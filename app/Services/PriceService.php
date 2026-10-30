<?php

namespace App\Services;

use App\Models\ActivityLogs;
use App\Models\PriceEvent;
use App\Models\Product;
use App\Models\User;
use App\Support\SessionClock;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The single place that decides what a product costs on a given business day.
 *
 * products.selling_price is always the normal price and is never rewritten. On a day covered by
 * a (non-cancelled) price event that includes the product, the price is the normal price changed
 * by the event's percentage, or set to the exact custom price the event gives it. After the last day the event simply stops matching, so prices go
 * back to normal on their own: there is nothing to schedule and nothing to restore.
 *
 * Resolve a fresh instance per use (app(PriceService::class)): it remembers the events it has
 * loaded for a date, which is right for one request but would go stale if kept for longer.
 *
 * Business-rule failures throw \DomainException with a message that is safe to show to staff.
 */
class PriceService
{
    public const MAX_PERCENT = 500;

    public const MAX_CUSTOM_PRICE = 100000;

    /** @var array<string, array<int, list<array{event: PriceEvent, custom: mixed, start: int, end: int}>>> product id => the events (and the part of the session they cover) per date */
    private array $eventsByDate = [];

    /**
     * The pub's business day for a moment in time. A day ends at pricing.day_ends_at, so 01:30 on
     * Sunday is still Saturday's business day.
     */
    public function businessDate(?CarbonInterface $at = null): Carbon
    {
        $at = $at ? Carbon::instance($at) : now();

        [$hour, $minute] = array_map('intval', explode(':', (string) config('pricing.day_ends_at', '03:00')) + [0, 0]);

        $cutoff = $at->copy()->setTime($hour, $minute);

        return ($at->lt($cutoff) ? $at->copy()->subDay() : $at->copy())->startOfDay();
    }

    /**
     * The price of a product on a business date.
     *
     * With `$minute` (minutes into the session, for a live order) a timed event only applies while
     * its window is open. Without it (the end-of-day stock count, which knows the day but not when
     * each unit sold) the event price is weighed by the share of trading hours the event covers,
     * so a whole-session event gives exactly the event price.
     *
     * `label` is the change from the normal price ("+20%", "-10%") and `increase` its direction,
     * both set only while an event applies.
     *
     * @return array{price: float, base: float, event: ?PriceEvent, label: ?string, increase: bool}
     */
    public function quoteOn(int $productId, float|int|string|null $basePrice, Carbon|string $date, ?int $minute = null): array
    {
        $base = round((float) $basePrice, 2);
        $none = ['price' => $base, 'base' => $base, 'event' => null, 'label' => null, 'increase' => false];
        $hits = $this->eventsOn($date)[$productId] ?? [];

        if ($hits === [] || $base <= 0) {
            return $none;
        }

        if ($minute !== null) {
            foreach ($hits as $hit) {
                if ($minute >= $hit['start'] && $minute < $hit['end']) {
                    $price = $this->eventPrice($hit['event'], $hit['custom'], $base);

                    return ['price' => $price, 'base' => $base, 'event' => $hit['event']] + $this->describeChange($hit['event'], $price, $base);
                }
            }

            return $none;
        }

        $delta = 0.0;
        $main = null;
        $mainShare = 0.0;

        foreach ($hits as $hit) {
            $share = $this->tradingShare($hit['start'], $hit['end']);

            if ($share <= 0) {
                continue;
            }

            $delta += ($this->eventPrice($hit['event'], $hit['custom'], $base) - $base) * $share;

            if ($share > $mainShare) {
                $main = $hit;
                $mainShare = $share;
            }
        }

        if (! $main) {
            return $none;
        }

        $price = round($base + $delta, 2);

        return ['price' => $price, 'base' => $base, 'event' => $main['event']] + $this->describeChange($main['event'], $price, $base);
    }

    public function priceOn(int $productId, float|int|string|null $basePrice, Carbon|string $date): float
    {
        return $this->quoteOn($productId, $basePrice, $date)['price'];
    }

    /** @return array{price: float, base: float, event: ?PriceEvent} */
    public function quoteNow(Product $product, ?CarbonInterface $at = null): array
    {
        $at = $at ? Carbon::instance($at) : now();

        return $this->quoteOn($product->id, $product->selling_price, $this->businessDate($at), SessionClock::minuteOf($at));
    }

    /**
     * Quotes for many products at the current moment.
     *
     * @param  iterable<Product>  $products
     * @return array<int, array{price: float, base: float, event: ?PriceEvent, label: ?string, increase: bool}> keyed by product id
     */
    public function quotesNow(iterable $products, ?CarbonInterface $at = null): array
    {
        $at = $at ? Carbon::instance($at) : now();
        $date = $this->businessDate($at);
        $minute = SessionClock::minuteOf($at);
        $quotes = [];

        foreach ($products as $product) {
            $quotes[$product->id] = $this->quoteOn($product->id, $product->selling_price, $date, $minute);
        }

        return $quotes;
    }

    /**
     * For the product list: each product's current or next price event, if it has one.
     *
     * @param  iterable<Product>  $products
     * @return array<int, array{event: PriceEvent, price: float, active: bool, label: string, increase: bool}> keyed by product id
     */
    public function upcomingFor(iterable $products): array
    {
        $prices = [];

        foreach ($products as $product) {
            $prices[$product->id] = (float) $product->selling_price;
        }

        if ($prices === []) {
            return [];
        }

        $today = $this->businessDate()->toDateString();
        $ids = array_keys($prices);

        $events = PriceEvent::active()
            ->where('ends_on', '>=', $today)
            ->whereHas('products', fn ($q) => $q->whereIn('products.id', $ids))
            ->with(['products' => fn ($q) => $q->whereIn('products.id', $ids)])
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get();

        $result = [];

        $minute = SessionClock::minuteOf(now());

        foreach ($events as $event) {
            $status = $event->statusOn($today, $minute);

            if ($status === PriceEvent::STATUS_ENDED) {
                continue;
            }

            foreach ($event->products as $product) {
                if (isset($result[$product->id])) {
                    continue;
                }

                $base = round($prices[$product->id], 2);
                $price = $this->eventPrice($event, $product->pivot->custom_price, $base);

                $result[$product->id] = [
                    'event' => $event,
                    'price' => $price,
                    'active' => $status === PriceEvent::STATUS_ACTIVE,
                ] + $this->describeChange($event, $price, $base);
            }
        }

        return $result;
    }

    // ------------------------------------------------------------------ changes

    /**
     * Schedule a price day (or range). Covers the products that are on the VIP list right now.
     *
     * @throws \DomainException
     */
    public function schedule(User $by, Carbon|string $start, Carbon|string $end, float $percent, ?string $name = null, ?string $startTime = null, ?string $endTime = null): PriceEvent
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();
        $percent = round($percent, 2);
        $name = $name !== null && trim($name) !== '' ? mb_substr(trim($name), 0, 255) : null;

        if ($percent == 0.0) {
            throw new \DomainException('Enter a percentage other than 0.');
        }

        if ($percent <= -100) {
            throw new \DomainException('A discount cannot be 100% or more.');
        }

        if ($percent > self::MAX_PERCENT) {
            throw new \DomainException('The increase cannot be more than '.self::MAX_PERCENT.'%.');
        }

        $this->assertDays($start, $end);
        [$startTime, $endTime] = $this->normalizeTimes($start, $end, $startTime, $endTime);

        return DB::transaction(function () use ($by, $start, $end, $percent, $name, $startTime, $endTime) {
            $productIds = Product::where('is_vip', true)->pluck('id')->all();

            if ($productIds === []) {
                throw new \DomainException('Add at least one product to the VIP list first.');
            }

            $this->assertNoClash($start, $end, $startTime, $endTime, $productIds);

            $event = PriceEvent::create([
                'name' => $name,
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'starts_time' => $startTime,
                'ends_time' => $endTime,
                'percent' => $percent,
                'created_by' => $by->id,
            ]);

            $event->products()->attach($productIds);

            $this->log($by, 'price_event_create', 'Scheduled VIP price day', $event, [
                'name' => $name,
                'starts_on' => $event->starts_on,
                'ends_on' => $event->ends_on,
                'starts_time' => $startTime,
                'ends_time' => $endTime,
                'percent' => $percent,
                'product_ids' => $productIds,
            ]);

            return $event;
        });
    }

    /**
     * Schedule a price day (or range) with an exact price per product instead of a percentage.
     * Blank prices and prices equal to the normal price mean "unchanged" and are left out; the
     * products that remain are the event's products (the VIP flag is not used).
     *
     * @param  array<int|string, mixed>  $prices  product id => price
     *
     * @throws \DomainException
     */
    public function scheduleCustom(User $by, Carbon|string $start, Carbon|string $end, array $prices, ?string $name = null, ?string $startTime = null, ?string $endTime = null): PriceEvent
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();
        $name = $name !== null && trim($name) !== '' ? mb_substr(trim($name), 0, 255) : null;

        $entered = [];

        foreach ($prices as $productId => $price) {
            if ($price === null || trim((string) $price) === '') {
                continue;
            }

            if (! is_numeric($price)) {
                throw new \DomainException('Every custom price must be a number.');
            }

            $price = round((float) $price, 2);

            if ($price <= 0) {
                throw new \DomainException('A custom price must be more than 0.');
            }

            if ($price > self::MAX_CUSTOM_PRICE) {
                throw new \DomainException('A custom price cannot be more than '.number_format(self::MAX_CUSTOM_PRICE).'.');
            }

            $entered[(int) $productId] = $price;
        }

        $this->assertDays($start, $end);
        [$startTime, $endTime] = $this->normalizeTimes($start, $end, $startTime, $endTime);

        return DB::transaction(function () use ($by, $start, $end, $entered, $name, $startTime, $endTime) {
            $products = Product::where('is_active', true)->whereIn('id', array_keys($entered))->get(['id', 'selling_price'])->keyBy('id');

            $changed = [];

            foreach ($entered as $productId => $price) {
                $product = $products[$productId] ?? null;

                if (! $product) {
                    throw new \DomainException('One of the products you priced is no longer available. Close this window and open it again.');
                }

                if (abs($price - round((float) $product->selling_price, 2)) >= 0.005) {
                    $changed[$productId] = $price;
                }
            }

            if ($changed === []) {
                throw new \DomainException('Enter a custom price for at least one product (one that differs from its normal price).');
            }

            $this->assertNoClash($start, $end, $startTime, $endTime, array_keys($changed));

            $event = PriceEvent::create([
                'name' => $name,
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'starts_time' => $startTime,
                'ends_time' => $endTime,
                'percent' => null,
                'created_by' => $by->id,
            ]);

            $event->products()->attach(collect($changed)->map(fn ($price) => ['custom_price' => $price])->all());

            $this->log($by, 'price_event_create', 'Scheduled VIP price day with custom prices', $event, [
                'name' => $name,
                'starts_on' => $event->starts_on,
                'ends_on' => $event->ends_on,
                'starts_time' => $startTime,
                'ends_time' => $endTime,
                'custom_prices' => $changed,
            ]);

            return $event;
        });
    }

    /** Prices return to normal at once; orders already placed keep the price they were taken at. */
    public function cancel(User $by, PriceEvent $event): PriceEvent
    {
        return DB::transaction(function () use ($by, $event) {
            $event = PriceEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($event->cancelled_at) {
                throw new \DomainException('This price day is already cancelled.');
            }

            $now = now();

            if ($event->statusOn($this->businessDate($now)->toDateString(), SessionClock::minuteOf($now)) === PriceEvent::STATUS_ENDED) {
                throw new \DomainException('This price day has already ended.');
            }

            $event->update(['cancelled_at' => now(), 'cancelled_by' => $by->id]);

            $this->log($by, 'price_event_cancel', 'Cancelled VIP price day', $event, [
                'name' => $event->name,
                'starts_on' => $event->starts_on,
                'ends_on' => $event->ends_on,
                'percent' => $event->isCustom() ? null : (float) $event->percent,
            ]);

            return $event;
        });
    }

    public function setVip(User $by, Product $product, bool $vip): void
    {
        if ($product->is_vip === $vip) {
            return;
        }

        $product->update(['is_vip' => $vip]);

        $this->log($by, $vip ? 'vip_add' : 'vip_remove', $vip ? "Added {$product->name} to the VIP list" : "Removed {$product->name} from the VIP list", $product, [
            'product' => $product->name,
        ]);
    }

    public function describeDays(PriceEvent $event): string
    {
        if ($event->hasTimes()) {
            return $this->describeRun($event->starts_on, $event->ends_on, $event->startTime(), $event->endTime());
        }

        $start = Carbon::parse($event->starts_on);

        if ($event->starts_on === $event->ends_on) {
            return $start->format('D j M Y');
        }

        return $start->format('D j M').' to '.Carbon::parse($event->ends_on)->format('D j M Y');
    }

    /**
     * What a price day means in real calendar moments, for the form and the list:
     * "Sat 8 Nov 18:00 to Sun 9 Nov 01:00". Without times it is the whole sessions, up to the
     * closing hour after the last one ("Sat 8 Nov session, until Sun 9 Nov 03:00").
     */
    public function describeRun(Carbon|string $startsOn, Carbon|string $endsOn, ?string $startTime = null, ?string $endTime = null): string
    {
        $startsOn = Carbon::parse($startsOn)->startOfDay();
        $endsOn = Carbon::parse($endsOn)->startOfDay();
        $startTime = $startTime ? substr($startTime, 0, 5) : null;
        $endTime = $endTime ? substr($endTime, 0, 5) : null;

        $format = 'D j M H:i';
        $until = SessionClock::instant($endsOn, $endTime ? SessionClock::endMinute($endTime) : SessionClock::MINUTES);

        if ($startTime || $endTime) {
            $from = SessionClock::instant($startsOn, $startTime ? SessionClock::sessionMinute($startTime) : 0);

            return $from->format($format).' to '.$until->format($format);
        }

        $days = $startsOn->equalTo($endsOn)
            ? $startsOn->format('D j M').' session'
            : $startsOn->format('D j M').' to '.$endsOn->format('D j M').' sessions';

        return $days.', until '.$until->format($format);
    }

    // ------------------------------------------------------------------ internals

    private function applyPercent(float $base, float $percent): float
    {
        return round($base * (1 + $percent / 100), 2);
    }

    /** The price an event sets for a product: its custom price, or the event's percentage on the base. */
    private function eventPrice(PriceEvent $event, float|string|null $custom, float $base): float
    {
        if ($custom !== null) {
            return round((float) $custom, 2);
        }

        return $this->applyPercent($base, (float) $event->percent);
    }

    /** @return array{label: string, increase: bool} how the event's price differs from the normal price */
    private function describeChange(PriceEvent $event, float $price, float $base): array
    {
        if (! $event->isCustom()) {
            return ['label' => $event->percentLabel(), 'increase' => $event->isIncrease()];
        }

        $change = $base > 0 ? round(($price / $base - 1) * 100, 1) : 0.0;

        return [
            'label' => ($change > 0 ? '+' : '-').rtrim(rtrim(number_format(abs($change), 1, '.', ''), '0'), '.').'%',
            'increase' => $price > $base,
        ];
    }

    /** @throws \DomainException */
    private function assertDays(Carbon $start, Carbon $end): void
    {
        if ($end->lt($start)) {
            throw new \DomainException('The last day cannot be before the first day.');
        }

        if ($start->lt($this->businessDate())) {
            throw new \DomainException('The first day cannot be in the past.');
        }
    }

    /**
     * Times are optional and must be HH:MM. Both are placed on the session, so a one-day event must
     * end after it starts ("01:00" is after "18:00" because it falls after midnight).
     *
     * @return array{0: ?string, 1: ?string}
     *
     * @throws \DomainException
     */
    private function normalizeTimes(Carbon $start, Carbon $end, ?string $startTime, ?string $endTime): array
    {
        $clean = [];

        foreach ([$startTime, $endTime] as $time) {
            $time = $time === null ? '' : trim($time);

            if ($time !== '' && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time)) {
                throw new \DomainException('Enter the time as hours and minutes, for example 18:00.');
            }

            $clean[] = $time === '' ? null : substr($time, 0, 5);
        }

        [$startTime, $endTime] = $clean;

        if ($start->equalTo($end) && $startTime && $endTime && SessionClock::endMinute($endTime) <= SessionClock::sessionMinute($startTime)) {
            throw new \DomainException('The end time must be after the start time (times after midnight, up to the closing hour, count as the same night).');
        }

        return [$startTime, $endTime];
    }

    /**
     * Two events clash only if they share a product on a business date AND their windows overlap
     * that day, so a 15:00 to 18:00 event and an 18:00 to closing event can sit side by side.
     *
     * @param  array<int, int>  $productIds
     *
     * @throws \DomainException
     */
    private function assertNoClash(Carbon $start, Carbon $end, ?string $startTime, ?string $endTime, array $productIds): void
    {
        $candidates = PriceEvent::active()
            ->where('starts_on', '<=', $end->toDateString())
            ->where('ends_on', '>=', $start->toDateString())
            ->whereHas('products', fn ($q) => $q->whereIn('products.id', $productIds))
            ->get();

        foreach ($candidates as $other) {
            $from = max($start->toDateString(), $other->starts_on);
            $to = min($end->toDateString(), $other->ends_on);

            for ($day = Carbon::parse($from); $day->lte(Carbon::parse($to)); $day->addDay()) {
                $date = $day->toDateString();
                [$s1, $e1] = SessionClock::window($date, $start->toDateString(), $end->toDateString(), $startTime, $endTime);
                [$s2, $e2] = $other->windowOn($date);

                if (max($s1, $s2) < min($e1, $e2)) {
                    throw new \DomainException('These days overlap another VIP price day ('
                        .($other->name ?: $this->describeDays($other)).') that covers some of the same products. Cancel it first or pick other days or times.');
                }
            }
        }
    }

    /** Share of the pub's trading hours (opening to closing) that a [start, end) session window covers. */
    private function tradingShare(int $start, int $end): float
    {
        $opens = SessionClock::opensMinute();
        $trading = SessionClock::MINUTES - $opens;

        if ($trading <= 0) {
            return 0.0;
        }

        return max(0, min($end, SessionClock::MINUTES) - max($start, $opens)) / $trading;
    }

    /** @return array<int, list<array{event: PriceEvent, custom: mixed, start: int, end: int}>> product id => the events that set its price that day */
    private function eventsOn(Carbon|string $date): array
    {
        $day = Carbon::parse($date)->toDateString();

        return $this->eventsByDate[$day] ??= $this->loadEvents($day);
    }

    private function loadEvents(string $day): array
    {
        $map = [];

        PriceEvent::active()
            ->where('starts_on', '<=', $day)
            ->where('ends_on', '>=', $day)
            ->with(['products' => fn ($q) => $q->select('products.id')])
            ->orderBy('id')
            ->get()
            ->each(function (PriceEvent $event) use (&$map, $day) {
                [$start, $end] = $event->windowOn($day);

                foreach ($event->products as $product) {
                    $map[$product->id][] = ['event' => $event, 'custom' => $product->pivot->custom_price, 'start' => $start, 'end' => $end];
                }
            });

        return $map;
    }

    private function log(User $by, string $action, string $description, \Illuminate\Database\Eloquent\Model $entity, array $metadata): void
    {
        ActivityLogs::create([
            'user_id' => $by->id,
            'action_type' => $action,
            'description' => $description,
            'entity_type' => $entity instanceof PriceEvent ? 'price_event' : 'product',
            'entity_id' => $entity->getKey(),
            'metadata' => $metadata,
        ]);
    }
}

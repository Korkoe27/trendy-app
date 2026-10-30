<?php

namespace App\Services;

use App\Models\ActivityLogs;
use App\Models\PriceEvent;
use App\Models\Product;
use App\Models\User;
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

    /** @var array<string, array<int, array{event: PriceEvent, custom: ?float}>> product id => event and its custom price, per date */
    private array $eventsByDate = [];

    /**
     * The pub's business day for a moment in time. A day ends at pricing.day_ends_at, so 01:30 on
     * Sunday is still Saturday's business day.
     */
    public function businessDate(?CarbonInterface $at = null): Carbon
    {
        $at = $at ? Carbon::instance($at) : now();

        [$hour, $minute] = array_map('intval', explode(':', (string) config('pricing.day_ends_at', '05:00')) + [0, 0]);

        $cutoff = $at->copy()->setTime($hour, $minute);

        return ($at->lt($cutoff) ? $at->copy()->subDay() : $at->copy())->startOfDay();
    }

    /**
     * `label` is the change from the normal price ("+20%", "-10%") and `increase` its direction,
     * both set only while an event applies.
     *
     * @return array{price: float, base: float, event: ?PriceEvent, label: ?string, increase: bool}
     */
    public function quoteOn(int $productId, float|int|string|null $basePrice, Carbon|string $date): array
    {
        $base = round((float) $basePrice, 2);
        $hit = $this->eventsOn($date)[$productId] ?? null;

        if (! $hit || $base <= 0) {
            return ['price' => $base, 'base' => $base, 'event' => null, 'label' => null, 'increase' => false];
        }

        $price = $this->eventPrice($hit['event'], $hit['custom'], $base);

        return ['price' => $price, 'base' => $base, 'event' => $hit['event']] + $this->describeChange($hit['event'], $price, $base);
    }

    public function priceOn(int $productId, float|int|string|null $basePrice, Carbon|string $date): float
    {
        return $this->quoteOn($productId, $basePrice, $date)['price'];
    }

    /** @return array{price: float, base: float, event: ?PriceEvent} */
    public function quoteNow(Product $product, ?CarbonInterface $at = null): array
    {
        return $this->quoteOn($product->id, $product->selling_price, $this->businessDate($at));
    }

    /**
     * Quotes for many products at the current moment.
     *
     * @param  iterable<Product>  $products
     * @return array<int, array{price: float, base: float, event: ?PriceEvent, label: ?string, increase: bool}> keyed by product id
     */
    public function quotesNow(iterable $products, ?CarbonInterface $at = null): array
    {
        $date = $this->businessDate($at);
        $quotes = [];

        foreach ($products as $product) {
            $quotes[$product->id] = $this->quoteOn($product->id, $product->selling_price, $date);
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

        foreach ($events as $event) {
            foreach ($event->products as $product) {
                if (isset($result[$product->id])) {
                    continue;
                }

                $base = round($prices[$product->id], 2);
                $price = $this->eventPrice($event, $product->pivot->custom_price, $base);

                $result[$product->id] = [
                    'event' => $event,
                    'price' => $price,
                    'active' => $event->starts_on <= $today,
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
    public function schedule(User $by, Carbon|string $start, Carbon|string $end, float $percent, ?string $name = null): PriceEvent
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

        return DB::transaction(function () use ($by, $start, $end, $percent, $name) {
            $productIds = Product::where('is_vip', true)->pluck('id')->all();

            if ($productIds === []) {
                throw new \DomainException('Add at least one product to the VIP list first.');
            }

            $this->assertNoClash($start, $end, $productIds);

            $event = PriceEvent::create([
                'name' => $name,
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'percent' => $percent,
                'created_by' => $by->id,
            ]);

            $event->products()->attach($productIds);

            $this->log($by, 'price_event_create', 'Scheduled VIP price day', $event, [
                'name' => $name,
                'starts_on' => $event->starts_on,
                'ends_on' => $event->ends_on,
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
    public function scheduleCustom(User $by, Carbon|string $start, Carbon|string $end, array $prices, ?string $name = null): PriceEvent
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

        return DB::transaction(function () use ($by, $start, $end, $entered, $name) {
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

            $this->assertNoClash($start, $end, array_keys($changed));

            $event = PriceEvent::create([
                'name' => $name,
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'percent' => null,
                'created_by' => $by->id,
            ]);

            $event->products()->attach(collect($changed)->map(fn ($price) => ['custom_price' => $price])->all());

            $this->log($by, 'price_event_create', 'Scheduled VIP price day with custom prices', $event, [
                'name' => $name,
                'starts_on' => $event->starts_on,
                'ends_on' => $event->ends_on,
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

            if ($event->ends_on < $this->businessDate()->toDateString()) {
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
        $start = Carbon::parse($event->starts_on);

        if ($event->starts_on === $event->ends_on) {
            return $start->format('D j M Y');
        }

        return $start->format('D j M').' to '.Carbon::parse($event->ends_on)->format('D j M Y');
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
     * @param  array<int, int>  $productIds
     *
     * @throws \DomainException
     */
    private function assertNoClash(Carbon $start, Carbon $end, array $productIds): void
    {
        $clash = PriceEvent::active()
            ->where('starts_on', '<=', $end->toDateString())
            ->where('ends_on', '>=', $start->toDateString())
            ->whereHas('products', fn ($q) => $q->whereIn('products.id', $productIds))
            ->first();

        if ($clash) {
            throw new \DomainException('These days overlap another VIP price day ('
                .($clash->name ?: $this->describeDays($clash)).') that covers some of the same products. Cancel it first or pick other days.');
        }
    }

    /** @return array<int, array{event: PriceEvent, custom: ?float}> product id => the event that sets its price that day */
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
            ->each(function (PriceEvent $event) use (&$map) {
                foreach ($event->products as $product) {
                    $map[$product->id] = ['event' => $event, 'custom' => $product->pivot->custom_price];
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

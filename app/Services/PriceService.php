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
 * by the event's percentage. After the last day the event simply stops matching, so prices go
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

    /** @var array<string, array<int, PriceEvent>> product id => event, per date */
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
     * @return array{price: float, base: float, event: ?PriceEvent}
     */
    public function quoteOn(int $productId, float|int|string|null $basePrice, Carbon|string $date): array
    {
        $base = round((float) $basePrice, 2);
        $event = $this->eventsOn($date)[$productId] ?? null;

        if (! $event || $base <= 0) {
            return ['price' => $base, 'base' => $base, 'event' => null];
        }

        return ['price' => $this->applyPercent($base, (float) $event->percent), 'base' => $base, 'event' => $event];
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
     * @return array<int, array{price: float, base: float, event: ?PriceEvent}> keyed by product id
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
     * @return array<int, array{event: PriceEvent, price: float, active: bool}> keyed by product id
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
                $result[$product->id] ??= [
                    'event' => $event,
                    'price' => $this->applyPercent(round($prices[$product->id], 2), (float) $event->percent),
                    'active' => $event->starts_on <= $today,
                ];
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

        if ($end->lt($start)) {
            throw new \DomainException('The last day cannot be before the first day.');
        }

        if ($start->lt($this->businessDate())) {
            throw new \DomainException('The first day cannot be in the past.');
        }

        return DB::transaction(function () use ($by, $start, $end, $percent, $name) {
            $productIds = Product::where('is_vip', true)->pluck('id')->all();

            if ($productIds === []) {
                throw new \DomainException('Add at least one product to the VIP list first.');
            }

            $clash = PriceEvent::active()
                ->where('starts_on', '<=', $end->toDateString())
                ->where('ends_on', '>=', $start->toDateString())
                ->whereHas('products', fn ($q) => $q->whereIn('products.id', $productIds))
                ->first();

            if ($clash) {
                throw new \DomainException('These days overlap another VIP price day ('
                    .($clash->name ?: $this->describeDays($clash)).') that covers some of the same products. Cancel it first or pick other days.');
            }

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
                'percent' => (float) $event->percent,
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

    /** @return array<int, PriceEvent> product id => the event that sets its price that day */
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
                    $map[$product->id] = $event;
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

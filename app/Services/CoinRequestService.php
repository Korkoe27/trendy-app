<?php

namespace App\Services;

use App\Models\ActivityLogs;
use App\Models\CoinRequest;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Snooker and other games have no stock: a game product has a number of coins per day that come
 * back every night. The attendant requests coins, the bar gives them, and the end-of-day count
 * checks the snooker money against the coins given (coins x the price when they were given).
 *
 * Coins available are worked out per business day (daily coins minus coins given or still waiting
 * that day), so the pool is full again at the closing hour with nothing to reset.
 *
 * Business-rule failures throw \DomainException with a message that is safe to show to staff.
 */
class CoinRequestService
{
    /** Active game products that have coins per day. */
    public function games(): Collection
    {
        return Product::games()
            ->where('is_active', true)
            ->where('daily_coins', '>', 0)
            ->orderBy('name')
            ->get();
    }

    /** The most coins one request can ask for (config coins.max_per_request, default 1). */
    public function maxPerRequest(): int
    {
        return max(1, (int) config('coins.max_per_request', 1));
    }

    /** The attendant's request that is still waiting for the bar in the current session, if any. */
    public function waitingFor(User $user): ?CoinRequest
    {
        return CoinRequest::where('requested_by', $user->id)
            ->where('status', CoinRequest::STATUS_REQUESTED)
            ->whereDate('business_date', $this->businessDate()->toDateString())
            ->orderBy('id')
            ->first();
    }

    public function blocksNewRequests(): bool
    {
        return (bool) config('coins.one_waiting_per_attendant', true);
    }

    public function businessDate(): Carbon
    {
        return app(PriceService::class)->businessDate();
    }

    /** Coins given or waiting on a business day. */
    public function used(Product $game, Carbon|string|null $date = null): int
    {
        return (int) CoinRequest::where('product_id', $game->id)
            ->whereDate('business_date', Carbon::parse($date ?? $this->businessDate())->toDateString())
            ->whereIn('status', [CoinRequest::STATUS_REQUESTED, CoinRequest::STATUS_GIVEN])
            ->sum('quantity');
    }

    public function available(Product $game, Carbon|string|null $date = null): int
    {
        return max(0, (int) $game->daily_coins - $this->used($game, $date));
    }

    /**
     * @throws \DomainException
     */
    public function request(User $by, Product $game, int $quantity, ?string $note = null): CoinRequest
    {
        $this->assertGame($game);

        if ($quantity < 1) {
            throw new \DomainException('Ask for at least one coin.');
        }

        $max = $this->maxPerRequest();

        if ($quantity > $max) {
            throw new \DomainException($max === 1
                ? 'You can only request 1 coin at a time.'
                : "You can only request {$max} coins at a time.");
        }

        return DB::transaction(function () use ($by, $game, $quantity, $note) {
            // Lock the game so two attendants cannot both take the last coins.
            $game = Product::whereKey($game->id)->lockForUpdate()->firstOrFail();
            $this->assertGame($game);

            if ($this->blocksNewRequests() && $this->waitingFor($by)) {
                throw new \DomainException('You already have a coin waiting for the bar. Wait until it is given, or cancel it first.');
            }

            $date = $this->businessDate();
            $available = $this->available($game, $date);

            if ($available <= 0) {
                throw new \DomainException("No {$game->name} coins are left today.");
            }

            if ($quantity > $available) {
                throw new \DomainException("Only {$available} {$game->name} ".($available === 1 ? 'coin is' : 'coins are').' left today.');
            }

            $request = CoinRequest::create([
                'product_id' => $game->id,
                'product_name' => $game->name,
                'quantity' => $quantity,
                'status' => CoinRequest::STATUS_REQUESTED,
                'business_date' => $date->toDateString(),
                'requested_by' => $by->id,
                'requested_by_name' => $by->name ?? 'Unknown',
                'requested_at' => now(),
                'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null,
            ]);

            $this->log($by, 'coin_request_create', "Requested {$quantity} {$game->name} ".($quantity === 1 ? 'coin' : 'coins'), $request);

            return $request;
        });
    }

    /**
     * The bar hands the coins over: the price is captured now, so a price day applies to snooker too.
     *
     * @throws \DomainException
     */
    public function give(CoinRequest $request, User $by): CoinRequest
    {
        return DB::transaction(function () use ($request, $by) {
            $request = CoinRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isWaiting()) {
                throw new \DomainException('This request is already '.$request->status.'.');
            }

            $game = Product::find($request->product_id);

            if (! $game) {
                throw new \DomainException('This game no longer exists.');
            }

            $price = app(PriceService::class)->quoteNow($game)['price'];

            if ($price <= 0) {
                throw new \DomainException("{$game->name} has no coin price set.");
            }

            $request->update([
                'status' => CoinRequest::STATUS_GIVEN,
                'given_by' => $by->id,
                'given_by_name' => $by->name ?? 'Unknown',
                'given_at' => now(),
                'unit_price' => $price,
                'total' => round($price * $request->quantity, 2),
            ]);

            $this->log($by, 'coin_request_give', "Gave {$request->quantity} {$request->product_name} ".($request->quantity === 1 ? 'coin' : 'coins').' (₵'.number_format((float) $request->total, 2).')', $request);

            return $request;
        });
    }

    /**
     * A waiting request: its own requester, or anyone who may cancel others' requests.
     *
     * @throws \DomainException
     */
    public function cancel(CoinRequest $request, User $by): CoinRequest
    {
        return DB::transaction(function () use ($request, $by) {
            $request = CoinRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isWaiting()) {
                throw new \DomainException('Only a waiting request can be cancelled. This one is '.$request->status.'.');
            }

            if ((int) $request->requested_by !== (int) $by->id && ! $by->hasPermission('delete', 'coin_requests')) {
                throw new \DomainException('Only the attendant who asked, or a manager, can cancel this request.');
            }

            $request->update(['status' => CoinRequest::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancelled_by' => $by->id]);

            $this->log($by, 'coin_request_cancel', "Cancelled a request for {$request->quantity} {$request->product_name}", $request);

            return $request;
        });
    }

    /**
     * A given request recorded by mistake: it stops counting (the coins return to the pool).
     *
     * @throws \DomainException
     */
    public function void(CoinRequest $request, User $by, string $reason): CoinRequest
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \DomainException('Please give a reason for voiding these coins.');
        }

        if (! $by->hasPermission('delete', 'coin_requests')) {
            throw new \DomainException('Only a manager can void coins that were given.');
        }

        return DB::transaction(function () use ($request, $by, $reason) {
            $request = CoinRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isGiven()) {
                throw new \DomainException('Only coins that were given can be voided. This request is '.$request->status.'.');
            }

            $request->update(['status' => CoinRequest::STATUS_VOIDED, 'voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => mb_substr($reason, 0, 255)]);

            $this->log($by, 'coin_request_void', "Voided {$request->quantity} {$request->product_name} coins: {$reason}", $request);

            return $request;
        });
    }

    /**
     * Coins given on a business day and the money they should have brought in.
     *
     * @return array{coins: int, amount: float}
     */
    public function givenOn(Carbon|string $date): array
    {
        $row = CoinRequest::whereDate('business_date', Carbon::parse($date)->toDateString())
            ->where('status', CoinRequest::STATUS_GIVEN)
            ->selectRaw('COALESCE(SUM(quantity), 0) as coins, COALESCE(SUM(total), 0) as amount')
            ->first();

        return ['coins' => (int) $row->coins, 'amount' => round((float) $row->amount, 2)];
    }

    private function assertGame(Product $game): void
    {
        $game->loadMissing('category');

        if (! $game->isGame()) {
            throw new \DomainException('Coins can only be requested for a game product.');
        }

        if (! $game->is_active || (int) $game->daily_coins < 1) {
            throw new \DomainException("{$game->name} is not set up with coins per day.");
        }
    }

    private function log(User $by, string $action, string $description, CoinRequest $request): void
    {
        ActivityLogs::create([
            'user_id' => $by->id,
            'action_type' => $action,
            'description' => $description,
            'entity_type' => 'coin_request',
            'entity_id' => $request->id,
            'metadata' => [
                'product' => $request->product_name,
                'quantity' => $request->quantity,
                'business_date' => $request->business_date instanceof Carbon ? $request->business_date->toDateString() : (string) $request->business_date,
                'timestamp' => now(),
            ],
        ]);
    }
}

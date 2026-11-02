<?php

namespace App\Services;

use App\Models\ActivityLogs;
use App\Models\CoinDay;
use App\Models\CoinRequest;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
    public function request(User $by, Product $game, int $quantity, ?string $note = null, ?string $customerName = null): CoinRequest
    {
        $this->assertGame($game);

        $customerName = trim((string) $customerName);

        if ($customerName === '') {
            throw new \DomainException('Enter the name of the player the coin is for.');
        }

        if ($quantity < 1) {
            throw new \DomainException('Ask for at least one coin.');
        }

        $max = $this->maxPerRequest();

        if ($quantity > $max) {
            throw new \DomainException($max === 1
                ? 'You can only request 1 coin at a time.'
                : "You can only request {$max} coins at a time.");
        }

        return DB::transaction(function () use ($by, $game, $quantity, $note, $customerName) {
            // Lock the game so two attendants cannot both take the last coins.
            $game = Product::whereKey($game->id)->lockForUpdate()->firstOrFail();
            $this->assertGame($game);

            if ($this->blocksNewRequests() && $this->waitingFor($by)) {
                throw new \DomainException('You already have a coin waiting for the bar. Wait until it is given, or cancel it first.');
            }

            $this->assertOpen();

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
                'customer_name' => mb_substr($customerName, 0, 100),
                'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null,
            ]);

            $this->log($by, 'coin_request_create', "Requested {$quantity} {$game->name} ".($quantity === 1 ? 'coin' : 'coins').' for '.$request->customer_name, $request);

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

    // ------------------------------------------------------------------ opening and closing the night

    /**
     * Requests are open from the opening hour (pricing.opens_at, 15:00) until the closing hour
     * (pricing.day_ends_at, 03:00, when the business day rolls over), unless the bar closed the night
     * by hand. This is the single source for the screen and for request().
     *
     * @return array{open: bool, reason: ?string, date: Carbon, opens_at: Carbon, closed_by: ?string, closed_at: ?Carbon}
     */
    public function availability(?CarbonInterface $at = null): array
    {
        $at = $at ? Carbon::instance($at) : now();
        $date = $this->businessDate($at);

        [$hour, $minute] = array_map('intval', explode(':', (string) config('pricing.opens_at', '15:00')) + [0, 0]);
        $opensAt = $date->copy()->setTime($hour, $minute);

        $day = CoinDay::whereDate('business_date', $date->toDateString())->first();

        $reason = null;

        if ($day?->isClosed()) {
            $reason = 'closed';
        } elseif ($at->lt($opensAt)) {
            $reason = 'before_opening';
        }

        return [
            'open' => $reason === null,
            'reason' => $reason,
            'date' => $date,
            'opens_at' => $opensAt,
            'closed_by' => $day?->isClosed() ? $day->closed_by_name : null,
            'closed_at' => $day?->isClosed() ? $day->closed_at : null,
        ];
    }

    /**
     * @throws \DomainException
     */
    private function assertOpen(): void
    {
        $state = $this->availability();

        if ($state['reason'] === 'before_opening') {
            throw new \DomainException('Coin requests open at '.$state['opens_at']->format('g:i A').'.');
        }

        if ($state['reason'] === 'closed') {
            throw new \DomainException('Coin requests are closed for tonight.');
        }
    }

    /**
     * The bar ends the night: no more requests until tomorrow's opening. Coins still waiting for the
     * bar are cancelled, and what had been given is stored as a snapshot on the day's entry.
     *
     * @throws \DomainException
     */
    public function closeDay(User $by): CoinDay
    {
        $this->assertMayClose($by);

        return DB::transaction(function () use ($by) {
            $date = $this->businessDate();
            $day = $this->lockedDay($date);

            if ($day->isClosed()) {
                throw new \DomainException('Coin requests are already closed for tonight.');
            }

            if ($this->availability()['reason'] === 'before_opening') {
                throw new \DomainException('Coin requests have not opened yet, so there is nothing to close.');
            }

            $waiting = CoinRequest::whereDate('business_date', $date->toDateString())
                ->where('status', CoinRequest::STATUS_REQUESTED)
                ->lockForUpdate()
                ->get();

            foreach ($waiting as $request) {
                $request->update([
                    'status' => CoinRequest::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancelled_by' => $by->id,
                    'note' => mb_substr(trim(($request->note ? $request->note.' · ' : '').'Closed for the night'), 0, 255),
                ]);
            }

            $given = $this->givenOn($date);

            $day->update([
                'closed_at' => now(),
                'closed_by' => $by->id,
                'closed_by_name' => $by->name ?? 'Unknown',
                'closed_coins' => $given['coins'],
                'closed_amount' => $given['amount'],
                'closed_cancelled' => $waiting->count(),
            ]);

            $this->logDay($by, 'coin_day_close', "Closed coin requests for {$date->format('D j M')}: {$given['coins']} coins given (₵".number_format($given['amount'], 2).")".($waiting->count() ? ", {$waiting->count()} waiting cancelled" : ''), $day, ['cancelled' => $waiting->count()]);

            return $day;
        });
    }

    /**
     * Undo a close done by mistake. Only tonight's session can be reopened; a finished night stays closed.
     *
     * @throws \DomainException
     */
    public function reopenDay(User $by): CoinDay
    {
        $this->assertMayClose($by);

        return DB::transaction(function () use ($by) {
            $date = $this->businessDate();
            $day = $this->lockedDay($date);

            if (! $day->isClosed()) {
                throw new \DomainException('Coin requests are not closed.');
            }

            $day->update(['closed_at' => null, 'closed_by' => null, 'closed_by_name' => null, 'closed_coins' => null, 'closed_amount' => null, 'closed_cancelled' => null]);

            $this->logDay($by, 'coin_day_reopen', "Reopened coin requests for {$date->format('D j M')}", $day);

            return $day;
        });
    }

    private function assertMayClose(User $by): void
    {
        if (! $by->hasPermission('modify', 'coin_requests')) {
            throw new \DomainException('Only the bar or a manager can close or reopen coin requests.');
        }
    }

    private function lockedDay(Carbon $date): CoinDay
    {
        $day = CoinDay::whereDate('business_date', $date->toDateString())->lockForUpdate()->first();

        return $day ?? CoinDay::create(['business_date' => $date->toDateString()]);
    }

    // ------------------------------------------------------------------ day by day

    /**
     * One row per business day that has requests or was closed by hand, newest first. The figures
     * are read live from the requests, so a later void is reflected.
     */
    public function history(int $perPage = 10, string $pageName = 'daysPage'): LengthAwarePaginator
    {
        $dates = CoinRequest::query()->select('business_date')->toBase()
            ->union(CoinDay::query()->select('business_date')->toBase());

        $page = DB::query()->fromSub($dates, 'd')->select('business_date')->orderByDesc('business_date')->paginate($perPage, ['business_date'], $pageName);

        $keys = collect($page->items())->map(fn ($row) => Carbon::parse($row->business_date)->toDateString())->all();

        $totals = CoinRequest::query()
            ->whereIn('business_date', $keys)
            ->selectRaw("business_date, COUNT(*) as requests,
                COALESCE(SUM(CASE WHEN status = 'given' THEN quantity ELSE 0 END), 0) as coins,
                COALESCE(SUM(CASE WHEN status = 'given' THEN total ELSE 0 END), 0) as amount,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'voided' THEN 1 ELSE 0 END) as voided,
                SUM(CASE WHEN status = 'requested' THEN 1 ELSE 0 END) as waiting")
            ->groupBy('business_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->business_date)->toDateString());

        $days = CoinDay::whereIn('business_date', $keys)->get()->keyBy(fn ($day) => $day->business_date->toDateString());

        return $page->through(function ($row) use ($totals, $days) {
            $key = Carbon::parse($row->business_date)->toDateString();
            $t = $totals->get($key);
            $day = $days->get($key);

            return [
                'date' => Carbon::parse($key),
                'requests' => (int) ($t->requests ?? 0),
                'coins' => (int) ($t->coins ?? 0),
                'amount' => round((float) ($t->amount ?? 0), 2),
                'cancelled' => (int) ($t->cancelled ?? 0),
                'voided' => (int) ($t->voided ?? 0),
                'waiting' => (int) ($t->waiting ?? 0),
                'closed' => (bool) $day?->isClosed(),
                'closed_by' => $day?->closed_by_name,
                'closed_at' => $day?->closed_at,
                'tonight' => $key === $this->businessDate()->toDateString(),
            ];
        });
    }

    /**
     * Everything about one business day for the View window.
     *
     * @return array{date: Carbon, summary: array<string, mixed>, games: Collection, attendants: Collection, requests: Collection}
     */
    public function dayDetail(Carbon|string $date): array
    {
        $date = Carbon::parse($date)->startOfDay();
        $requests = CoinRequest::whereDate('business_date', $date->toDateString())->orderBy('requested_at')->orderBy('id')->get();
        $given = $requests->where('status', CoinRequest::STATUS_GIVEN);
        $day = CoinDay::whereDate('business_date', $date->toDateString())->first();

        return [
            'date' => $date,
            'summary' => [
                'requests' => $requests->count(),
                'coins' => (int) $given->sum('quantity'),
                'amount' => round((float) $given->sum('total'), 2),
                'cancelled' => $requests->where('status', CoinRequest::STATUS_CANCELLED)->count(),
                'voided' => $requests->where('status', CoinRequest::STATUS_VOIDED)->count(),
                'waiting' => $requests->where('status', CoinRequest::STATUS_REQUESTED)->count(),
                'closed' => (bool) $day?->isClosed(),
                'closed_by' => $day?->closed_by_name,
                'closed_at' => $day?->closed_at,
            ],
            'games' => $requests->groupBy('product_name')->map(fn (Collection $rows, string $name) => [
                'name' => $name,
                'requests' => $rows->count(),
                'coins' => (int) $rows->where('status', CoinRequest::STATUS_GIVEN)->sum('quantity'),
                'amount' => round((float) $rows->where('status', CoinRequest::STATUS_GIVEN)->sum('total'), 2),
            ])->values(),
            'attendants' => $requests->groupBy(fn ($r) => $r->requested_by_name ?? 'Unknown')->map(fn (Collection $rows, string $name) => [
                'name' => $name,
                'requests' => $rows->count(),
                'coins' => (int) $rows->where('status', CoinRequest::STATUS_GIVEN)->sum('quantity'),
                'amount' => round((float) $rows->where('status', CoinRequest::STATUS_GIVEN)->sum('total'), 2),
            ])->values(),
            'requests' => $requests,
        ];
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

    private function logDay(User $by, string $action, string $description, CoinDay $day, array $extra = []): void
    {
        ActivityLogs::create([
            'user_id' => $by->id,
            'action_type' => $action,
            'description' => $description,
            'entity_type' => 'coin_day',
            'entity_id' => $day->id,
            'metadata' => ['business_date' => $day->business_date->toDateString(), 'timestamp' => now()] + $extra,
        ]);
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

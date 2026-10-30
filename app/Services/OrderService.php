<?php

namespace App\Services;

use App\Models\ActivityLogs;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Everything that changes an order goes through here. Postgres is the record: each change runs in
 * a transaction on a row-locked order. Redis (the cache store) is only the live layer on top:
 * a per-order lock so two people cannot close or edit the same tab at once, and a short-lived
 * cache of the open board. If Redis is unavailable, the lock is skipped (the row lock still
 * protects the data) and the board is read straight from the database.
 *
 * Orders never touch stock: the end-of-day inventory count stays the single source of truth.
 *
 * Business-rule failures throw \DomainException with a message that is safe to show to staff.
 */
class OrderService
{
    public const BOARD_CACHE_KEY = 'orders:board';

    public const MAX_QUANTITY = 200;

    /** How long a request waits for another person's change to the same order to finish. */
    public function __construct(private int $lockWaitSeconds = 5)
    {
    }

    /**
     * @param  array<int, int>  $items  product id => quantity
     *
     * @throws \DomainException
     */
    public function place(User $staff, string $customerName, string $customerPhone, array $items, ?string $notes = null): Order
    {
        $customerName = trim($customerName);
        $phone = Phone::normalize($customerPhone);

        if ($customerName === '') {
            throw new \DomainException('Enter a name for the customer.');
        }

        if (! $phone) {
            throw new \DomainException('Enter a valid Ghana mobile number for the customer.');
        }

        $lines = $this->priceItems($items);

        $order = DB::transaction(function () use ($staff, $customerName, $phone, $lines, $notes) {
            $order = Order::create([
                'customer_name' => $customerName,
                'customer_phone' => $phone,
                'status' => Order::STATUS_PENDING,
                'payment_status' => Order::PAYMENT_UNPAID,
                'notes' => $notes ? trim($notes) : null,
                'taken_by' => $staff->id,
                'taken_by_name' => $staff->name ?? 'Unknown',
                'total' => 0,
            ]);

            $order->update(['code' => sprintf('ORD-%04d', $order->id)]);

            $this->insertItems($order, $lines, $staff);
            $this->recalculate($order);

            $this->log($staff, 'order_create', "Placed {$order->code} for {$order->customer_name}", $order, ['total' => (float) $order->total]);

            return $order;
        });

        $this->flushBoard();

        return $order->load('items');
    }

    /** The customer's open tab, if they have one. Matches on the normalised phone; the oldest wins. */
    public function findOpenTab(string $customerPhone): ?Order
    {
        $phone = Phone::normalize($customerPhone);

        if (! $phone) {
            return null;
        }

        return Order::open()->where('customer_phone', $phone)->orderBy('created_at')->orderBy('id')->first();
    }

    /**
     * A customer pays once, so a repeat order goes onto their open tab. Only when they have no open
     * tab is a new order created.
     *
     * @param  array<int, int>  $items  product id => quantity
     * @return array{0: Order, 1: bool} the order, and whether the items were added to an existing tab
     *
     * @throws \DomainException
     */
    public function placeOrAddToTab(User $staff, string $customerName, string $customerPhone, array $items, ?string $notes = null): array
    {
        $phone = Phone::normalize($customerPhone);

        if (! $phone) {
            throw new \DomainException('Enter a valid Ghana mobile number for the customer.');
        }

        $lock = $this->customerLock($phone);

        try {
            $tab = $this->findOpenTab($phone);

            if (! $tab) {
                return [$this->place($staff, $customerName, $phone, $items, $notes), false];
            }

            if (! $tab->canBeManagedBy($staff)) {
                // Waiters only see their own orders, so nothing about the other waiter's tab is revealed.
                throw new \DomainException('This customer already has an open tab with another waiter, so you cannot add to it here. Ask a manager or an admin to take the order.');
            }

            return [$this->addItems($tab, $staff, $items, $notes), true];
        } finally {
            try {
                $lock?->release();
            } catch (\Throwable) {
                // The lock expires on its own after 10 seconds.
            }
        }
    }

    /**
     * Add more items to an open order. The order goes back to the bartender's queue.
     *
     * @param  array<int, int>  $items  product id => quantity
     *
     * @throws \DomainException
     */
    public function addItems(Order $order, User $staff, array $items, ?string $notes = null): Order
    {
        $lines = $this->priceItems($items);
        $notes = $notes !== null ? trim($notes) : null;

        return $this->locked($order, function (Order $order) use ($staff, $lines, $notes) {
            $this->assertCanManage($order, $staff, 'add items to it');
            $this->assertOpen($order);
            $this->assertNoActivePayment($order);

            // Same drink again while it is still waiting: bump the quantity instead of a second row.
            foreach ($lines as $line) {
                $existing = $order->items()
                    ->where('status', OrderItem::STATUS_PENDING)
                    ->where('product_id', $line['product_id'])
                    ->where('unit_price', $line['unit_price'])
                    ->first();

                if ($existing) {
                    $quantity = $existing->quantity + $line['quantity'];
                    $this->assertQuantity($quantity);
                    $existing->update(['quantity' => $quantity, 'line_total' => round($line['unit_price'] * $quantity, 2)]);
                } else {
                    $this->insertItems($order, [$line], $staff);
                }
            }

            $order->update([
                'status' => Order::STATUS_PENDING,
                'served_at' => null,
                'notes' => $notes ? trim(($order->notes ? $order->notes."\n" : '').$notes) : $order->notes,
            ]);
            $this->recalculate($order);

            $this->log($staff, 'order_add_items', "Added items to {$order->code}", $order, [
                'items' => collect($lines)->map(fn ($l) => $l['product_name'].' x'.$l['quantity'])->all(),
                'total' => (float) $order->total,
            ]);

            return $order;
        });
    }

    /**
     * Change the quantity of an item that is still waiting to be prepared. Zero removes it.
     *
     * @throws \DomainException
     */
    public function setItemQuantity(Order $order, User $staff, int $itemId, int $quantity): Order
    {
        return $this->locked($order, function (Order $order) use ($staff, $itemId, $quantity) {
            $this->assertCanManage($order, $staff, 'change it');
            $this->assertOpen($order);
            $this->assertNoActivePayment($order);

            $item = $order->items()->whereKey($itemId)->first();

            if (! $item) {
                throw new \DomainException('That item is no longer on the order.');
            }

            if ($item->isLocked()) {
                throw new \DomainException('This item has already been prepared and can no longer be changed.');
            }

            if ($quantity > 0) {
                $this->assertQuantity($quantity);
                $item->update(['quantity' => $quantity, 'line_total' => round((float) $item->unit_price * $quantity, 2)]);
            } else {
                if ($order->items()->count() <= 1) {
                    throw new \DomainException('An order needs at least one item. Cancel the order instead.');
                }

                $item->delete();
            }

            $this->syncStatus($order);
            $this->recalculate($order);

            $this->log($staff, 'order_edit_item', "Changed {$item->product_name} on {$order->code}", $order, [
                'item' => $item->product_name,
                'quantity' => $quantity,
                'total' => (float) $order->total,
            ]);

            return $order;
        });
    }

    /** Bartender: everything waiting on the order has been prepared. */
    public function markReady(Order $order, User $staff): Order
    {
        return $this->locked($order, function (Order $order) use ($staff) {
            if ($order->status !== Order::STATUS_PENDING) {
                throw new \DomainException('This order is not waiting to be prepared.');
            }

            $order->items()->where('status', OrderItem::STATUS_PENDING)->update([
                'status' => OrderItem::STATUS_READY,
                'ready_at' => now(),
            ]);

            $order->update(['status' => Order::STATUS_READY, 'ready_at' => now(), 'ready_by' => $staff->id]);

            $this->log($staff, 'order_ready', "{$order->code} marked ready", $order);

            return $order;
        });
    }

    /** Waiter: the order has been taken to the table; the tab stays open until paid. */
    public function serve(Order $order, User $staff): Order
    {
        return $this->locked($order, function (Order $order) use ($staff) {
            if ($order->status !== Order::STATUS_READY) {
                throw new \DomainException('Only a ready order can be served.');
            }

            $order->update(['status' => Order::STATUS_SERVED, 'served_at' => now()]);

            $this->log($staff, 'order_served', "{$order->code} served", $order);

            return $order;
        });
    }

    /**
     * Close the tab, paid in cash. Mobile money goes through the payment gateway instead and is
     * completed by completeMobileMoneyPayment().
     *
     * @throws \DomainException
     */
    public function closeWithCash(Order $order, User $staff): Order
    {
        return $this->locked($order, function (Order $order) use ($staff) {
            $this->assertCanManage($order, $staff);

            if (! Order::canTakeCash($staff)) {
                throw new \DomainException('Only a manager or an admin can close a tab with cash. Use mobile money, or ask a manager.');
            }

            $this->assertNoActivePayment($order);

            return $this->settle($order, $staff, 'cash');
        });
    }

    /**
     * Cancel an order that has not been paid.
     *
     * @throws \DomainException
     */
    public function cancel(Order $order, User $staff, string $reason): Order
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \DomainException('Please give a reason for cancelling the order.');
        }

        return $this->locked($order, function (Order $order) use ($staff, $reason) {
            $this->assertOpen($order);
            $this->assertNoActivePayment($order);

            if ($order->isPaid()) {
                throw new \DomainException('A paid order cannot be cancelled.');
            }

            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $staff->id,
                'cancel_reason' => $reason,
            ]);

            $this->log($staff, 'order_cancel', "Cancelled {$order->code}", $order, ['reason' => $reason, 'total' => (float) $order->total]);

            return $order;
        });
    }

    /**
     * Open orders grouped for the board. Plain arrays, so they are cheap to cache and to poll.
     *
     * @return array{pending: array, ready: array, served: array}
     */
    public function board(): array
    {
        try {
            return $this->cache()->remember(self::BOARD_CACHE_KEY, config('orders.board_cache_seconds', 5), fn () => $this->buildBoard());
        } catch (\Throwable $e) {
            Log::warning('Order board cache unavailable, reading from the database.', ['error' => $e->getMessage()]);

            return $this->buildBoard();
        }
    }

    public function flushBoard(): void
    {
        try {
            $this->cache()->forget(self::BOARD_CACHE_KEY);
        } catch (\Throwable $e) {
            Log::warning('Could not clear the order board cache.', ['error' => $e->getMessage()]);
        }
    }

    /** Unpaid, still-open tabs: the "pending debt" list. */
    public function unpaidTabs()
    {
        return Order::unpaid()->with('items')->orderBy('created_at');
    }

    /**
     * Shared by cash settlement and (later) a confirmed mobile-money payment.
     *
     * @throws \DomainException
     */
    public function settle(Order $order, User $staff, string $method): Order
    {
        $this->assertOpen($order);

        if ($order->isPaid()) {
            throw new \DomainException('This order has already been paid.');
        }

        if ($order->status === Order::STATUS_PENDING) {
            throw new \DomainException('The bartender has not marked this order ready yet. Close the tab once it is ready.');
        }

        $order->update([
            'status' => Order::STATUS_CLOSED,
            'payment_status' => Order::PAYMENT_PAID,
            'payment_method' => $method,
            'paid_at' => now(),
            'closed_at' => now(),
            'closed_by' => $staff->id,
            'closed_by_name' => $staff->name ?? 'Unknown',
        ]);

        $this->log($staff, 'order_paid', "{$order->code} paid by {$method}: ₵".number_format((float) $order->total, 2), $order, [
            'payment_method' => $method,
            'total' => (float) $order->total,
        ]);

        return $order;
    }

    /**
     * Only admins, managers and the waiter who created the order may add to, edit or close it.
     *
     * @throws \DomainException
     */
    public function assertCanManage(Order $order, User $staff, string $action = 'close it'): void
    {
        if (! $order->canBeManagedBy($staff)) {
            throw new \DomainException("Only the waiter who took this order, a manager or an admin can {$action}.");
        }
    }

    // ------------------------------------------------------------------ internals

    /**
     * @param  array<int, int>  $items
     * @return list<array{product_id:int, product_name:string, unit_price:float, price_event_id:?int, quantity:int}>
     */
    private function priceItems(array $items): array
    {
        $quantities = [];

        foreach ($items as $productId => $quantity) {
            $quantity = (int) $quantity;

            if ($quantity > 0) {
                $this->assertQuantity($quantity);
                $quantities[(int) $productId] = $quantity;
            }
        }

        if ($quantities === []) {
            throw new \DomainException('Add at least one item to the order.');
        }

        $products = Product::whereIn('id', array_keys($quantities))->where('is_active', true)->get()->keyBy('id');

        // The price is the normal one, or the VIP price while a price day is running.
        $quotes = app(PriceService::class)->quotesNow($products);

        $lines = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);

            if (! $product) {
                throw new \DomainException('One of the items is no longer available.');
            }

            if ((float) $product->selling_price <= 0) {
                throw new \DomainException("{$product->name} has no selling price yet.");
            }

            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price' => $quotes[$productId]['price'],
                'price_event_id' => $quotes[$productId]['event']?->id,
                'quantity' => $quantity,
            ];
        }

        return $lines;
    }

    private function insertItems(Order $order, array $lines, User $staff): void
    {
        foreach ($lines as $line) {
            $order->items()->create([
                'product_id' => $line['product_id'],
                'product_name' => $line['product_name'],
                'unit_price' => $line['unit_price'],
                'price_event_id' => $line['price_event_id'] ?? null,
                'quantity' => $line['quantity'],
                'line_total' => round($line['unit_price'] * $line['quantity'], 2),
                'status' => OrderItem::STATUS_PENDING,
                'added_by' => $staff->id,
                'added_by_name' => $staff->name ?? 'Unknown',
            ]);
        }
    }

    /** The total is always the sum of the item rows, never a number sent from the browser. */
    private function recalculate(Order $order): void
    {
        $order->update(['total' => round((float) $order->items()->sum('line_total'), 2)]);
    }

    /** After items change, make the order's status match what is left to prepare. */
    private function syncStatus(Order $order): void
    {
        if ($order->items()->where('status', OrderItem::STATUS_PENDING)->exists()) {
            $order->update(['status' => Order::STATUS_PENDING]);
        } elseif ($order->status === Order::STATUS_PENDING) {
            $order->update(['status' => Order::STATUS_READY, 'ready_at' => $order->ready_at ?? now()]);
        }
    }

    private function assertOpen(Order $order): void
    {
        if (! $order->isOpen()) {
            throw new \DomainException('This order is already '.$order->status.'.');
        }
    }

    /** While a mobile-money prompt is out, the total must not change and the tab must not be settled another way. */
    private function assertNoActivePayment(Order $order): void
    {
        if ($order->payments()->active()->exists()) {
            throw new \DomainException('A mobile money payment is waiting for the customer to approve. Wait for it to finish, or cancel that payment attempt first.');
        }
    }

    private function assertQuantity(int $quantity): void
    {
        if ($quantity > self::MAX_QUANTITY) {
            throw new \DomainException('That quantity is too large. The most you can add for one item is '.self::MAX_QUANTITY.'.');
        }
    }

    /**
     * Run a change on a fresh, row-locked copy of the order, under a short Redis lock.
     */
    public function locked(Order $order, \Closure $callback): Order
    {
        $lock = null;

        try {
            $lock = $this->cache()->lock('order:'.$order->id, config('orders.lock_seconds', 10));
            $lock->block($this->lockWaitSeconds);
        } catch (LockTimeoutException) {
            throw new \DomainException('Someone else is updating this order. Please try again in a moment.');
        } catch (\Throwable $e) {
            // Redis (or the lock store) is down: carry on, the database row lock still protects the order.
            Log::warning('Order lock unavailable, relying on the database lock.', ['error' => $e->getMessage()]);
            $lock = null;
        }

        try {
            $result = DB::transaction(function () use ($order, $callback) {
                $fresh = Order::lockForUpdate()->findOrFail($order->id);

                return $callback($fresh);
            });
        } finally {
            try {
                $lock?->release();
            } catch (\Throwable) {
                // The lock expires on its own after 10 seconds.
            }
        }

        $this->flushBoard();

        return $result->load('items');
    }

    /**
     * Serialises "has this customer got an open tab?" so two waiters ordering for the same customer
     * at once cannot create two tabs. Null when Redis is down: same posture as locked().
     */
    private function customerLock(string $phone): ?\Illuminate\Contracts\Cache\Lock
    {
        try {
            $lock = $this->cache()->lock('order-customer:'.$phone, config('orders.lock_seconds', 10));
            $lock->block($this->lockWaitSeconds);

            return $lock;
        } catch (LockTimeoutException) {
            throw new \DomainException('Someone else is placing an order for this customer. Please try again in a moment.');
        } catch (\Throwable $e) {
            Log::warning('Customer lock unavailable, carrying on without it.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** The store behind the locks and the board cache: Redis in production (config/orders.php). */
    private function cache(): Repository
    {
        return Cache::store(config('orders.cache_store'));
    }

    private function buildBoard(): array
    {
        $board = ['pending' => [], 'ready' => [], 'served' => []];

        $orders = Order::open()->with('items')->orderBy('created_at')->orderBy('id')->get();
        $awaitingPayment = OrderPayment::active()->whereIn('order_id', $orders->pluck('id'))->pluck('order_id')->flip();

        $orders->each(function (Order $order) use (&$board, $awaitingPayment) {
            $hasReadyItems = $order->items->contains(fn ($i) => $i->isLocked());

            $board[$order->status][] = [
                'id' => $order->id,
                'code' => $order->code,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'phone_masked' => Phone::mask($order->customer_phone),
                'status' => $order->status,
                'total' => (float) $order->total,
                'taken_by' => $order->taken_by !== null ? (int) $order->taken_by : null,
                'taken_by_name' => $order->taken_by_name,
                'created_at' => $order->created_at->toIso8601String(),
                'is_new_round' => $order->status === Order::STATUS_PENDING && $hasReadyItems,
                'payment_pending' => $awaitingPayment->has($order->id),
                'items' => $order->items->map(fn (OrderItem $i) => [
                    'id' => $i->id,
                    'name' => $i->product_name,
                    'quantity' => $i->quantity,
                    'status' => $i->status,
                    'line_total' => (float) $i->line_total,
                ])->all(),
            ];
        });

        return $board;
    }

    private function log(User $staff, string $action, string $description, Order $order, array $extra = []): void
    {
        ActivityLogs::create([
            'user_id' => $staff->id,
            'action_type' => $action,
            'description' => $description,
            'entity_type' => 'order',
            'entity_id' => $order->id,
            'metadata' => array_merge(['code' => $order->code, 'timestamp' => now()], $extra),
        ]);
    }
}

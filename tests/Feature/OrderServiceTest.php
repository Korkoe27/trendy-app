<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orders;

    private User $waiter;

    private User $bartender;

    private int $beer;

    private int $water;

    private int $jollof;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = app(OrderService::class);

        $roleId = DB::table('roles')->insertGetId(['name' => 'Staff', 'created_at' => now(), 'updated_at' => now()]);

        // Waiters hold orders -> create; only the waiter who took an order (or a manager) may add to or close it.
        DB::table('permissions')->insert([
            'role_id' => $roleId, 'module_id' => DB::table('modules')->where('name', 'orders')->value('id'),
            'can_view' => true, 'can_create' => true, 'can_modify' => false, 'can_delete' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $categoryId = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);

        $this->beer = $this->product($categoryId, 'Club Beer (Large)', 30);
        $this->water = $this->product($categoryId, 'Voltic Water', 5);
        $this->jollof = $this->product($categoryId, 'Jollof Rice', 75);

        $this->waiter = User::forceCreate(['name' => 'Garnet', 'username' => 'garnet', 'phone' => '0241111111', 'role_id' => $roleId, 'password' => '482913', 'must_change_password' => false]);
        $this->bartender = User::forceCreate(['name' => 'Akua', 'username' => 'akua', 'phone' => '0242222222', 'role_id' => $roleId, 'password' => '482913', 'must_change_password' => false]);
    }

    private function product(int $categoryId, string $name, float $price, bool $active = true): int
    {
        return DB::table('products')->insertGetId([
            'name' => $name, 'category_id' => $categoryId, 'selling_price' => $price,
            'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function place(?array $items = null): Order
    {
        return $this->orders->place($this->waiter, 'Kwame Mensah', '024 123 4567', $items ?? [$this->beer => 2, $this->jollof => 1]);
    }

    // ---------------------------------------------------------------- repeat customers

    public function test_a_repeat_order_goes_onto_the_open_tab_whatever_the_phone_format(): void
    {
        $first = $this->place([$this->beer => 2]); // 024 123 4567 => 60

        foreach (['0241234567', '+233 24 123 4567', '233241234567'] as $format) {
            [$order, $merged] = $this->orders->placeOrAddToTab($this->waiter, 'Someone Else', $format, [$this->water => 1]);

            $this->assertTrue($merged, $format);
            $this->assertSame($first->id, $order->id, $format);
        }

        $this->assertSame(1, Order::count());
        $this->assertSame('75.00', $first->fresh()->total); // 60 + 3 x 5
        $this->assertSame('Kwame Mensah', $first->fresh()->customer_name, 'The tab keeps its original name');
    }

    public function test_a_customer_with_no_open_tab_gets_a_new_order(): void
    {
        $this->place();

        [$other, $merged] = $this->orders->placeOrAddToTab($this->waiter, 'Ama', '0249999999', [$this->beer => 1]);
        $this->assertFalse($merged);
        $this->assertSame('Ama', $other->customer_name);

        [$fresh, $merged] = $this->orders->placeOrAddToTab($this->waiter, 'New', '0248888888', [$this->beer => 1], 'No ice');
        $this->assertFalse($merged);
        $this->assertSame('No ice', $fresh->notes);
        $this->assertSame(3, Order::count());
    }

    public function test_a_paid_or_cancelled_order_does_not_attract_new_items(): void
    {
        $paid = $this->orders->closeWithCash($this->orders->markReady($this->place(), $this->bartender), $this->waiter);
        $this->assertSame('closed', $paid->status);

        [$second, $merged] = $this->orders->placeOrAddToTab($this->waiter, 'Kwame Mensah', '0241234567', [$this->beer => 1]);
        $this->assertFalse($merged);
        $this->assertNotSame($paid->id, $second->id);

        $this->orders->cancel($second, $this->waiter, 'Walked out');

        [$third, $merged] = $this->orders->placeOrAddToTab($this->waiter, 'Kwame Mensah', '0241234567', [$this->beer => 1]);
        $this->assertFalse($merged);
        $this->assertNotSame($second->id, $third->id);
        $this->assertSame('135.00', $paid->fresh()->total, 'The paid order is untouched');
    }

    public function test_a_repeat_order_on_a_served_tab_goes_back_to_the_bartender_and_notes_are_appended(): void
    {
        $order = $this->place([$this->beer => 1]);
        $this->orders->markReady($order, $this->bartender);
        $this->orders->serve($order, $this->waiter);
        $order->update(['notes' => 'Table 4']);

        [$merged, $wasMerged] = $this->orders->placeOrAddToTab($this->waiter, 'Kwame Mensah', '0241234567', [$this->beer => 1], 'Extra cold');

        $this->assertTrue($wasMerged);
        $this->assertSame('pending', $merged->status);
        $this->assertNull($merged->served_at);
        $this->assertSame("Table 4\nExtra cold", $merged->notes);
        $this->assertCount(2, $merged->items, 'The served beer is locked, so the new beer is its own row');
    }

    public function test_the_oldest_open_tab_wins_when_a_customer_somehow_has_two(): void
    {
        $oldest = $this->place([$this->beer => 1]);
        $this->place([$this->water => 1]); // place() always creates a new order

        $this->assertSame($oldest->id, $this->orders->findOpenTab('0241234567')->id);

        [$order] = $this->orders->placeOrAddToTab($this->waiter, 'Kwame Mensah', '0241234567', [$this->jollof => 1]);
        $this->assertSame($oldest->id, $order->id);
    }

    public function test_a_repeat_order_is_validated_like_a_new_one(): void
    {
        $order = $this->place();

        foreach ([['nonsense', [$this->beer => 1], 'valid Ghana mobile'], ['0241234567', [], 'at least one item'], ['0241234567', [$this->beer => 999], 'too large']] as [$phone, $items, $message]) {
            try {
                $this->orders->placeOrAddToTab($this->waiter, 'Kwame Mensah', $phone, $items);
                $this->fail('Expected a DomainException');
            } catch (\DomainException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }

        $this->assertSame('135.00', $order->fresh()->total, 'A rejected order must not change the tab');
    }

    public function test_a_held_customer_lock_makes_the_second_waiter_wait_politely(): void
    {
        $lock = Cache::lock('order-customer:+233241234567', 10);
        $this->assertTrue($lock->get());

        try {
            (new OrderService(lockWaitSeconds: 1))->placeOrAddToTab($this->waiter, 'Kwame Mensah', '0241234567', [$this->beer => 1]);
            $this->fail('Expected the customer to be busy');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Someone else is placing an order', $e->getMessage());
        } finally {
            $lock->release();
        }

        $this->assertSame(0, Order::count());
    }

    // ---------------------------------------------------------------- placing

    public function test_placing_an_order_snapshots_prices_and_computes_the_total_on_the_server(): void
    {
        $order = $this->place();

        $this->assertSame('ORD-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT), $order->code);
        $this->assertSame('pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('+233241234567', $order->customer_phone);
        $this->assertSame('Garnet', $order->taken_by_name);
        $this->assertSame('135.00', $order->total); // 2 x 30 + 75
        $this->assertCount(2, $order->items);
        $this->assertTrue($order->items->every(fn ($i) => $i->status === 'pending'));

        $this->assertDatabaseHas('activity_logs', ['user_id' => $this->waiter->id, 'action_type' => 'order_create', 'entity_type' => 'order', 'entity_id' => $order->id]);
    }

    public function test_later_price_changes_do_not_rewrite_an_old_order(): void
    {
        $order = $this->place();

        DB::table('products')->where('id', $this->beer)->update(['selling_price' => 99, 'name' => 'Renamed']);

        $order->refresh();
        $this->assertSame('135.00', $order->total);
        $this->assertSame('Club Beer (Large)', $order->items->first()->product_name);
        $this->assertSame('30.00', $order->items->first()->unit_price);
    }

    public function test_placing_validates_customer_and_items(): void
    {
        $inactive = $this->product(DB::table('categories')->value('id'), 'Old Drink', 10, false);
        $free = $this->product(DB::table('categories')->value('id'), 'Freebie', 0);

        $cases = [
            'no name' => [fn () => $this->orders->place($this->waiter, '  ', '0241234567', [$this->beer => 1]), 'Enter a name'],
            'bad phone' => [fn () => $this->orders->place($this->waiter, 'Kwame', '12345', [$this->beer => 1]), 'valid Ghana mobile number'],
            'no items' => [fn () => $this->orders->place($this->waiter, 'Kwame', '0241234567', []), 'at least one item'],
            'zero quantities' => [fn () => $this->orders->place($this->waiter, 'Kwame', '0241234567', [$this->beer => 0]), 'at least one item'],
            'inactive product' => [fn () => $this->orders->place($this->waiter, 'Kwame', '0241234567', [$inactive => 1]), 'no longer available'],
            'unknown product' => [fn () => $this->orders->place($this->waiter, 'Kwame', '0241234567', [999999 => 1]), 'no longer available'],
            'no price' => [fn () => $this->orders->place($this->waiter, 'Kwame', '0241234567', [$free => 1]), 'no selling price'],
            'huge quantity' => [fn () => $this->orders->place($this->waiter, 'Kwame', '0241234567', [$this->beer => 5000]), 'too large'],
        ];

        foreach ($cases as $label => [$call, $message]) {
            try {
                $call();
                $this->fail("Expected a DomainException for: {$label}");
            } catch (\DomainException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $label);
            }
        }

        $this->assertSame(0, Order::count(), 'A rejected order must not leave anything behind');
    }

    public function test_orders_never_touch_stock(): void
    {
        DB::table('stocks')->insert(['product_id' => $this->beer, 'total_units' => 100, 'created_at' => now(), 'updated_at' => now()]);
        $movements = DB::table('stock_movements')->count();

        $order = $this->place();
        $this->orders->markReady($order, $this->bartender);
        $this->orders->closeWithCash($order, $this->waiter);

        $this->assertSame(1, DB::table('stocks')->count());
        $this->assertSame(100, (int) DB::table('stocks')->value('total_units'));
        $this->assertSame($movements, DB::table('stock_movements')->count());
    }

    // ---------------------------------------------------------------- kitchen flow

    public function test_bartender_marks_an_order_ready_and_its_items_lock(): void
    {
        $order = $this->place();

        $order = $this->orders->markReady($order, $this->bartender);

        $this->assertSame('ready', $order->status);
        $this->assertSame($this->bartender->id, $order->ready_by);
        $this->assertNotNull($order->ready_at);
        $this->assertTrue($order->items->every(fn ($i) => $i->status === 'ready' && $i->isLocked()));
    }

    public function test_an_order_can_only_be_marked_ready_once(): void
    {
        $order = $this->orders->markReady($this->place(), $this->bartender);

        $this->expectException(\DomainException::class);
        $this->orders->markReady($order, $this->bartender);
    }

    public function test_serving_requires_a_ready_order(): void
    {
        $order = $this->place();

        try {
            $this->orders->serve($order, $this->waiter);
            $this->fail('A pending order cannot be served');
        } catch (\DomainException) {
        }

        $this->orders->markReady($order, $this->bartender);
        $served = $this->orders->serve($order, $this->waiter);

        $this->assertSame('served', $served->status);
        $this->assertNotNull($served->served_at);
    }

    public function test_adding_items_sends_the_order_back_to_the_bartender_and_keeps_old_items_locked(): void
    {
        $order = $this->place();
        $this->orders->markReady($order, $this->bartender);
        $this->orders->serve($order, $this->waiter);

        $order = $this->orders->addItems($order, $this->waiter, [$this->water => 3, $this->beer => 1]);

        $this->assertSame('pending', $order->status);
        $this->assertNull($order->served_at);
        $this->assertSame('180.00', $order->total); // 135 + 15 + 30

        $ready = $order->items->where('status', 'ready');
        $pending = $order->items->where('status', 'pending');

        $this->assertCount(2, $ready);   // the original beer + jollof stay as they were
        $this->assertCount(2, $pending); // new water and a new beer line (the old beer is locked)
        $this->assertSame(2, $ready->firstWhere('product_id', $this->beer)->quantity);
    }

    public function test_adding_the_same_drink_twice_while_waiting_merges_the_quantity(): void
    {
        $order = $this->place([$this->beer => 1]);

        $order = $this->orders->addItems($order, $this->waiter, [$this->beer => 2]);

        $this->assertCount(1, $order->items);
        $this->assertSame(3, $order->items->first()->quantity);
        $this->assertSame('90.00', $order->total);
    }

    public function test_only_pending_items_can_be_edited_or_removed(): void
    {
        $order = $this->place();
        $beer = $order->items->firstWhere('product_id', $this->beer);

        $order = $this->orders->setItemQuantity($order, $this->waiter, $beer->id, 4);
        $this->assertSame('195.00', $order->total); // 4 x 30 + 75

        $order = $this->orders->setItemQuantity($order, $this->waiter, $beer->id, 0);
        $this->assertCount(1, $order->items);
        $this->assertSame('75.00', $order->total);

        $this->orders->markReady($order, $this->bartender);
        $remaining = $order->items->first();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already been prepared');
        $this->orders->setItemQuantity($order, $this->waiter, $remaining->id, 3);
    }

    public function test_the_last_item_cannot_be_removed(): void
    {
        $order = $this->place([$this->beer => 1]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('at least one item');
        $this->orders->setItemQuantity($order, $this->waiter, $order->items->first()->id, 0);
    }

    public function test_removing_the_only_waiting_item_returns_the_order_to_ready(): void
    {
        $order = $this->place([$this->beer => 1]);
        $this->orders->markReady($order, $this->bartender);

        $order = $this->orders->addItems($order, $this->waiter, [$this->water => 1]);
        $this->assertSame('pending', $order->status);

        $waiting = $order->items->firstWhere('status', 'pending');
        $order = $this->orders->setItemQuantity($order, $this->waiter, $waiting->id, 0);

        $this->assertSame('ready', $order->status);
        $this->assertSame('30.00', $order->total);
    }

    // ---------------------------------------------------------------- payment

    public function test_cash_payment_closes_the_tab(): void
    {
        $order = $this->place();
        $this->orders->markReady($order, $this->bartender);
        $this->orders->serve($order, $this->waiter);

        $order = $this->orders->closeWithCash($order, $this->waiter);

        $this->assertSame('closed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('cash', $order->payment_method);
        $this->assertSame('Garnet', $order->closed_by_name);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->closed_at);

        $this->assertDatabaseHas('activity_logs', ['action_type' => 'order_paid', 'entity_id' => $order->id]);
    }

    public function test_a_ready_order_can_be_paid_without_being_served_first(): void
    {
        $order = $this->place();
        $this->orders->markReady($order, $this->bartender);

        $this->assertSame('closed', $this->orders->closeWithCash($order, $this->waiter)->status);
    }

    public function test_an_order_still_waiting_for_the_bartender_cannot_be_closed(): void
    {
        $order = $this->place();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not marked this order ready');
        $this->orders->closeWithCash($order, $this->waiter);
    }

    public function test_a_tab_cannot_be_paid_twice(): void
    {
        $order = $this->place();
        $this->orders->markReady($order, $this->bartender);
        $this->orders->closeWithCash($order, $this->waiter);

        $this->expectException(\DomainException::class);
        $this->orders->closeWithCash($order, $this->waiter);

        $this->assertSame(1, DB::table('activity_logs')->where('action_type', 'order_paid')->count());
    }

    public function test_closed_orders_cannot_get_more_items(): void
    {
        $order = $this->place();
        $this->orders->markReady($order, $this->bartender);
        $this->orders->closeWithCash($order, $this->waiter);

        $this->expectException(\DomainException::class);
        $this->orders->addItems($order, $this->waiter, [$this->water => 1]);
    }

    public function test_cancelling_needs_a_reason_and_only_works_on_open_orders(): void
    {
        $order = $this->place();

        try {
            $this->orders->cancel($order, $this->waiter, '  ');
            $this->fail('A reason is required');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('reason', $e->getMessage());
        }

        $cancelled = $this->orders->cancel($order, $this->waiter, 'Customer left');
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('Customer left', $cancelled->cancel_reason);

        $this->expectException(\DomainException::class);
        $this->orders->markReady($cancelled, $this->bartender);
    }

    public function test_a_paid_order_cannot_be_cancelled(): void
    {
        $order = $this->place();
        $this->orders->markReady($order, $this->bartender);
        $this->orders->closeWithCash($order, $this->waiter);

        $this->expectException(\DomainException::class);
        $this->orders->cancel($order, $this->waiter, 'Oops');
    }

    // ---------------------------------------------------------------- board, locks, debts

    public function test_board_groups_open_orders_by_status(): void
    {
        $pending = $this->place();
        $ready = $this->orders->markReady($this->place(), $this->bartender);
        $served = $this->place();
        $this->orders->markReady($served, $this->bartender);
        $this->orders->serve($served, $this->waiter);
        $closed = $this->place();
        $this->orders->markReady($closed, $this->bartender);
        $this->orders->closeWithCash($closed, $this->waiter);

        $board = $this->orders->board();

        $this->assertSame([$pending->id], array_column($board['pending'], 'id'));
        $this->assertSame([$ready->id], array_column($board['ready'], 'id'));
        $this->assertSame([$served->id], array_column($board['served'], 'id'));
        $this->assertSame('024 •• 4567', $board['pending'][0]['phone_masked']);
        $this->assertCount(2, $board['pending'][0]['items']);
    }

    public function test_board_is_cached_briefly_and_cleared_by_every_change(): void
    {
        $order = $this->place();
        $this->assertCount(1, $this->orders->board()['pending']);

        // A row inserted behind the service's back is not seen until the cache is cleared...
        DB::table('orders')->insert([
            'code' => 'ORD-9999', 'customer_name' => 'Ghost', 'customer_phone' => '+233240000000', 'status' => 'pending',
            'payment_status' => 'unpaid', 'total' => 10, 'taken_by_name' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertCount(1, $this->orders->board()['pending']);

        // ...and every service change clears it.
        $this->orders->markReady($order, $this->bartender);
        $board = $this->orders->board();
        $this->assertCount(1, $board['pending']);
        $this->assertCount(1, $board['ready']);
    }

    public function test_a_new_round_on_a_served_tab_is_flagged_on_the_board(): void
    {
        $order = $this->place([$this->beer => 1]);
        $this->orders->markReady($order, $this->bartender);
        $this->orders->serve($order, $this->waiter);
        $this->orders->addItems($order, $this->waiter, [$this->water => 1]);

        $this->assertTrue($this->orders->board()['pending'][0]['is_new_round']);
    }

    public function test_unpaid_tabs_lists_only_open_unpaid_orders(): void
    {
        $open = $this->orders->markReady($this->place(), $this->bartender);
        $paid = $this->place();
        $this->orders->markReady($paid, $this->bartender);
        $this->orders->closeWithCash($paid, $this->waiter);
        $this->orders->cancel($this->place(), $this->waiter, 'Left');

        $this->assertSame([$open->id], $this->orders->unpaidTabs()->pluck('id')->all());
    }

    public function test_a_held_lock_makes_the_second_request_fail_politely(): void
    {
        $order = $this->orders->markReady($this->place(), $this->bartender);

        $lock = Cache::lock('order:'.$order->id, 10);
        $this->assertTrue($lock->get());

        try {
            (new OrderService(lockWaitSeconds: 1))->closeWithCash($order, $this->waiter);
            $this->fail('Expected the order to be busy');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Someone else is updating', $e->getMessage());
        } finally {
            $lock->release();
        }

        $this->assertSame('ready', $order->fresh()->status, 'The blocked request must not have changed the order');
    }

    public function test_orders_still_work_when_the_cache_store_is_down(): void
    {
        Cache::extend('failing', fn () => Cache::repository(new class extends ArrayStore
        {
            public function get($key)
            {
                throw new \RuntimeException('Connection refused');
            }

            public function put($key, $value, $seconds)
            {
                throw new \RuntimeException('Connection refused');
            }

            public function forget($key)
            {
                throw new \RuntimeException('Connection refused');
            }

            public function lock($name, $seconds = 0, $owner = null)
            {
                throw new \RuntimeException('Connection refused');
            }
        }));

        config(['cache.stores.failing' => ['driver' => 'failing'], 'orders.cache_store' => 'failing']);

        $order = $this->place();
        $order = $this->orders->markReady($order, $this->bartender);
        $order = $this->orders->closeWithCash($order, $this->waiter);

        $this->assertSame('closed', $order->status);
        $this->assertCount(0, $this->orders->board()['pending']); // board falls back to the database
    }

    public function test_the_order_store_can_be_pointed_at_its_own_cache_store(): void
    {
        config(['cache.stores.orders_live' => ['driver' => 'array'], 'orders.cache_store' => 'orders_live']);

        $order = $this->place();
        $this->orders->board();

        $this->assertTrue(Cache::store('orders_live')->has(OrderService::BOARD_CACHE_KEY));
        $this->assertFalse(Cache::store('array')->has(OrderService::BOARD_CACHE_KEY));

        $this->orders->markReady($order, $this->bartender);
        $this->assertFalse(Cache::store('orders_live')->has(OrderService::BOARD_CACHE_KEY), 'A change clears the board cache');
    }
}

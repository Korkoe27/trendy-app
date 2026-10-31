<?php

namespace Tests\Feature;

use App\Livewire\Pages\Orders;
use App\Livewire\Pages\Settings;
use App\Models\BlockedNumber;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** The block list: numbers that cannot place orders. Only the Super Admin manages it. */
class BlockListTest extends TestCase
{
    use RefreshDatabase;

    private int $beer;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = app(OrderService::class);
        $category = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);
        $this->beer = DB::table('products')->insertGetId(['name' => 'Club Beer', 'category_id' => $category, 'selling_price' => 30, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function userIn(string $roleName, array $grants = [], string $name = 'Someone'): User
    {
        // Role names are unique and the orders migration already creates starter roles, so only the
        // Super Admin role keeps its exact name; the others only matter through their permissions.
        $roleId = $roleName === 'Super Admin'
            ? (DB::table('roles')->where('name', 'Super Admin')->value('id') ?? DB::table('roles')->insertGetId(['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]))
            : DB::table('roles')->insertGetId(['name' => $roleName.' '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);

        foreach ($grants as $module => $actions) {
            DB::table('permissions')->insert([
                'role_id' => $roleId, 'module_id' => DB::table('modules')->where('name', $module)->value('id'),
                'can_view' => in_array('view', $actions), 'can_create' => in_array('create', $actions),
                'can_modify' => in_array('modify', $actions), 'can_delete' => in_array('delete', $actions),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return User::forceCreate([
            'name' => $name, 'username' => strtolower($name).uniqid(), 'phone' => '+23324'.random_int(1000000, 9999999),
            'role_id' => $roleId, 'password' => '482913', 'must_change_password' => false,
        ]);
    }

    private function superAdmin(): User
    {
        return $this->userIn('Super Admin', [], 'Boss');
    }

    private function waiter(): User
    {
        return $this->userIn('Waiter', ['orders' => ['view', 'create']], 'Ama');
    }

    public function test_the_super_admin_can_block_and_unblock_a_number(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(Settings::class)
            ->assertSee('Block list')
            ->set('blockPhone', '024 123 4567')
            ->set('blockReason', 'Unpaid tab')
            ->call('blockNumber')
            ->assertHasNoErrors()
            ->assertSee('0241234567')
            ->assertSee('Unpaid tab');

        $entry = BlockedNumber::firstOrFail();
        $this->assertSame('+233241234567', $entry->phone);
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'block_number_add']);

        Livewire::test(Settings::class)->call('unblockNumber', $entry->id);

        $this->assertSame(0, BlockedNumber::count());
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'block_number_remove']);
    }

    public function test_bad_and_duplicate_numbers_are_rejected(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(Settings::class)->set('blockPhone', '12345')->call('blockNumber')->assertHasErrors('blockPhone');
        Livewire::test(Settings::class)->set('blockPhone', '')->call('blockNumber')->assertHasErrors('blockPhone');

        Livewire::test(Settings::class)->set('blockPhone', '0241234567')->call('blockNumber')->assertHasNoErrors();
        Livewire::test(Settings::class)->set('blockPhone', '+233 24 123 4567')->call('blockNumber')->assertHasErrors('blockPhone');

        $this->assertSame(1, BlockedNumber::count());
    }

    public function test_nobody_else_can_see_or_change_the_block_list(): void
    {
        BlockedNumber::create(['phone' => '+233241234567']);

        foreach ([
            $this->userIn('Admin', ['orders' => ['view', 'create', 'modify', 'delete']], 'Admin One'),
            $this->userIn('Manager', ['orders' => ['view'], 'order_debts' => ['view', 'modify']], 'Manager One'),
            $this->waiter(),
        ] as $user) {
            $this->actingAs($user);

            Livewire::test(Settings::class)->assertDontSee('Block list')->assertDontSee('0241234567');
            Livewire::test(Settings::class)->set('blockPhone', '0249999999')->call('blockNumber')->assertForbidden();
            Livewire::test(Settings::class)->call('unblockNumber', BlockedNumber::first()->id)->assertForbidden();
        }

        $this->assertSame(1, BlockedNumber::count());
    }

    public function test_a_blocked_number_cannot_place_an_order_however_it_is_typed(): void
    {
        BlockedNumber::create(['phone' => '+233241234567']);
        $ama = $this->waiter();

        foreach (['0241234567', '024 123 4567', '+233 24 123 4567'] as $typed) {
            try {
                $this->orders->place($ama, 'Kwame', $typed, [$this->beer => 1]);
                $this->fail('A blocked number must not place an order.');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('block list', $e->getMessage());
            }

            try {
                $this->orders->placeOrAddToTab($ama, 'Kwame', $typed, [$this->beer => 1]);
                $this->fail('A blocked number must not place an order.');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('block list', $e->getMessage());
            }
        }

        $this->assertSame(0, DB::table('orders')->count());

        // Another number is fine.
        $this->orders->place($ama, 'Kofi', '0249999999', [$this->beer => 1]);
        $this->assertSame(1, DB::table('orders')->count());
    }

    public function test_blocking_a_number_stops_more_items_on_its_open_tab_but_not_paying_it(): void
    {
        $ama = $this->waiter();
        $order = $this->orders->place($ama, 'Kwame', '0241234567', [$this->beer => 1]);

        BlockedNumber::create(['phone' => '+233241234567']);

        $this->expectException(\DomainException::class);

        try {
            $this->orders->addItems($order, $ama, [$this->beer => 1]);
        } finally {
            $this->assertSame('30.00', $order->fresh()->total);

            // Closing the tab they already owe is still allowed.
            $manager = $this->userIn('Manager', ['order_debts' => ['view', 'modify']], 'Manager One');
            $ready = $this->orders->markReady($order->fresh(), $this->userIn('Bar', ['orders' => ['view', 'modify']], 'Akua'));
            $this->orders->closeWithCash($ready, $manager);
            $this->assertSame('closed', $order->fresh()->status);
        }
    }

    public function test_the_take_order_screen_warns_and_refuses_a_blocked_number(): void
    {
        BlockedNumber::create(['phone' => '+233241234567']);
        $this->actingAs($this->waiter());

        Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->set('customerName', 'Kwame')
            ->set('customerPhone', '024 123 4567')
            ->call('addToCart', $this->beer)
            ->assertSeeHtml('data-testid="blocked-number-notice"')
            ->call('saveOrder')
            ->assertHasErrors('cart');

        $this->assertSame(0, DB::table('orders')->count());

        Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->set('customerPhone', '0249999999')
            ->assertDontSeeHtml('data-testid="blocked-number-notice"');
    }
}

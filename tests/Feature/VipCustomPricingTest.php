<?php

namespace Tests\Feature;

use App\Livewire\Pages\Inventory;
use App\Livewire\Pages\Orders;
use App\Livewire\Pages\Products;
use App\Models\PriceEvent;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PriceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** VIP price days with an exact price per product instead of a percentage. */
class VipCustomPricingTest extends TestCase
{
    use RefreshDatabase;

    private int $beer;

    private int $water;

    private int $jollof;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-28 16:00:00'); // Wednesday
        config(['pricing.day_ends_at' => '05:00']);

        $drinks = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);
        $food = DB::table('categories')->insertGetId(['name' => 'food', 'pricing_model' => 'per_plate', 'created_at' => now(), 'updated_at' => now()]);
        $this->beer = $this->product($drinks, 'Club Beer', 10);
        $this->water = $this->product($drinks, 'Voltic Water', 5);
        $this->jollof = $this->product($food, 'Jollof Rice', 75);

        $this->admin = $this->userWith(['products' => ['view', 'create', 'modify', 'delete'], 'orders' => ['view', 'create', 'modify']], 'Admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function product(int $categoryId, string $name, float $price, bool $active = true): int
    {
        return DB::table('products')->insertGetId([
            'name' => $name, 'category_id' => $categoryId, 'selling_price' => $price, 'units_per_box' => 12,
            'is_active' => $active, 'is_vip' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param array<string, list<string>> $grants module => actions */
    private function userWith(array $grants, string $name): User
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Role '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);

        foreach ($grants as $module => $actions) {
            $moduleId = DB::table('modules')->where('name', $module)->value('id')
                ?? DB::table('modules')->insertGetId(['name' => $module, 'created_at' => now(), 'updated_at' => now()]);

            DB::table('permissions')->insert([
                'role_id' => $roleId, 'module_id' => $moduleId,
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

    private function prices(): PriceService
    {
        return app(PriceService::class);
    }

    private function custom(string $start, ?string $end, array $prices, ?string $name = 'Derby Night'): PriceEvent
    {
        return $this->prices()->scheduleCustom($this->admin, $start, $end ?? $start, $prices, $name);
    }

    private function priceOf(int $productId, string $at): float
    {
        return $this->prices()->quoteNow(Product::find($productId), Carbon::parse($at))['price'];
    }

    // ------------------------------------------------------------------ the price rule

    public function test_a_custom_price_applies_on_the_event_days_only_and_only_to_the_priced_products(): void
    {
        $event = $this->custom('2026-10-30', '2026-10-31', [$this->beer => '15', $this->water => '']);

        $this->assertTrue($event->isCustom());
        $this->assertNull($event->fresh()->percent);

        $this->assertSame(10.0, $this->priceOf($this->beer, '2026-10-29 12:00'));
        $this->assertSame(15.0, $this->priceOf($this->beer, '2026-10-30 12:00'));
        $this->assertSame(15.0, $this->priceOf($this->beer, '2026-10-31 20:00'));
        $this->assertSame(10.0, $this->priceOf($this->beer, '2026-11-01 12:00'), 'back to normal by itself');
        $this->assertSame(5.0, $this->priceOf($this->water, '2026-10-30 12:00'), 'blank means unchanged');

        $this->assertSame(10.0, (float) DB::table('products')->where('id', $this->beer)->value('selling_price'), 'the stored price is never rewritten');
    }

    public function test_custom_prices_can_go_down_and_the_quote_describes_the_change_per_product(): void
    {
        $this->custom('2026-10-30', null, [$this->beer => '12.50', $this->water => '4']);

        $beer = $this->prices()->quoteNow(Product::find($this->beer), Carbon::parse('2026-10-30 12:00'));
        $water = $this->prices()->quoteNow(Product::find($this->water), Carbon::parse('2026-10-30 12:00'));

        $this->assertSame('+25%', $beer['label']);
        $this->assertTrue($beer['increase']);
        $this->assertSame('-20%', $water['label']);
        $this->assertFalse($water['increase']);
    }

    public function test_the_vip_flag_is_not_needed_and_food_products_can_be_priced_too(): void
    {
        $this->assertFalse((bool) Product::find($this->beer)->is_vip);

        $this->custom('2026-10-30', null, [$this->jollof => '90']);

        $this->assertSame(90.0, $this->priceOf($this->jollof, '2026-10-30 12:00'));
    }

    public function test_a_price_equal_to_the_normal_price_is_left_out_and_at_least_one_change_is_needed(): void
    {
        $event = $this->custom('2026-10-30', null, [$this->beer => '10.00', $this->water => '6']);

        $this->assertSame([$this->water], $event->products()->pluck('products.id')->all());

        $this->expectException(\DomainException::class);
        $this->custom('2026-11-05', null, [$this->beer => '10']);
    }

    public function test_scheduling_rules_for_custom_prices(): void
    {
        $cases = [
            'nothing entered' => fn () => $this->custom('2026-10-30', null, [$this->beer => '', $this->water => null]),
            'zero' => fn () => $this->custom('2026-10-30', null, [$this->beer => '0']),
            'negative' => fn () => $this->custom('2026-10-30', null, [$this->beer => '-3']),
            'text' => fn () => $this->custom('2026-10-30', null, [$this->beer => 'abc']),
            'huge' => fn () => $this->custom('2026-10-30', null, [$this->beer => '9999999']),
            'past day' => fn () => $this->custom('2026-10-27', null, [$this->beer => '12']),
            'end before start' => fn () => $this->custom('2026-10-31', '2026-10-30', [$this->beer => '12']),
            'unknown product' => fn () => $this->custom('2026-10-30', null, [99999 => '12']),
        ];

        foreach ($cases as $label => $attempt) {
            try {
                $attempt();
                $this->fail("Expected a refusal: $label");
            } catch (\DomainException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, PriceEvent::count());
    }

    public function test_an_inactive_product_cannot_be_priced(): void
    {
        $old = $this->product(1, 'Old Drink', 8, active: false);

        $this->expectException(\DomainException::class);
        $this->custom('2026-10-30', null, [$old => '12']);
    }

    public function test_custom_and_percentage_events_coexist_on_different_products_and_clash_on_the_same_one(): void
    {
        DB::table('products')->where('id', $this->beer)->update(['is_vip' => true]);
        $this->prices()->schedule($this->admin, '2026-10-30', '2026-10-30', 20, 'Percent');
        $this->custom('2026-10-30', null, [$this->water => '7']);

        $this->assertSame(12.0, $this->priceOf($this->beer, '2026-10-30 12:00'));
        $this->assertSame(7.0, $this->priceOf($this->water, '2026-10-30 12:00'));

        try {
            $this->custom('2026-10-30', null, [$this->beer => '14']); // beer already covered that day
            $this->fail('A second event on the same product and day must be refused.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('overlap', $e->getMessage());
        }
    }

    public function test_cancelling_a_custom_event_restores_normal_prices_at_once_and_is_logged(): void
    {
        $event = $this->custom('2026-10-28', null, [$this->beer => '15']);
        $this->assertSame(15.0, $this->priceOf($this->beer, '2026-10-28 18:00'));

        app(PriceService::class)->cancel($this->admin, $event);

        $this->assertSame(10.0, $this->priceOf($this->beer, '2026-10-28 18:00'));
        $log = DB::table('activity_logs')->where('action_type', 'price_event_create')->first();
        $this->assertNotNull($log);
        $this->assertArrayHasKey('custom_prices', json_decode($log->metadata, true));
    }

    // ------------------------------------------------------------------ orders and stock taking

    public function test_an_order_uses_the_custom_price_and_later_rounds_follow_the_price_in_force(): void
    {
        $event = $this->custom('2026-10-28', null, [$this->beer => '15']);
        $orders = app(OrderService::class);

        $order = $orders->place($this->admin, 'Kwame', '0241234567', [$this->beer => 2, $this->water => 1]);

        $beerLine = $order->items->firstWhere('product_id', $this->beer);
        $this->assertSame('15.00', $beerLine->unit_price);
        $this->assertSame('30.00', $beerLine->line_total);
        $this->assertSame($event->id, $beerLine->price_event_id);
        $this->assertSame('35.00', $order->fresh()->total);

        Carbon::setTestNow('2026-10-29 16:00:00');
        $orders->addItems($order, $this->admin, [$this->beer => 1]);

        $this->assertSame(['10.00', '15.00'], $order->fresh()->items->where('product_id', $this->beer)->pluck('unit_price')->sort()->values()->all());
    }

    public function test_the_take_order_screen_shows_the_custom_price_and_its_change(): void
    {
        $this->custom('2026-10-28', null, [$this->beer => '15', $this->water => '4']);
        $this->actingAs($this->admin);

        Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->assertSee('VIP +50%')
            ->assertSee('VIP -20%')
            ->call('addToCart', $this->beer)
            ->call('addToCart', $this->beer)
            ->assertSee('30.00');
    }

    public function test_end_of_day_revenue_and_profit_follow_a_custom_price(): void
    {
        $this->custom('2026-10-28', null, [$this->beer => '8']); // a discount below the normal price
        DB::table('stocks')->insert([
            'product_id' => $this->beer, 'total_units' => 100, 'cost_price' => 6, 'cost_margin' => 4, 'total_cost' => 600,
            'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);
        $this->actingAs($this->admin);

        Carbon::setTestNow('2026-10-29 16:00:00');

        Livewire::test(Inventory::class)
            ->call('openTakeInventoryModal')
            ->set('salesDate', '2026-10-28')
            ->set('cashAmount', '480')
            ->set('momoAmount', '0')
            ->set('hubtelAmount', '0')
            ->set('snooker', '0')
            ->set('foodTotal', '0')
            ->set("productStocks.{$this->beer}.closing_units", '40')
            ->call('submitInventory')
            ->assertHasNoErrors();

        $this->assertSame(480.0, (float) DB::table('daily_sales_summaries')->value('drinks_total'), '60 sold x 8.00');
        $this->assertSame(120.0, (float) DB::table('daily_sales_summaries')->value('total_profit'), '60 x (4.00 margin - 2.00 price change)');
    }

    // ------------------------------------------------------------------ the form

    public function test_the_custom_form_lists_every_active_product_and_schedules_the_prices(): void
    {
        $this->product(1, 'Retired Drink', 3, active: false);
        $this->actingAs($this->admin);

        $listed = Livewire::test(Products::class)
            ->call('openVipModal')
            ->call('setVipMode', 'custom')
            ->viewData('customProducts')->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Club Beer', 'Voltic Water', 'Jollof Rice'], $listed, 'every ACTIVE product, VIP or not');

        $page = Livewire::test(Products::class)
            ->call('openVipModal')
            ->call('setVipMode', 'custom')
            ->assertSee('Club Beer')
            ->assertSee('Voltic Water')
            ->assertSee('Jollof Rice') // food is listed too
            ->set('vipName', 'Derby Night')
            ->set('vipStart', '2026-10-31')
            ->set("customPrices.{$this->beer}", '14.50')
            ->set("customPrices.{$this->water}", '')
            ->call('scheduleVipPrices')
            ->assertHasNoErrors()
            ->assertSet('showVipModal', false);

        $event = PriceEvent::firstOrFail();
        $this->assertTrue($event->isCustom());
        $this->assertSame('Derby Night', $event->name);
        $this->assertSame('2026-10-31', $event->starts_on);
        $this->assertSame(1, $event->products()->count());
        $this->assertEquals(14.5, (float) $event->products()->first()->pivot->custom_price);

        $page->call('openVipModal')->assertSee('Custom prices')->assertSee('Derby Night');
    }

    public function test_the_form_shows_custom_price_errors(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Products::class)
            ->call('openVipModal')
            ->call('setVipMode', 'custom')
            ->call('scheduleVipPrices')
            ->assertHasErrors('customPrices')
            ->set("customPrices.{$this->beer}", 'abc')
            ->call('scheduleVipPrices')
            ->assertHasErrors(['customPrices.'.$this->beer])
            ->set("customPrices.{$this->beer}", '-4')
            ->call('scheduleVipPrices')
            ->assertHasErrors(['customPrices.'.$this->beer]);

        $this->assertSame(0, PriceEvent::count());
    }

    public function test_the_products_page_shows_a_custom_price_beside_the_selling_price(): void
    {
        $this->custom('2026-10-28', null, [$this->beer => '15']);
        $this->actingAs($this->admin);

        Livewire::test(Products::class)->assertSee('VIP GH₵ 15.00 (+50%)');
    }

    public function test_only_roles_that_can_modify_products_can_use_custom_prices(): void
    {
        $this->actingAs($this->userWith(['products' => ['view']], 'Viewer'));

        Livewire::test(Products::class)->call('setVipMode', 'custom')->assertForbidden();
        Livewire::test(Products::class)
            ->set('vipMode', 'custom')
            ->set('vipStart', '2026-10-30')
            ->set('vipEnd', '2026-10-30')
            ->set("customPrices.{$this->beer}", '15')
            ->call('scheduleVipPrices')
            ->assertForbidden();

        $this->assertSame(0, PriceEvent::count());
    }
}

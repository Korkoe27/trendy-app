<?php

namespace Tests\Feature;

use App\Livewire\Pages\Inventory;
use App\Livewire\Pages\Orders;
use App\Livewire\Pages\Products;
use App\Models\Order;
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

/**
 * VIP price days: the VIP list, scheduling a percentage for chosen days, and prices returning to
 * normal afterwards. products.selling_price is never rewritten, so every test also checks that.
 */
class VipPricingTest extends TestCase
{
    use RefreshDatabase;

    private int $beer;

    private int $water;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 28 Oct 2026, 16:00. The default business day ends at 05:00.
        Carbon::setTestNow('2026-10-28 16:00:00');
        config(['pricing.day_ends_at' => '05:00']);

        $drinks = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);
        $this->beer = $this->product($drinks, 'Club Beer', 10, vip: true);
        $this->water = $this->product($drinks, 'Voltic Water', 5);

        $this->admin = $this->userWith(['products' => ['view', 'create', 'modify', 'delete'], 'orders' => ['view', 'create', 'modify']], 'Admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function product(int $categoryId, string $name, float $price, bool $vip = false): int
    {
        return DB::table('products')->insertGetId([
            'name' => $name, 'category_id' => $categoryId, 'selling_price' => $price, 'units_per_box' => 12,
            'is_active' => true, 'is_vip' => $vip, 'created_at' => now(), 'updated_at' => now(),
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

    /** A +20% event on the given days for the current VIP list. */
    private function event(string $start, ?string $end = null, float $percent = 20): PriceEvent
    {
        return $this->prices()->schedule($this->admin, $start, $end ?? $start, $percent, 'Independence Day');
    }

    private function beerPrice(?string $at = null): float
    {
        $product = Product::find($this->beer);

        return $this->prices()->quoteNow($product, $at ? Carbon::parse($at) : null)['price'];
    }

    // ------------------------------------------------------------------ the price rule

    public function test_the_price_is_raised_on_the_event_day_and_back_to_normal_after_it(): void
    {
        $this->event('2026-10-30', '2026-10-31');

        $this->assertSame(10.0, $this->beerPrice('2026-10-29 12:00'), 'before the event');
        $this->assertSame(12.0, $this->beerPrice('2026-10-30 12:00'), 'first day');
        $this->assertSame(12.0, $this->beerPrice('2026-10-31 20:00'), 'last day');
        $this->assertSame(10.0, $this->beerPrice('2026-11-01 12:00'), 'after the event, with nothing to restore');

        $this->assertSame(10.0, (float) DB::table('products')->where('id', $this->beer)->value('selling_price'), 'the stored price is never rewritten');
    }

    public function test_the_business_day_runs_until_closing_time_not_midnight(): void
    {
        $this->event('2026-10-30');

        $this->assertSame(10.0, $this->beerPrice('2026-10-30 04:59'), 'still the 29th business day');
        $this->assertSame(12.0, $this->beerPrice('2026-10-30 05:00'), 'event day starts at closing time');
        $this->assertSame(12.0, $this->beerPrice('2026-10-31 04:59'), 'after midnight is still the event day');
        $this->assertSame(10.0, $this->beerPrice('2026-10-31 05:00'), 'back to normal at 05:00');

        config(['pricing.day_ends_at' => '00:00']);
        $this->assertSame(10.0, $this->beerPrice('2026-10-31 00:30'), 'with a midnight cut-off the day ends at midnight');
    }

    public function test_a_discount_is_applied_and_prices_are_rounded_to_two_decimals(): void
    {
        $this->event('2026-10-30', null, -10);
        $this->assertSame(9.0, $this->beerPrice('2026-10-30 12:00'));

        $this->prices()->setVip($this->admin, Product::find($this->water), true);
        $odd = $this->product(1, 'Odd Drink', 7.35, vip: true);
        $this->event('2026-11-05', null, 12.5);
        $this->assertSame(8.27, $this->prices()->priceOn($odd, 7.35, '2026-11-05'));
    }

    public function test_only_vip_products_are_affected(): void
    {
        $this->event('2026-10-30');

        $quote = $this->prices()->quoteNow(Product::find($this->water), Carbon::parse('2026-10-30 12:00'));
        $this->assertSame(5.0, $quote['price']);
        $this->assertNull($quote['event']);
    }

    public function test_the_event_covers_the_vip_list_as_it_was_when_scheduled(): void
    {
        $this->event('2026-10-30');

        // Added to the VIP list afterwards: not part of the booked event.
        $this->prices()->setVip($this->admin, Product::find($this->water), true);
        $this->assertSame(5.0, $this->prices()->quoteNow(Product::find($this->water), Carbon::parse('2026-10-30 12:00'))['price']);

        // Removed from the list afterwards: still part of it.
        $this->prices()->setVip($this->admin, Product::find($this->beer), false);
        $this->assertSame(12.0, $this->beerPrice('2026-10-30 12:00'));
    }

    public function test_a_cancelled_event_stops_applying_at_once(): void
    {
        $event = $this->event('2026-10-28'); // today
        $this->assertSame(12.0, $this->beerPrice());

        $this->prices()->cancel($this->admin, $event);

        $this->assertSame(10.0, $this->beerPrice());
        $this->assertNotNull($event->fresh()->cancelled_at);
        $this->assertSame(1, DB::table('activity_logs')->where('action_type', 'price_event_cancel')->count());
    }

    public function test_scheduling_rules(): void
    {
        $cases = [
            'no vip products' => fn () => DB::table('products')->update(['is_vip' => false]) && $this->event('2026-10-30'),
            'past day' => fn () => $this->event('2026-10-27'),
            'end before start' => fn () => $this->event('2026-10-31', '2026-10-30'),
            'zero percent' => fn () => $this->event('2026-10-30', null, 0),
            'discount of 100%' => fn () => $this->event('2026-10-30', null, -100),
            'huge increase' => fn () => $this->event('2026-10-30', null, 501),
        ];

        foreach ($cases as $label => $attempt) {
            try {
                $attempt();
                $this->fail("Expected a refusal: $label");
            } catch (\DomainException) {
                $this->assertTrue(true);
            }

            DB::table('products')->where('id', $this->beer)->update(['is_vip' => true]);
        }

        $this->assertSame(0, PriceEvent::count());
    }

    public function test_overlapping_events_for_the_same_products_are_refused(): void
    {
        $this->event('2026-10-30', '2026-11-01');

        $this->expectException(\DomainException::class);
        $this->event('2026-11-01', '2026-11-03');
    }

    public function test_events_that_do_not_share_products_or_days_can_coexist(): void
    {
        $this->event('2026-10-30');
        $this->event('2026-10-31'); // different day, same products

        $this->prices()->setVip($this->admin, Product::find($this->beer), false);
        $this->prices()->setVip($this->admin, Product::find($this->water), true);
        $this->event('2026-10-30'); // same day, different products

        $this->assertSame(3, PriceEvent::count());
        $this->assertSame(6.0, $this->prices()->quoteNow(Product::find($this->water), Carbon::parse('2026-10-30 12:00'))['price']);
    }

    // ------------------------------------------------------------------ orders

    public function test_an_order_during_the_event_uses_the_vip_price_and_later_lines_follow_the_price_in_force(): void
    {
        $event = $this->event('2026-10-28'); // today
        $orders = app(OrderService::class);

        $order = $orders->place($this->admin, 'Kwame', '0241234567', [$this->beer => 2, $this->water => 1]);

        $beerLine = $order->items->firstWhere('product_id', $this->beer);
        $this->assertSame('12.00', $beerLine->unit_price);
        $this->assertSame('24.00', $beerLine->line_total);
        $this->assertSame($event->id, $beerLine->price_event_id);
        $this->assertSame('5.00', $order->items->firstWhere('product_id', $this->water)->unit_price);
        $this->assertNull($order->items->firstWhere('product_id', $this->water)->price_event_id);
        $this->assertSame('29.00', $order->fresh()->total);

        // The event ends: a later round of the same drink goes on a new line at the normal price,
        // and the first round keeps the price it was taken at.
        Carbon::setTestNow('2026-10-29 16:00:00');
        $orders->addItems($order, $this->admin, [$this->beer => 1]);

        $beerLines = $order->fresh()->items->where('product_id', $this->beer);
        $this->assertSame(['10.00', '12.00'], $beerLines->pluck('unit_price')->sort()->values()->all());
        $this->assertSame('39.00', $order->fresh()->total);
    }

    public function test_the_take_order_screen_shows_the_vip_price(): void
    {
        $this->event('2026-10-28');
        $this->actingAs($this->admin);

        Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->assertSee('VIP +20%')
            ->assertSee('12.00')
            ->call('addToCart', $this->beer)
            ->call('addToCart', $this->beer)
            ->assertSee('24.00'); // two at 12.00
    }

    // ------------------------------------------------------------------ end of day stock taking

    public function test_end_of_day_revenue_for_an_event_day_uses_the_vip_price_and_survives_an_edit_after_the_event(): void
    {
        $this->event('2026-10-28'); // the event day being recorded below
        DB::table('stocks')->insert([
            'product_id' => $this->beer, 'total_units' => 100, 'cost_price' => 6, 'cost_margin' => 4, 'total_cost' => 600,
            'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);
        $this->actingAs($this->admin);

        // Record the event day (28th) itself, taken the next afternoon.
        Carbon::setTestNow('2026-10-29 16:00:00');

        $component = Livewire::test(Inventory::class)
            ->call('openTakeInventoryModal')
            ->set('salesDate', '2026-10-28')
            ->set('cashAmount', '700')
            ->set('momoAmount', '0')
            ->set('hubtelAmount', '0')
            ->set('snooker', '0')
            ->set('foodTotal', '0')
            ->set("productStocks.{$this->beer}.closing_units", '40');

        $this->assertSame(720.0, (float) $component->instance()->calculateExpectedRevenue($this->beer), '60 sold x 12.00');

        $component->call('submitInventory')->assertHasNoErrors();

        $this->assertSame(720.0, (float) DB::table('daily_sales')->where('product_id', $this->beer)->value('total_amount'));
        $this->assertSame(720.0, (float) DB::table('daily_sales_summaries')->value('drinks_total'));
        $this->assertSame(360.0, (float) DB::table('daily_sales_summaries')->value('total_profit'), '60 sold x (4.00 margin + 2.00 price change)');
        $this->assertSame(360.0, (float) DB::table('daily_sales')->where('product_id', $this->beer)->value('unit_profit'));
        $this->assertSame(360.0, 720.0 - 60 * 6.0, 'revenue minus cost of goods equals the profit');
    }

    public function test_profit_follows_a_discount_and_is_unchanged_on_an_ordinary_day(): void
    {
        $this->event('2026-10-28', percent: -10); // 9.00 instead of 10.00; the 27th is an ordinary day
        DB::table('stocks')->insert([
            'product_id' => $this->beer, 'total_units' => 100, 'cost_price' => 6, 'cost_margin' => 4, 'total_cost' => 600,
            'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);
        $this->actingAs($this->admin);
        Carbon::setTestNow('2026-10-29 16:00:00');

        $record = function (string $date, string $closing) {
            Livewire::test(Inventory::class)
                ->call('openTakeInventoryModal')
                ->set('salesDate', $date)
                ->set('cashAmount', '0')
                ->set('momoAmount', '0')
                ->set('hubtelAmount', '0')
                ->set('snooker', '0')
                ->set('foodTotal', '0')
                ->set("productStocks.{$this->beer}.closing_units", $closing)
                ->call('submitInventory')
                ->assertHasNoErrors();
        };

        $record('2026-10-28', '90'); // discount day: 10 sold at 9.00, cost 6.00
        $this->assertSame(30.0, (float) DB::table('daily_sales')->where('sales_date', '2026-10-28')->value('unit_profit'));
        $this->assertSame(90.0, (float) DB::table('daily_sales')->where('sales_date', '2026-10-28')->value('total_amount'));

        $record('2026-10-27', '80'); // ordinary day: 10 sold at 10.00, margin 4.00 (stock now at 90, margin recomputed)
        $this->assertSame(40.0, (float) DB::table('daily_sales')->where('sales_date', '2026-10-27')->value('unit_profit'));
        $this->assertSame(100.0, (float) DB::table('daily_sales')->where('sales_date', '2026-10-27')->value('total_amount'));
    }

    public function test_analytics_and_a_late_edit_keep_the_event_day_profit_and_losses_at_the_event_price(): void
    {
        $this->event('2026-10-28');
        DB::table('stocks')->insert([
            'product_id' => $this->beer, 'total_units' => 100, 'cost_price' => 6, 'cost_margin' => 4, 'total_cost' => 600,
            'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);
        $this->actingAs($this->admin);
        Carbon::setTestNow('2026-10-29 16:00:00');

        Livewire::test(Inventory::class)
            ->call('openTakeInventoryModal')
            ->set('salesDate', '2026-10-28')
            ->set('cashAmount', '720') // exactly the expected 60 x 12.00: no loss
            ->set('momoAmount', '0')
            ->set('hubtelAmount', '0')
            ->set('snooker', '0')
            ->set('foodTotal', '0')
            ->set("productStocks.{$this->beer}.closing_units", '40')
            ->call('submitInventory')
            ->assertHasNoErrors();

        // Analytics' month picker uses PostgreSQL's TO_CHAR; give the SQLite test database an equivalent.
        DB::connection()->getPdo()->sqliteCreateFunction('TO_CHAR', fn ($value) => substr((string) $value, 0, 7));

        $analytics = Livewire::test(\App\Livewire\Pages\Analytics::class)->set('startDate', '2026-10-28')->set('endDate', '2026-10-28');

        $this->assertEqualsWithDelta(720, (float) $analytics->get('drinksTotal'), 0.01);
        $this->assertEqualsWithDelta(360, (float) $analytics->get('totalProfit'), 0.01);
        $this->assertEqualsWithDelta(50, (float) $analytics->get('averageProfitMargin'), 0.01, '(12.00 - 6.00) / 12.00');
        $this->assertEqualsWithDelta(0, (float) $analytics->get('accumulatedLosses'), 0.01, 'collected equals the expected event-price total');

        // The event is long over: correcting that day keeps the event price in revenue and profit.
        Carbon::setTestNow('2026-11-10 16:00:00');
        $summaryId = DB::table('daily_sales_summaries')->value('id');

        Livewire::test(Inventory::class)
            ->call('openEditModal', $summaryId)
            ->set("productStocks.{$this->beer}.closing_units", '30') // 70 sold
            ->set('cashAmount', '840')
            ->call('updateInventory')
            ->assertHasNoErrors();

        $this->assertSame(840.0, (float) DB::table('daily_sales')->where('product_id', $this->beer)->value('total_amount'), '70 x 12.00');
        $this->assertSame(420.0, (float) DB::table('daily_sales')->where('product_id', $this->beer)->value('unit_profit'), '70 x (4.00 + 2.00)');
        $this->assertSame(420.0, (float) DB::table('daily_sales_summaries')->value('total_profit'));
    }

    // ------------------------------------------------------------------ Products page

    public function test_the_products_page_shows_the_vip_price_beside_the_selling_price(): void
    {
        $this->event('2026-10-28'); // running now
        $this->prices()->setVip($this->admin, Product::find($this->water), true);
        $this->event('2026-11-05', null, -10); // next week, for beer and water

        $this->actingAs($this->admin);

        Livewire::test(Products::class)
            ->assertSee('VIP GH₵ 12.00 (+20%)')
            ->assertSee('Thu 5 Nov: GH₵ 4.50 (-10%)'); // water has only the upcoming one
    }

    public function test_a_vip_product_without_an_event_shows_a_vip_tag_and_the_vip_filter_works(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Products::class)
            ->assertSee('★ VIP')
            ->assertSee('Voltic Water')
            ->set('vipOnly', true)
            ->assertSee('Club Beer')
            ->assertDontSee('Voltic Water');
    }

    public function test_the_vip_list_can_be_edited_and_a_price_day_scheduled_and_cancelled_from_the_page(): void
    {
        $this->actingAs($this->admin);

        $page = Livewire::test(Products::class)
            ->call('toggleVip', $this->water)
            ->assertSee('Voltic Water is now on the VIP list.');
        $this->assertTrue((bool) Product::find($this->water)->is_vip);

        $page->call('toggleVip', $this->water);
        $this->assertFalse((bool) Product::find($this->water)->is_vip);

        $page->call('openVipModal')
            ->assertSet('vipStart', '2026-10-28')
            ->assertSee('Club Beer')
            ->set('vipName', 'Derby Night')
            ->set('vipStart', '2026-10-31')
            ->assertSet('vipEnd', '2026-10-31')
            ->set('vipPercent', '25')
            ->assertSee('12.50') // preview of the new price
            ->call('scheduleVipPrices')
            ->assertHasNoErrors()
            ->assertSet('showVipModal', false);

        $event = PriceEvent::firstOrFail();
        $this->assertSame('Derby Night', $event->name);
        $this->assertSame('25.00', $event->percent);
        $this->assertSame('2026-10-31', $event->starts_on);

        $page->call('openVipModal')->assertSee('Derby Night')->call('cancelVipEvent', $event->id);
        $this->assertNotNull($event->fresh()->cancelled_at);
    }

    public function test_the_form_is_validated_and_service_refusals_are_shown(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Products::class)
            ->call('openVipModal')
            ->set('vipPercent', '')
            ->call('scheduleVipPrices')
            ->assertHasErrors(['vipPercent' => 'required'])
            ->set('vipPercent', '0')
            ->call('scheduleVipPrices')
            ->assertHasErrors(['vipPercent'])
            ->set('vipStart', '2026-10-20') // in the past
            ->set('vipEnd', '2026-10-20')
            ->set('vipPercent', '10')
            ->call('scheduleVipPrices')
            ->assertHasErrors(['vipPercent'])
            ->assertSee('cannot be in the past');

        $this->assertSame(0, PriceEvent::count());
    }

    public function test_only_roles_that_can_modify_products_can_manage_vip_prices(): void
    {
        $viewer = $this->userWith(['products' => ['view']], 'Viewer');
        $this->actingAs($viewer);

        Livewire::test(Products::class)->assertDontSee('VIP Price Days')->call('toggleVip', $this->water)->assertForbidden();
        Livewire::test(Products::class)->call('openVipModal')->assertForbidden();
        Livewire::test(Products::class)->set('vipStart', '2026-10-30')->set('vipEnd', '2026-10-30')->set('vipPercent', '10')->call('scheduleVipPrices')->assertForbidden();

        $event = $this->event('2026-10-30');
        Livewire::test(Products::class)->call('cancelVipEvent', $event->id)->assertForbidden();

        $this->assertFalse((bool) Product::find($this->water)->is_vip);
        $this->assertNull($event->fresh()->cancelled_at);

        // A viewer still sees the prices.
        Livewire::test(Products::class)->assertSee('Fri 30 Oct: GH₵ 12.00 (+20%)');
    }

    public function test_the_order_totals_of_other_pages_are_unaffected_when_nothing_is_scheduled(): void
    {
        $order = app(OrderService::class)->place($this->admin, 'Kwame', '0241234567', [$this->beer => 2]);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame('20.00', $order->fresh()->total);
        $this->assertNull($order->items->first()->price_event_id);
    }
}

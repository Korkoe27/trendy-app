<?php

namespace Tests\Feature;

use App\Livewire\Pages\Analytics;
use App\Livewire\Pages\CoinRequests;
use App\Livewire\Pages\Inventory;
use App\Models\CoinRequest;
use App\Models\Product;
use App\Models\User;
use App\Services\CoinRequestService;
use App\Services\DebtLedger;
use App\Support\DailyDifference;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Snooker has no stock: the game product has coins per day that come back every night. The
 * attendant requests coins, the bar gives them, and the end-of-day count checks the snooker money
 * against the coins given.
 */
class CoinRequestsTest extends TestCase
{
    use RefreshDatabase;

    private int $snooker;

    private int $beer;

    private User $attendant;

    private User $bartender;

    private User $manager;

    private CoinRequestService $coins;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 28 Oct 2026, 20:00: inside that night's session (closing hour 03:00).
        Carbon::setTestNow('2026-10-28 20:00:00');
        config(['pricing.day_ends_at' => '03:00', 'pricing.opens_at' => '15:00', 'coins.max_per_request' => 1000, 'coins.one_waiting_per_attendant' => false]); // bulk requests are exercised below; the one-coin and one-waiting rules have their own tests
        DB::connection()->getPdo()->sqliteCreateFunction('TO_CHAR', fn ($value) => substr((string) $value, 0, 7));

        $games = DB::table('categories')->insertGetId(['name' => 'games', 'pricing_model' => 'per_slot', 'created_at' => now(), 'updated_at' => now()]);
        $drinks = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);

        $this->snooker = DB::table('products')->insertGetId([
            'name' => 'Snooker', 'category_id' => $games, 'selling_price' => 10, 'daily_coins' => 200,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->beer = DB::table('products')->insertGetId([
            'name' => 'Club Beer', 'category_id' => $drinks, 'selling_price' => 10, 'units_per_box' => 12,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->attendant = $this->userWith(['coin_requests' => ['view', 'create']], 'Esi');
        $this->bartender = $this->userWith(['coin_requests' => ['view', 'modify']], 'Akua');
        $this->manager = $this->userWith(['coin_requests' => ['view', 'create', 'modify', 'delete'], 'inventory' => ['view', 'create', 'modify']], 'Manager');

        $this->coins = app(CoinRequestService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    private function game(): Product
    {
        return Product::find($this->snooker);
    }

    private function request(int $quantity, ?User $by = null): CoinRequest
    {
        return $this->coins->request($by ?? $this->attendant, $this->game(), $quantity);
    }

    // ------------------------------------------------------------------ availability

    public function test_the_pool_is_the_daily_coins_minus_given_and_waiting_coins(): void
    {
        $this->assertSame(200, $this->coins->available($this->game()));

        $given = $this->request(40);
        $this->coins->give($given, $this->bartender);
        $this->request(10); // waiting: reserved

        $this->assertSame(150, $this->coins->available($this->game()));
    }

    public function test_more_than_is_available_is_refused(): void
    {
        $this->request(190);

        try {
            $this->request(11);
            $this->fail('Only 10 coins are left.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Only 10', $e->getMessage());
        }

        $this->request(10);

        $this->expectException(\DomainException::class);
        $this->request(1);
    }

    public function test_a_cancelled_or_voided_request_frees_its_coins(): void
    {
        $waiting = $this->request(30);
        $this->assertSame(170, $this->coins->available($this->game()));

        $this->coins->cancel($waiting, $this->attendant);
        $this->assertSame(200, $this->coins->available($this->game()));

        $given = $this->coins->give($this->request(25), $this->bartender);
        $this->assertSame(175, $this->coins->available($this->game()));

        $this->coins->void($given, $this->manager, 'Entered twice');
        $this->assertSame(200, $this->coins->available($this->game()));
    }

    public function test_the_pool_is_full_again_in_the_next_session(): void
    {
        $this->coins->give($this->request(200), $this->bartender);
        $this->assertSame(0, $this->coins->available($this->game()));

        Carbon::setTestNow('2026-10-29 02:59:00'); // still the same session
        $this->assertSame(0, $this->coins->available($this->game()));

        Carbon::setTestNow('2026-10-29 03:00:00'); // the closing hour: coins are back
        $this->assertSame(200, $this->coins->available($this->game()));
        $this->request(5);
    }

    // ------------------------------------------------------------------ the requests

    public function test_only_a_game_with_coins_can_be_requested(): void
    {
        foreach ([Product::find($this->beer)] as $product) {
            try {
                $this->coins->request($this->attendant, $product, 1);
                $this->fail('Coins can only be requested for a game.');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('game product', $e->getMessage());
            }
        }

        DB::table('products')->where('id', $this->snooker)->update(['daily_coins' => null]);

        $this->expectException(\DomainException::class);
        $this->request(1);
    }

    public function test_giving_captures_the_price_and_the_session_date(): void
    {
        $request = $this->request(3);
        $this->assertSame('2026-10-28', $request->business_date->toDateString());
        $this->assertNull($request->unit_price);

        // The coin price changes before the bar gives the coins: the price at hand-over counts.
        DB::table('products')->where('id', $this->snooker)->update(['selling_price' => 12]);
        $given = $this->coins->give($request, $this->bartender);

        $this->assertSame('given', $given->status);
        $this->assertSame('12.00', $given->unit_price);
        $this->assertSame('36.00', $given->total);
        $this->assertSame($this->bartender->id, $given->given_by);
        $this->assertSame(['coins' => 3, 'amount' => 36.0], $this->coins->givenOn('2026-10-28'));
        $this->assertSame(['coins' => 0, 'amount' => 0.0], $this->coins->givenOn('2026-10-29'));
    }

    public function test_a_late_night_request_belongs_to_the_session_that_started_the_day_before(): void
    {
        Carbon::setTestNow('2026-10-29 01:30:00'); // Thursday 01:30 is still Wednesday's session

        $request = $this->request(2);

        $this->assertSame('2026-10-28', $request->business_date->toDateString());
    }

    public function test_a_coin_price_day_applies_when_the_coins_are_given(): void
    {
        app(\App\Services\PriceService::class)->scheduleCustom($this->manager, '2026-10-28', '2026-10-28', [$this->snooker => '15'], 'Derby');

        $given = $this->coins->give($this->request(2), $this->bartender);

        $this->assertSame('15.00', $given->unit_price);
        $this->assertSame('30.00', $given->total);
    }

    public function test_a_request_can_only_be_given_once_and_only_by_status(): void
    {
        $given = $this->coins->give($this->request(1), $this->bartender);

        $this->expectException(\DomainException::class);
        $this->coins->give($given, $this->bartender);
    }

    public function test_who_may_cancel_and_void(): void
    {
        $mine = $this->request(1);
        $other = $this->userWith(['coin_requests' => ['view', 'create']], 'Kojo');

        try {
            $this->coins->cancel($mine, $other);
            $this->fail('Another attendant must not cancel it.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Only the attendant who asked', $e->getMessage());
        }

        $this->coins->cancel($mine, $this->manager); // a manager can
        $this->assertSame('cancelled', $mine->fresh()->status);

        $given = $this->coins->give($this->request(1), $this->bartender);

        foreach ([[$this->attendant, 'Mistake'], [$this->manager, '  ']] as [$by, $reason]) {
            try {
                $this->coins->void($given, $by, $reason);
                $this->fail('Void must be refused.');
            } catch (\DomainException) {
                $this->assertTrue(true);
            }
        }

        $voided = $this->coins->void($given, $this->manager, 'Wrong customer');
        $this->assertSame('voided', $voided->status);
        $this->assertSame(['coins' => 0, 'amount' => 0.0], $this->coins->givenOn('2026-10-28'));
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'coin_request_void']);
    }

    public function test_an_attendant_can_request_only_one_coin_at_a_time(): void
    {
        config(['coins.max_per_request' => 1]);

        try {
            $this->request(2);
            $this->fail('Two coins in one request must be refused.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('only request 1 coin at a time', $e->getMessage());
        }

        $this->assertSame(0, CoinRequest::count());
        $this->assertSame(200, $this->coins->available($this->game()));

        // A customer who needs two coins is two requests, each visible to the bar.
        $this->coins->give($this->request(1), $this->bartender);
        $this->request(1);
        $this->assertSame(2, CoinRequest::count());
        $this->assertSame(198, $this->coins->available($this->game()));
    }

    public function test_an_attendant_cannot_request_while_one_of_their_coins_is_waiting(): void
    {
        config(['coins.max_per_request' => 1, 'coins.one_waiting_per_attendant' => true]);

        $first = $this->request(1);

        try {
            $this->request(1);
            $this->fail('A second request while the first is waiting must be refused.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('already have a coin waiting for the bar', $e->getMessage());
        }

        $this->assertSame(1, CoinRequest::count());

        // Once the bar has given it, the attendant can ask again.
        $this->coins->give($first, $this->bartender);
        $this->request(1);
        $this->assertSame(2, CoinRequest::count());

        // Cancelling the waiting one also frees the attendant.
        $waiting = CoinRequest::where('status', 'requested')->firstOrFail();
        $this->coins->cancel($waiting, $this->attendant);
        $this->request(1);
        $this->assertSame(3, CoinRequest::count());
    }

    public function test_one_attendants_waiting_coin_does_not_block_another_attendant(): void
    {
        config(['coins.max_per_request' => 1, 'coins.one_waiting_per_attendant' => true]);
        $other = $this->userWith(['coin_requests' => ['view', 'create']], 'Kojo');

        $this->request(1);
        $this->request(1, $other);

        $this->assertSame(2, CoinRequest::where('status', 'requested')->count());
    }

    public function test_a_waiting_request_from_an_earlier_session_does_not_block_today(): void
    {
        config(['coins.max_per_request' => 1, 'coins.one_waiting_per_attendant' => true]);

        $this->request(1); // Wednesday's session, never given

        Carbon::setTestNow('2026-10-29 16:00:00'); // Thursday's session
        $this->request(1);

        $this->assertSame(2, CoinRequest::count());
    }

    public function test_the_screen_disables_the_request_button_while_a_coin_is_waiting(): void
    {
        config(['coins.max_per_request' => 1, 'coins.one_waiting_per_attendant' => true]);
        $this->actingAs($this->attendant);

        $page = Livewire::test(CoinRequests::class)
            ->assertDontSee('Your coin is waiting for the bar')
            ->call('requestCoins')
            ->assertHasNoErrors()
            ->assertSee('Your coin is waiting for the bar')
            ->assertSeeHtml('disabled');

        // Even if the button is bypassed, the server refuses.
        $page->call('requestCoins')->assertHasErrors('quantity')->assertSee('already have a coin waiting');
        $this->assertSame(1, CoinRequest::count());

        // The bar gives it: the attendant can request again.
        $this->coins->give(CoinRequest::firstOrFail(), $this->bartender);
        Livewire::test(CoinRequests::class)->assertDontSee('Your coin is waiting for the bar')->call('requestCoins')->assertHasNoErrors();
        $this->assertSame(2, CoinRequest::count());
    }

    public function test_the_screen_offers_one_coin_and_refuses_a_larger_request(): void
    {
        config(['coins.max_per_request' => 1, 'coins.one_waiting_per_attendant' => false]);
        $this->actingAs($this->attendant);

        $page = Livewire::test(CoinRequests::class)
            ->assertSee('One coin per request')
            ->assertDontSee('aria-label="One more"', false)
            ->call('incrementQuantity')
            ->assertSet('quantity', 1);

        // A tampered request that sets a larger number is still refused by the server.
        $page->set('quantity', 5)->call('requestCoins')->assertHasErrors('quantity')->assertSee('only request 1 coin at a time');

        $page->set('quantity', 1)->call('requestCoins')->assertHasNoErrors();
        $this->assertSame(1, CoinRequest::count());
        $this->assertSame(1, (int) CoinRequest::first()->quantity);
    }

    // ------------------------------------------------------------------ the screen

    public function test_the_screen_follows_the_rights_of_each_role(): void
    {
        $this->actingAs($this->attendant);
        Livewire::test(CoinRequests::class)
            ->assertSee('Available today: 200 of 200')
            ->set('quantity', 2)
            ->call('requestCoins')
            ->assertHasNoErrors()
            ->assertSee('Available today: 198 of 200')
            ->assertSee('Waiting for the bar (1)')
            ->assertDontSee('Given ✓');

        $request = CoinRequest::firstOrFail();

        Livewire::test(CoinRequests::class)->call('give', $request->id)->assertForbidden();

        $this->actingAs($this->bartender);
        Livewire::test(CoinRequests::class)
            ->assertSee('Given ✓')
            ->assertDontSee('REQUEST')
            ->call('requestCoins')->assertForbidden();

        Livewire::test(CoinRequests::class)->call('give', $request->id)->assertSee('Marked as given.');
        $this->assertSame('given', $request->fresh()->status);

        $this->actingAs($this->manager);
        Livewire::test(CoinRequests::class)->assertSee('Given this session: 2 coins')->assertSee('20.00')->assertSee('Void');
    }

    public function test_the_screen_refuses_more_than_available_and_shows_why(): void
    {
        $this->actingAs($this->attendant);

        Livewire::test(CoinRequests::class)
            ->set('quantity', 201)
            ->call('requestCoins')
            ->assertHasErrors('quantity')
            ->assertSee('Only 200');

        $this->assertSame(0, CoinRequest::count());
    }

    public function test_the_route_needs_the_coin_requests_right(): void
    {
        $this->actingAs($this->userWith([], 'Nobody'))->get(route('coin-requests'))->assertForbidden();
        $this->actingAs($this->attendant)->get(route('coin-requests'))->assertOk()->assertSee('Coin Requests');
    }

    // ------------------------------------------------------------------ end of day

    private function giveCoins(int $quantity): void
    {
        $this->coins->give($this->request($quantity), $this->bartender);
    }

    public function test_the_stock_count_shows_the_snooker_money_expected_from_the_coins_given(): void
    {
        $this->giveCoins(12); // 12 x 10 = 120

        Carbon::setTestNow('2026-10-29 16:00:00');
        $this->actingAs($this->manager);

        Livewire::test(Inventory::class)
            ->call('openTakeInventoryModal')
            ->set('salesDate', '2026-10-28')
            ->assertSeeHtml('Coins given: <strong>12</strong>')
            ->assertSee('GH₵ 120.00 expected')
            ->set('snooker', '100')
            ->assertSee('Short by GH₵ 20.00')
            ->set('snooker', '120')
            ->assertSee('Matches the coins given')
            ->set('snooker', '150')
            ->assertSee('Over by GH₵ 30.00');
    }

    private function takeDay(string $snooker): void
    {
        DB::table('stocks')->insert([
            'product_id' => $this->beer, 'total_units' => 100, 'cost_price' => 6, 'cost_margin' => 4, 'total_cost' => 600,
            'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
        ]);

        Carbon::setTestNow('2026-10-29 16:00:00');
        $this->actingAs($this->manager);

        Livewire::test(Inventory::class)
            ->call('openTakeInventoryModal')
            ->set('salesDate', '2026-10-28')
            ->set('cashAmount', '600') // 60 beers sold at 10
            ->set('momoAmount', '0')
            ->set('hubtelAmount', '0')
            ->set('snooker', $snooker)
            ->set('foodTotal', '0')
            ->set("productStocks.{$this->beer}.closing_units", '40')
            ->call('submitInventory')
            ->assertHasNoErrors();
    }

    public function test_the_day_stores_a_snapshot_and_a_snooker_shortfall_counts_as_a_loss(): void
    {
        $this->giveCoins(12); // expected 120
        $this->takeDay('100');  // 20 short

        $summary = DB::table('daily_sales_summaries')->first();
        $this->assertSame(12, (int) $summary->snooker_coins);
        $this->assertSame(120.0, (float) $summary->snooker_expected);
        $this->assertSame(100.0, (float) $summary->snooker);

        $this->assertEqualsWithDelta(20.0, DailyDifference::snookerShortfall($summary), 0.001);
        $this->assertEqualsWithDelta(-20.0, DailyDifference::difference($summary), 0.001, 'drinks matched, snooker short by 20');

        // Debt Payments and Analytics agree on the same figure.
        $this->assertSame(20.0, (new DebtLedger)->monthlyDebt(Carbon::create(2026, 10, 1)));

        $analytics = Livewire::test(Analytics::class)->set('timeframe', 'custom')->set('startDate', '2026-10-01')->set('endDate', '2026-10-31');
        $this->assertEqualsWithDelta(-20.0, (float) $analytics->get('accumulatedLosses'), 0.01);
        $this->assertEqualsWithDelta(-(float) $analytics->get('accumulatedLosses'), (new DebtLedger)->monthlyDebt(Carbon::create(2026, 10, 1)), 0.01);
    }

    public function test_a_matching_amount_is_no_loss_and_an_excess_does_not_offset_anything(): void
    {
        $this->giveCoins(12);
        $this->takeDay('120');

        $summary = DB::table('daily_sales_summaries')->first();
        $this->assertEqualsWithDelta(0.0, DailyDifference::difference($summary), 0.001);

        // An excess is flagged on the form but never offsets a drinks shortfall.
        DB::table('daily_sales_summaries')->update(['snooker' => 300, 'total_cash' => 580]); // 20 short on drinks, 180 over on snooker
        $summary = DB::table('daily_sales_summaries')->first();

        $this->assertEqualsWithDelta(0.0, DailyDifference::snookerShortfall($summary), 0.001);
        $this->assertEqualsWithDelta(-20.0, DailyDifference::difference($summary), 0.001);
        $this->assertSame(20.0, (new DebtLedger)->monthlyDebt(Carbon::create(2026, 10, 1)));
    }

    public function test_days_without_a_snooker_snapshot_are_unchanged(): void
    {
        DB::table('daily_sales_summaries')->insert([
            'sales_date' => '2026-09-10', 'drinks_total' => 500, 'food_total' => 0, 'on_the_house' => 0,
            'total_cash' => 480, 'total_momo' => 0, 'total_hubtel' => 0, 'snooker' => 250, 'product_id' => $this->beer,
            'created_at' => now(), 'updated_at' => now(),
        ]); // snooker typed, no snapshot (a day from before the check existed)

        $row = DB::table('daily_sales_summaries')->first();
        $this->assertNull($row->snooker_expected);
        $this->assertEqualsWithDelta(-20.0, DailyDifference::difference($row), 0.001);
        $this->assertSame(20.0, (new DebtLedger)->monthlyDebt(Carbon::create(2026, 9, 1)));
    }

    public function test_editing_a_day_takes_a_fresh_snapshot_of_the_coins_given(): void
    {
        $this->giveCoins(12);
        $this->takeDay('120');
        $summaryId = DB::table('daily_sales_summaries')->value('id');

        // The bar voids 2 coins after the day was recorded; editing re-reads the coins given.
        $request = CoinRequest::firstOrFail();
        $this->coins->void($request, $this->manager, 'Wrong count');
        Carbon::setTestNow('2026-10-28 21:00:00'); // the same session the day belongs to
        $this->giveCoins(10);
        Carbon::setTestNow('2026-10-29 16:00:00');

        Livewire::test(Inventory::class)
            ->call('openEditModal', $summaryId)
            ->set("productStocks.{$this->beer}.closing_units", '40')
            ->call('updateInventory')
            ->assertHasNoErrors();

        $summary = DB::table('daily_sales_summaries')->first();
        $this->assertSame(10, (int) $summary->snooker_coins);
        $this->assertSame(100.0, (float) $summary->snooker_expected);
    }

    // ------------------------------------------------------------------ permissions migration

    public function test_the_migration_creates_the_module_role_and_rights_and_is_idempotent(): void
    {
        $migration = require base_path('database/migrations/2026_11_07_100100_add_coin_requests_module_roles_and_permissions.php');
        $migration->up();
        $migration->up();

        $moduleId = DB::table('modules')->where('name', 'coin_requests')->value('id');
        $this->assertNotNull($moduleId);
        $this->assertSame(1, DB::table('modules')->where('name', 'coin_requests')->count());

        $rights = fn (string $role) => (array) DB::table('permissions')
            ->where('module_id', $moduleId)
            ->where('role_id', DB::table('roles')->where('name', $role)->value('id'))
            ->first(['can_view', 'can_create', 'can_modify', 'can_delete']);

        $this->assertEquals(['can_view' => 1, 'can_create' => 1, 'can_modify' => 0, 'can_delete' => 0], $rights('Snooker Attendant'));
        $this->assertSame(1, DB::table('roles')->where('name', 'Snooker Attendant')->count());
    }
}

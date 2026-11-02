<?php

namespace Tests\Feature;

use App\Livewire\Pages\CoinRequests;
use App\Models\CoinDay;
use App\Models\CoinRequest;
use App\Models\Product;
use App\Models\User;
use App\Services\CoinRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coin requests open by themselves at the opening hour, the bar closes the night by hand, and every
 * business day gets an entry (with a detail window) for the bar and managers.
 */
class CoinOpeningHoursTest extends TestCase
{
    use RefreshDatabase;

    private int $snooker;

    private User $attendant;

    private User $bartender;

    private User $manager;

    private CoinRequestService $coins;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-28 20:00:00'); // Wednesday evening, inside the session
        config(['pricing.day_ends_at' => '03:00', 'pricing.opens_at' => '15:00', 'coins.max_per_request' => 1000, 'coins.one_waiting_per_attendant' => false]);

        $games = DB::table('categories')->insertGetId(['name' => 'games', 'pricing_model' => 'per_slot', 'created_at' => now(), 'updated_at' => now()]);
        $this->snooker = DB::table('products')->insertGetId([
            'name' => 'Snooker', 'category_id' => $games, 'selling_price' => 10, 'daily_coins' => 200,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->attendant = $this->userWith(['coin_requests' => ['view', 'create']], 'Esi');
        $this->bartender = $this->userWith(['coin_requests' => ['view', 'modify']], 'Akua');
        $this->manager = $this->userWith(['coin_requests' => ['view', 'create', 'modify', 'delete']], 'Manager');

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

    private function request(int $quantity = 1, ?User $by = null): CoinRequest
    {
        return $this->coins->request($by ?? $this->attendant, $this->game(), $quantity, null, 'Kojo');
    }

    private function given(int $quantity, ?User $by = null): CoinRequest
    {
        return $this->coins->give($this->request($quantity, $by), $this->bartender);
    }

    // ------------------------------------------------------------------ opening hours

    public function test_requests_open_at_the_opening_hour_and_run_past_midnight(): void
    {
        foreach (['2026-10-28 15:00:00', '2026-10-28 23:30:00', '2026-10-29 02:59:00'] as $moment) {
            Carbon::setTestNow($moment);
            $this->assertTrue($this->coins->availability()['open'], $moment);
            $this->request();
        }

        // Everything above belongs to Wednesday's session.
        $this->assertSame(3, CoinRequest::whereDate('business_date', '2026-10-28')->count());
    }

    public function test_requests_are_refused_before_the_opening_hour_with_the_time_they_open(): void
    {
        foreach (['2026-10-28 09:00:00', '2026-10-28 14:59:00', '2026-10-29 03:00:00', '2026-10-29 10:00:00'] as $moment) {
            Carbon::setTestNow($moment);

            $state = $this->coins->availability();
            $this->assertFalse($state['open'], $moment);
            $this->assertSame('before_opening', $state['reason']);

            try {
                $this->request();
                $this->fail("A request at {$moment} must be refused.");
            } catch (\DomainException $e) {
                $this->assertSame('Coin requests open at 3:00 PM.', $e->getMessage());
            }
        }

        $this->assertSame(0, CoinRequest::count());
    }

    public function test_the_opening_hour_follows_the_setting(): void
    {
        config(['pricing.opens_at' => '18:00']);
        Carbon::setTestNow('2026-10-28 17:59:00');

        $this->assertFalse($this->coins->availability()['open']);

        Carbon::setTestNow('2026-10-28 18:00:00');
        $this->assertTrue($this->coins->availability()['open']);
    }

    public function test_the_screen_shows_when_requests_open_and_blocks_the_button(): void
    {
        Carbon::setTestNow('2026-10-28 10:00:00');
        $this->actingAs($this->attendant);

        Livewire::test(CoinRequests::class)
            ->assertSeeHtml('data-testid="coin-not-open"')
            ->assertSee('Opens at 3:00 PM')
            ->set('customerName', 'Kojo')->call('requestCoins')
            ->assertHasErrors('quantity');

        $this->assertSame(0, CoinRequest::count());

        Carbon::setTestNow('2026-10-28 15:00:00');

        Livewire::test(CoinRequests::class)
            ->assertDontSeeHtml('data-testid="coin-not-open"')
            ->assertSee('Requests open')
            ->set('customerName', 'Kojo')->call('requestCoins')
            ->assertHasNoErrors();

        $this->assertSame(1, CoinRequest::count());
    }

    // ------------------------------------------------------------------ closing the night

    public function test_the_bar_closes_the_night_and_waiting_coins_are_cancelled_with_a_snapshot(): void
    {
        $this->given(3);
        $this->given(2);
        $waiting = $this->request(1);

        Carbon::setTestNow('2026-10-29 01:00:00');
        $day = $this->coins->closeDay($this->bartender);

        $this->assertTrue($day->isClosed());
        $this->assertSame('2026-10-28', $day->business_date->toDateString());
        $this->assertSame(5, (int) $day->closed_coins);
        $this->assertSame(50.0, (float) $day->closed_amount);
        $this->assertSame(1, (int) $day->closed_cancelled);
        $this->assertSame('Akua', $day->closed_by_name);

        $this->assertSame(CoinRequest::STATUS_CANCELLED, $waiting->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'coin_day_close', 'user_id' => $this->bartender->id]);
    }

    public function test_nothing_can_be_requested_after_the_night_is_closed_but_tomorrow_opens_normally(): void
    {
        $this->coins->closeDay($this->manager);

        $state = $this->coins->availability();
        $this->assertFalse($state['open']);
        $this->assertSame('closed', $state['reason']);
        $this->assertSame('Manager', $state['closed_by']);

        try {
            $this->request();
            $this->fail('A closed night must refuse requests.');
        } catch (\DomainException $e) {
            $this->assertSame('Coin requests are closed for tonight.', $e->getMessage());
        }

        Carbon::setTestNow('2026-10-29 15:00:00'); // next session

        $this->assertTrue($this->coins->availability()['open']);
        $this->assertSame(200, $this->coins->available($this->game()));
        $this->request();
    }

    public function test_only_the_bar_or_a_manager_can_close_and_reopen(): void
    {
        try {
            $this->coins->closeDay($this->attendant);
            $this->fail('An attendant cannot close the night.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Only the bar or a manager', $e->getMessage());
        }

        $this->assertSame(0, CoinDay::count());

        $this->actingAs($this->attendant);
        Livewire::test(CoinRequests::class)->call('closeNight')->assertForbidden();
        Livewire::test(CoinRequests::class)->call('reopenNight')->assertForbidden();
    }

    public function test_closing_twice_is_refused_and_a_night_that_has_not_opened_cannot_be_closed(): void
    {
        Carbon::setTestNow('2026-10-28 10:00:00');

        try {
            $this->coins->closeDay($this->bartender);
            $this->fail('Nothing to close before opening.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('not opened yet', $e->getMessage());
        }

        Carbon::setTestNow('2026-10-28 21:00:00');
        $this->coins->closeDay($this->bartender);

        $this->expectExceptionMessage('already closed');
        $this->coins->closeDay($this->bartender);
    }

    public function test_reopening_restores_requests_and_only_tonight_can_be_reopened(): void
    {
        $this->given(1);
        $this->coins->closeDay($this->bartender);

        $this->coins->reopenDay($this->manager);

        $this->assertTrue($this->coins->availability()['open']);
        $this->assertNull(CoinDay::first()->closed_at);
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'coin_day_reopen']);
        $this->request();

        // Close again, then the session ends: the finished night cannot be reopened.
        $this->coins->closeDay($this->bartender);
        Carbon::setTestNow('2026-10-29 16:00:00');

        $this->expectExceptionMessage('not closed');
        $this->coins->reopenDay($this->bartender);
    }

    public function test_the_buttons_follow_the_state_of_the_night(): void
    {
        $this->actingAs($this->bartender);

        Livewire::test(CoinRequests::class)
            ->assertSeeHtml('data-testid="close-night"')
            ->assertDontSeeHtml('data-testid="reopen-night"')
            ->call('closeNight')
            ->assertSee('Closed for tonight')
            ->assertSeeHtml('data-testid="reopen-night"')
            ->assertDontSeeHtml('data-testid="close-night"')
            ->call('reopenNight')
            ->assertSeeHtml('data-testid="close-night"');

        // An attendant sees the state but no button.
        $this->actingAs($this->attendant);
        Livewire::test(CoinRequests::class)->assertDontSeeHtml('data-testid="close-night"');
    }

    // ------------------------------------------------------------------ day by day

    public function test_every_business_day_gets_an_entry_computed_from_its_requests(): void
    {
        // Wednesday: 3 + 2 given, one voided, one cancelled.
        $this->given(3);
        $this->given(2);
        $voided = $this->given(4);
        $this->coins->void($voided, $this->manager, 'Entered twice');
        $this->coins->cancel($this->request(1), $this->attendant);

        // Thursday
        Carbon::setTestNow('2026-10-29 17:00:00');
        $this->given(6);

        $rows = $this->coins->history()->items();

        $this->assertCount(2, $rows);
        $this->assertSame('2026-10-29', $rows[0]['date']->toDateString(), 'newest first');
        $this->assertSame(6, $rows[0]['coins']);
        $this->assertSame(60.0, $rows[0]['amount']);
        $this->assertTrue($rows[0]['tonight']);

        $this->assertSame('2026-10-28', $rows[1]['date']->toDateString());
        $this->assertSame(5, $rows[1]['coins']);
        $this->assertSame(50.0, $rows[1]['amount'], 'a voided request no longer counts');
        $this->assertSame(4, $rows[1]['requests'], 'two given, a voided and a cancelled one');
        $this->assertSame(1, $rows[1]['cancelled']);
        $this->assertSame(1, $rows[1]['voided']);
        $this->assertFalse($rows[1]['tonight']);
    }

    public function test_a_void_after_closing_is_reflected_in_the_day_but_the_snapshot_keeps_the_close(): void
    {
        $given = $this->given(5);
        $this->coins->closeDay($this->bartender);

        $this->coins->void($given, $this->manager, 'Wrong table');

        $row = $this->coins->history()->items()[0];
        $this->assertSame(0, $row['coins']);
        $this->assertTrue($row['closed']);
        $this->assertSame(5, (int) CoinDay::first()->closed_coins, 'the figure at the moment of closing');
    }

    public function test_a_closed_day_without_requests_still_has_an_entry(): void
    {
        $this->coins->closeDay($this->bartender);

        $rows = $this->coins->history()->items();

        $this->assertCount(1, $rows);
        $this->assertSame(0, $rows[0]['requests']);
        $this->assertTrue($rows[0]['closed']);
    }

    public function test_the_history_is_paginated(): void
    {
        foreach (range(1, 12) as $i) {
            CoinRequest::create([
                'product_id' => $this->snooker, 'product_name' => 'Snooker', 'quantity' => 1, 'status' => CoinRequest::STATUS_GIVEN,
                'business_date' => Carbon::parse('2026-09-01')->addDays($i)->toDateString(), 'requested_at' => now(), 'given_at' => now(), 'unit_price' => 10, 'total' => 10,
            ]);
        }

        $page = $this->coins->history(10);

        $this->assertSame(12, $page->total());
        $this->assertCount(10, $page->items());
    }

    public function test_the_day_detail_lists_games_attendants_and_every_request(): void
    {
        $this->given(3);
        $voided = $this->given(1, $this->manager);
        $this->coins->void($voided, $this->manager, 'Wrong table');
        $this->request(2); // still waiting

        $detail = $this->coins->dayDetail('2026-10-28');

        $this->assertSame(3, $detail['summary']['requests']);
        $this->assertSame(3, $detail['requests']->count());
        $this->assertSame(3, $detail['summary']['coins']);
        $this->assertSame(30.0, $detail['summary']['amount']);
        $this->assertSame(1, $detail['summary']['voided']);
        $this->assertSame(1, $detail['summary']['waiting']);
        $this->assertSame('Snooker', $detail['games']->first()['name']);
        $this->assertEqualsCanonicalizing(['Esi', 'Manager'], $detail['attendants']->pluck('name')->all());
    }

    public function test_the_history_and_the_view_window_are_for_the_bar_and_managers_only(): void
    {
        $this->given(2);

        $this->actingAs($this->attendant);
        Livewire::test(CoinRequests::class)
            ->assertDontSeeHtml('data-testid="coin-days"')
            ->call('openDayDetail', '2026-10-28')
            ->assertForbidden();

        $this->actingAs($this->bartender);
        Livewire::test(CoinRequests::class)
            ->assertSeeHtml('data-testid="coin-days"')
            ->assertSeeHtml('data-testid="view-coin-day"')
            ->assertDontSeeHtml('data-testid="coin-day-modal"')
            ->call('openDayDetail', '2026-10-28')
            ->assertSeeHtml('data-testid="coin-day-modal"')
            ->assertSee('Every request')
            ->assertSee('By attendant')
            ->assertSee('Esi')
            ->call('closeDayDetail')
            ->assertDontSeeHtml('data-testid="coin-day-modal"');

        $this->actingAs($this->manager);
        Livewire::test(CoinRequests::class)->assertSeeHtml('data-testid="coin-days"');
    }

    public function test_a_bad_date_for_the_view_window_is_not_found(): void
    {
        $this->actingAs($this->bartender);

        Livewire::test(CoinRequests::class)->call('openDayDetail', 'not-a-date')->assertNotFound();
    }
}

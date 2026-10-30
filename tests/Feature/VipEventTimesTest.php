<?php

namespace Tests\Feature;

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

/**
 * The pub works in sessions that run past midnight: Saturday 15:00 to Sunday 03:00, then Sunday
 * 15:00 to Monday 03:00. A price day set for Saturday must run until Sunday 03:00, and its optional
 * start/end times are placed on that session ("01:00" on a Saturday event means Sunday 01:00).
 */
class VipEventTimesTest extends TestCase
{
    use RefreshDatabase;

    private int $beer;

    private int $water;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-04 16:00:00'); // Wednesday
        config(['pricing.day_ends_at' => '03:00', 'pricing.opens_at' => '15:00']);

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

    /** A custom-price event: beer at 16.00 (normal 10.00). */
    private function beerEvent(string $start, ?string $end = null, ?string $startTime = null, ?string $endTime = null, string $price = '16'): PriceEvent
    {
        return $this->prices()->scheduleCustom($this->admin, $start, $end ?? $start, [$this->beer => $price], 'Event', $startTime, $endTime);
    }

    private function beerAt(string $moment): float
    {
        return $this->prices()->quoteNow(Product::find($this->beer), Carbon::parse($moment))['price'];
    }

    // 7 Nov 2026 is a Saturday.

    public function test_the_default_closing_hour_is_three_in_the_morning(): void
    {
        config(['pricing.day_ends_at' => null]);
        $this->assertSame('03:00', (include base_path('config/pricing.php'))['day_ends_at']);
    }

    public function test_a_saturday_event_covers_the_whole_session_until_sunday_three_am(): void
    {
        $this->beerEvent('2026-11-07');

        $this->assertSame(10.0, $this->beerAt('2026-11-07 02:59'), 'that is still Friday night\'s session');
        $this->assertSame(16.0, $this->beerAt('2026-11-07 15:00'), 'Saturday opening');
        $this->assertSame(16.0, $this->beerAt('2026-11-07 23:59'));
        $this->assertSame(16.0, $this->beerAt('2026-11-08 00:30'), 'after midnight is still Saturday\'s session');
        $this->assertSame(16.0, $this->beerAt('2026-11-08 02:59'));
        $this->assertSame(10.0, $this->beerAt('2026-11-08 03:00'), 'the session is over');
        $this->assertSame(10.0, $this->beerAt('2026-11-08 20:00'), 'Sunday\'s session is not covered');
    }

    public function test_orders_after_midnight_get_the_saturday_price(): void
    {
        $this->beerEvent('2026-11-07');
        $orders = app(OrderService::class);

        Carbon::setTestNow('2026-11-08 01:15:00');
        $late = $orders->place($this->admin, 'Kwame', '0241234567', [$this->beer => 1]);
        $this->assertSame('16.00', $late->items->first()->unit_price);

        Carbon::setTestNow('2026-11-08 03:05:00');
        $next = $orders->place($this->admin, 'Esi', '0245550000', [$this->beer => 1]);
        $this->assertSame('10.00', $next->items->first()->unit_price);
    }

    public function test_a_timed_window_runs_from_the_start_time_past_midnight_to_the_end_time(): void
    {
        $this->beerEvent('2026-11-07', null, '18:00', '01:00');

        $this->assertSame(10.0, $this->beerAt('2026-11-07 17:59'));
        $this->assertSame(16.0, $this->beerAt('2026-11-07 18:00'));
        $this->assertSame(16.0, $this->beerAt('2026-11-08 00:30'));
        $this->assertSame(10.0, $this->beerAt('2026-11-08 01:00'), 'the end time is exclusive');
        $this->assertSame(10.0, $this->beerAt('2026-11-08 02:00'));
    }

    public function test_an_end_time_equal_to_the_closing_hour_means_the_end_of_the_session(): void
    {
        $this->beerEvent('2026-11-07', null, '20:00', '03:00');

        $this->assertSame(10.0, $this->beerAt('2026-11-07 19:59'));
        $this->assertSame(16.0, $this->beerAt('2026-11-08 02:59'));
        $this->assertSame(10.0, $this->beerAt('2026-11-08 03:00'));
    }

    public function test_only_the_first_and_last_day_are_partial_when_an_event_spans_sessions(): void
    {
        $this->beerEvent('2026-11-07', '2026-11-09', '18:00', '01:00');

        $this->assertSame(10.0, $this->beerAt('2026-11-07 17:00'), 'before the first day\'s start time');
        $this->assertSame(16.0, $this->beerAt('2026-11-07 18:00'));
        $this->assertSame(16.0, $this->beerAt('2026-11-08 16:00'), 'the middle session is whole');
        $this->assertSame(16.0, $this->beerAt('2026-11-10 00:59'), 'last day until 01:00 (after midnight)');
        $this->assertSame(10.0, $this->beerAt('2026-11-10 01:00'));
    }

    public function test_times_are_validated(): void
    {
        $bad = [
            'not a time' => ['abc', null],
            'hour out of range' => ['25:00', null],
            'minutes out of range' => [null, '10:75'],
            'end before start' => ['18:00', '17:00'],
            'start after the end of the same night' => ['01:00', '18:00'],
            'same time' => ['18:00', '18:00'],
        ];

        foreach ($bad as $label => [$from, $until]) {
            try {
                $this->beerEvent('2026-11-07', null, $from, $until);
                $this->fail("Expected a refusal: $label");
            } catch (\DomainException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, PriceEvent::count());

        // Ending after midnight on the same night is fine.
        $this->beerEvent('2026-11-07', null, '18:00', '01:00');
        $this->assertSame(1, PriceEvent::count());
    }

    public function test_the_percentage_flow_takes_times_too(): void
    {
        $event = $this->prices()->schedule($this->admin, '2026-11-07', '2026-11-07', 20, 'Percent', '18:00', '01:00');

        $this->assertSame('18:00', $event->startTime());
        $this->assertSame('01:00', $event->endTime());
        $this->assertSame(12.0, $this->beerAt('2026-11-08 00:30'));
        $this->assertSame(10.0, $this->beerAt('2026-11-08 01:00'));
    }

    // ------------------------------------------------------------------ the end-of-day stock count

    private function countPrice(string $date): float
    {
        return $this->prices()->priceOn($this->beer, 10, $date);
    }

    public function test_the_stock_count_uses_the_full_event_price_for_a_whole_session_event(): void
    {
        $this->beerEvent('2026-11-07');

        $this->assertSame(16.0, $this->countPrice('2026-11-07'), 'Saturday\'s session, counted on Sunday');
        $this->assertSame(10.0, $this->countPrice('2026-11-08'));
    }

    public function test_an_event_covering_the_whole_trading_window_counts_at_the_full_price(): void
    {
        $this->beerEvent('2026-11-07', null, '15:00', '03:00');

        $this->assertSame(16.0, $this->countPrice('2026-11-07'));
    }

    public function test_a_part_session_event_is_blended_by_the_trading_hours_it_covers(): void
    {
        $this->beerEvent('2026-11-07', null, '21:00', null); // 21:00 to 03:00 = 6 of the 12 trading hours

        $this->assertSame(13.0, $this->countPrice('2026-11-07'), '10 + (16 - 10) x 6/12');
    }

    public function test_an_event_outside_trading_hours_does_not_change_the_count(): void
    {
        $this->beerEvent('2026-11-07', null, '04:00', '10:00');

        $this->assertSame(10.0, $this->countPrice('2026-11-07'));
        $this->assertNull($this->prices()->quoteOn($this->beer, 10, '2026-11-07')['event']);
    }

    // ------------------------------------------------------------------ overlap

    public function test_adjacent_windows_can_sit_side_by_side_but_overlapping_ones_clash(): void
    {
        $this->beerEvent('2026-11-07', null, '15:00', '18:00');
        $this->beerEvent('2026-11-07', null, '18:00', null, '14');

        $this->assertSame(16.0, $this->beerAt('2026-11-07 17:00'));
        $this->assertSame(14.0, $this->beerAt('2026-11-07 19:00'));

        foreach ([['17:00', '19:00'], [null, null]] as [$from, $until]) {
            try {
                $this->beerEvent('2026-11-07', null, $from, $until, '12');
                $this->fail('An overlapping window must be refused.');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('overlap', $e->getMessage());
            }
        }

        // A different night is unaffected.
        $this->beerEvent('2026-11-14', null, '17:00', '19:00');
        $this->assertSame(3, PriceEvent::count());
    }

    // ------------------------------------------------------------------ describing and status

    public function test_the_window_is_described_in_calendar_moments(): void
    {
        $this->assertSame('Sat 7 Nov 18:00 to Sun 8 Nov 01:00', $this->prices()->describeRun('2026-11-07', '2026-11-07', '18:00', '01:00'));
        $this->assertSame('Sat 7 Nov session, until Sun 8 Nov 03:00', $this->prices()->describeRun('2026-11-07', '2026-11-07'));
        $this->assertSame('Sat 7 Nov to Sun 8 Nov sessions, until Mon 9 Nov 03:00', $this->prices()->describeRun('2026-11-07', '2026-11-08'));
        $this->assertSame('Sat 7 Nov 15:00 to Sun 8 Nov 03:00', $this->prices()->describeRun('2026-11-07', '2026-11-07', '15:00', '03:00'));
    }

    public function test_a_timed_event_is_upcoming_active_then_ended_within_its_night(): void
    {
        $event = $this->beerEvent('2026-11-07', null, '18:00', '01:00');

        $at = fn (string $moment) => $event->statusOn(
            $this->prices()->businessDate(Carbon::parse($moment))->toDateString(),
            \App\Support\SessionClock::minuteOf(Carbon::parse($moment)),
        );

        $this->assertSame('upcoming', $at('2026-11-07 16:00'));
        $this->assertSame('active', $at('2026-11-07 18:00'));
        $this->assertSame('active', $at('2026-11-08 00:30'));
        $this->assertSame('ended', $at('2026-11-08 01:00'));
    }

    // ------------------------------------------------------------------ the form

    public function test_the_form_shows_what_the_times_mean_and_schedules_the_event(): void
    {
        $this->actingAs($this->admin);

        $page = Livewire::test(Products::class)
            ->call('openVipModal')
            ->set('vipStart', '2026-11-07')
            ->set('vipEnd', '2026-11-07')
            ->assertSee('Runs: Sat 7 Nov session, until Sun 8 Nov 03:00')
            ->set('vipStartTime', '18:00')
            ->set('vipEndTime', '01:00')
            ->assertSee('Runs: Sat 7 Nov 18:00 to Sun 8 Nov 01:00')
            ->set('vipPercent', '20')
            ->call('scheduleVipPrices')
            ->assertHasNoErrors()
            ->assertSet('showVipModal', false);

        $event = PriceEvent::firstOrFail();
        $this->assertSame('18:00', $event->startTime());
        $this->assertSame('01:00', $event->endTime());

        $page->call('openVipModal')->assertSee('Sat 7 Nov 18:00 to Sun 8 Nov 01:00');
    }

    public function test_the_custom_form_takes_times_and_invalid_times_are_rejected(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Products::class)
            ->call('openVipModal')
            ->call('setVipMode', 'custom')
            ->set('vipStart', '2026-11-07')
            ->set('vipEnd', '2026-11-07')
            ->set("customPrices.{$this->beer}", '16')
            ->set('vipStartTime', '99:99')
            ->call('scheduleVipPrices')
            ->assertHasErrors(['vipStartTime'])
            ->set('vipStartTime', '18:00')
            ->set('vipEndTime', '17:00')
            ->call('scheduleVipPrices')
            ->assertHasErrors('customPrices')
            ->set('vipEndTime', '01:00')
            ->call('scheduleVipPrices')
            ->assertHasNoErrors();

        $this->assertSame(1, PriceEvent::count());
        $this->assertSame('18:00', PriceEvent::first()->startTime());
    }

    public function test_events_without_times_keep_their_old_behaviour(): void
    {
        $event = $this->beerEvent('2026-11-07');

        $this->assertNull($event->startTime());
        $this->assertNull($event->endTime());
        $this->assertFalse($event->hasTimes());
        $this->assertSame('Sat 7 Nov 2026', $this->prices()->describeDays($event));
    }
}

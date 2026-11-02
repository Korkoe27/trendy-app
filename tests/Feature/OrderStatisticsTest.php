<?php

namespace Tests\Feature;

use App\Livewire\Pages\Analytics;
use App\Models\User;
use App\Services\OrderStatistics;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Staff (waiter and bar) and customer performance on the Analytics page, computed from orders. */
class OrderStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private int $ama;

    private int $kofi;

    private int $akua;

    private OrderStatistics $stats;

    protected function setUp(): void
    {
        parent::setUp();

        config(['pricing.day_ends_at' => '03:00']);
        DB::connection()->getPdo()->sqliteCreateFunction('TO_CHAR', fn ($value) => substr((string) $value, 0, 7));

        $this->ama = $this->user('Ama');
        $this->kofi = $this->user('Kofi');
        $this->akua = $this->user('Akua');
        $this->stats = app(OrderStatistics::class);
    }

    private function user(string $name): int
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Role '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);

        return User::forceCreate([
            'name' => $name, 'username' => strtolower($name).uniqid(), 'phone' => '+23324'.random_int(1000000, 9999999),
            'role_id' => $roleId, 'password' => '482913', 'must_change_password' => false,
        ])->id;
    }

    /**
     * @param  list<array{0:string,1:int}>  $items  [name, quantity] at 10 each
     */
    private function order(int $waiter, string $customer, string $phone, string $at, array $items, string $status = 'served', string $payment = 'unpaid', ?int $readyBy = null, ?string $readyAfter = null, ?string $servedAfter = null): int
    {
        $name = DB::table('users')->where('id', $waiter)->value('name');
        $created = Carbon::parse($at);
        $total = array_sum(array_map(fn ($i) => $i[1] * 10, $items));

        $orderId = DB::table('orders')->insertGetId([
            'customer_name' => $customer, 'customer_phone' => $phone, 'status' => $status, 'payment_status' => $payment,
            'total' => $total, 'taken_by' => $waiter, 'taken_by_name' => $name,
            'ready_at' => $readyAfter ? $created->copy()->modify($readyAfter) : null, 'ready_by' => $readyBy,
            'served_at' => $servedAfter ? $created->copy()->modify($servedAfter) : null,
            'created_at' => $created, 'updated_at' => $created,
        ]);

        foreach ($items as [$product, $quantity]) {
            DB::table('order_items')->insert([
                'order_id' => $orderId, 'product_name' => $product, 'unit_price' => 10, 'quantity' => $quantity, 'line_total' => $quantity * 10,
                'status' => 'ready', 'ready_at' => $readyAfter ? $created->copy()->modify($readyAfter) : null,
                'created_at' => $created, 'updated_at' => $created,
            ]);
        }

        return $orderId;
    }

    private function window(string $start = '2026-10-28', string $end = '2026-10-28'): array
    {
        return $this->stats->window($start, $end);
    }

    // ------------------------------------------------------------------ the window

    public function test_a_day_runs_from_the_closing_hour_to_the_next_one(): void
    {
        [$from, $to] = $this->window('2026-10-28', '2026-10-29');

        $this->assertSame('2026-10-28 03:00:00', $from->toDateTimeString());
        $this->assertSame('2026-10-30 03:00:00', $to->toDateTimeString());
    }

    // ------------------------------------------------------------------ staff

    public function test_each_waiter_gets_orders_items_sales_average_unpaid_and_cancellations(): void
    {
        $this->order($this->ama, 'Kwame', '+233241111111', '2026-10-28 18:00', [['Beer', 2], ['Water', 1]], 'closed', 'paid');           // 30
        $this->order($this->ama, 'Esi', '+233242222222', '2026-10-28 20:00', [['Beer', 1]], 'served', 'unpaid');                         // 10, unpaid
        $this->order($this->ama, 'Yaw', '+233243333333', '2026-10-28 21:00', [['Beer', 9]], 'cancelled');                                // ignored
        $this->order($this->kofi, 'Ama C', '+233244444444', '2026-10-28 19:00', [['Beer', 5]], 'closed', 'paid');                         // 50

        $staff = $this->stats->staff(...$this->window());
        [$kofi, $ama] = $staff['waiters'];

        $this->assertSame('Kofi', $kofi['name'], 'best sales first');
        $this->assertSame(50.0, $kofi['revenue']);

        $this->assertSame('Ama', $ama['name']);
        $this->assertSame(2, $ama['orders'], 'the cancelled order is not a sale');
        $this->assertSame(4, $ama['items']);
        $this->assertSame(40.0, $ama['revenue']);
        $this->assertSame(20.0, $ama['average_order']);
        $this->assertSame(1, $ama['cancelled']);
        $this->assertSame(10.0, $ama['outstanding']);
        $this->assertSame(44.4, $ama['share'], '40 of 90');

        $this->assertSame(3, $staff['totals']['orders']);
        $this->assertSame(90.0, $staff['totals']['revenue']);
    }

    public function test_orders_after_midnight_belong_to_the_previous_day_and_other_days_are_left_out(): void
    {
        $this->order($this->ama, 'Late', '+233241111111', '2026-10-29 01:30', [['Beer', 1]]);   // Wednesday's session
        $this->order($this->ama, 'Next', '+233242222222', '2026-10-29 03:00', [['Beer', 1]]);   // Thursday's
        $this->order($this->ama, 'Before', '+233243333333', '2026-10-28 02:59', [['Beer', 1]]); // Tuesday's

        $this->assertSame(1, $this->stats->staff(...$this->window('2026-10-28', '2026-10-28'))['waiters'][0]['orders']);
        $this->assertSame(3, $this->stats->staff(...$this->window('2026-10-27', '2026-10-29'))['waiters'][0]['orders']);
    }

    public function test_speed_from_ready_to_served_and_the_bars_waiting_time(): void
    {
        $this->order($this->ama, 'A', '+233241111111', '2026-10-28 18:00', [['Beer', 1]], 'served', 'unpaid', $this->akua, '+4 minutes', '+10 minutes');   // ready after 4, served 6 later
        $this->order($this->ama, 'B', '+233242222222', '2026-10-28 19:00', [['Beer', 1]], 'served', 'unpaid', $this->akua, '+6 minutes', '+10 minutes');   // ready after 6, served 4 later
        $this->order($this->ama, 'C', '+233243333333', '2026-10-28 20:00', [['Beer', 1]], 'pending');                                                         // not prepared yet

        $staff = $this->stats->staff(...$this->window());

        $this->assertSame(5.0, $staff['waiters'][0]['serve_minutes']);
        $this->assertSame('Akua', $staff['bar'][0]['name']);
        $this->assertSame(2, $staff['bar'][0]['orders']);
        $this->assertSame(5.0, $staff['bar'][0]['prep_minutes']);
    }

    public function test_a_waiter_with_nothing_served_has_no_speed_figure(): void
    {
        $this->order($this->ama, 'A', '+233241111111', '2026-10-28 18:00', [['Beer', 1]], 'pending');

        $this->assertNull($this->stats->staff(...$this->window())['waiters'][0]['serve_minutes']);
    }

    // ------------------------------------------------------------------ customers

    public function test_customers_are_matched_by_phone_and_summarised(): void
    {
        $this->order($this->ama, 'Kwame', '+233241111111', '2026-10-28 18:00', [['Beer', 2]], 'closed', 'paid');                    // 20
        $this->order($this->kofi, 'Kwame M.', '+233241111111', '2026-10-28 22:00', [['Beer', 3]], 'served', 'unpaid');                // 30 unpaid, same phone another waiter
        $this->order($this->ama, 'Esi', '+233242222222', '2026-10-28 19:00', [['Beer', 1]], 'closed', 'paid');                       // 10
        $this->order($this->ama, 'Yaw', '+233243333333', '2026-10-28 20:00', [['Beer', 9]], 'cancelled');                            // ignored

        $result = $this->stats->customers(...$this->window());

        $this->assertSame(2, $result['summary']['customers']);
        $this->assertSame(1, $result['summary']['repeat']);
        $this->assertSame(50.0, $result['summary']['repeat_rate']);
        $this->assertSame(30.0, $result['summary']['average_spend'], '60 across 2 customers');
        $this->assertSame(30.0, $result['summary']['outstanding']);

        $top = $result['top_spenders'][0];
        $this->assertSame('0241111111', $top['phone']);
        $this->assertSame('Kwame M.', $top['name'], 'the name on their latest order');
        $this->assertSame(2, $top['orders']);
        $this->assertSame(5, $top['items']);
        $this->assertSame(50.0, $top['spent']);
        $this->assertSame(25.0, $top['average_order']);
        $this->assertSame(30.0, $top['outstanding']);
        $this->assertSame('2026-10-28', $top['last_visit']);
    }

    public function test_the_most_frequent_list_is_ordered_by_visits(): void
    {
        $this->order($this->ama, 'Big', '+233241111111', '2026-10-28 18:00', [['Beer', 20]], 'closed', 'paid'); // one big visit
        foreach (['19:00', '20:00', '21:00'] as $time) {
            $this->order($this->ama, 'Regular', '+233242222222', "2026-10-28 {$time}", [['Beer', 1]], 'closed', 'paid');
        }

        $result = $this->stats->customers(...$this->window());

        $this->assertSame('Big', $result['top_spenders'][0]['name']);
        $this->assertSame('Regular', $result['most_frequent'][0]['name']);
    }

    public function test_the_lists_are_limited(): void
    {
        foreach (range(1, 14) as $i) {
            $this->order($this->ama, "C{$i}", sprintf('+2332455%05d', $i), '2026-10-28 18:00', [['Beer', $i]], 'closed', 'paid');
        }

        $result = $this->stats->customers(...$this->window());

        $this->assertSame(14, $result['summary']['customers']);
        $this->assertCount(10, $result['top_spenders']);
    }

    public function test_an_empty_period_gives_zeroes_not_errors(): void
    {
        $this->assertSame([], $this->stats->staff(...$this->window())['waiters']);

        $summary = $this->stats->customers(...$this->window())['summary'];
        $this->assertSame(0, $summary['customers']);
        $this->assertSame(0.0, $summary['repeat_rate']);
    }

    // ------------------------------------------------------------------ the page

    public function test_the_analytics_page_shows_both_sections_for_the_chosen_dates(): void
    {
        $this->order($this->ama, 'Kwame Mensah', '+233241111111', '2026-10-28 18:00', [['Beer', 2]], 'closed', 'paid', $this->akua, '+3 minutes', '+8 minutes');

        $roleId = DB::table('roles')->insertGetId(['name' => 'Viewer '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $viewer = User::forceCreate(['name' => 'Viewer', 'username' => 'viewer'.uniqid(), 'phone' => '+233249999999', 'role_id' => $roleId, 'password' => '482913', 'must_change_password' => false]);
        $this->actingAs($viewer);

        Livewire::test(Analytics::class)
            ->set('timeframe', 'custom')
            ->set('startDate', '2026-10-28')
            ->set('endDate', '2026-10-28')
            ->call('loadCharts')
            ->assertSeeHtml('data-testid="staff-performance"')
            ->assertSeeHtml('data-testid="customer-performance"')
            ->assertSeeHtml('data-testid="bar-performance"')
            ->assertSee('Ama')
            ->assertSee('Akua')
            ->assertSee('Kwame Mensah')
            ->assertSee('0241111111')
            ->assertSee('GH₵ 20.00')
            ->set('customerView', 'visits')
            ->assertSee('Most frequent');
    }
}

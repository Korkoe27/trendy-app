<?php

namespace Tests\Feature;

use App\Livewire\Pages\Analytics;
use App\Models\User;
use App\Services\DebtLedger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Paying debt must bring the loss shown on the Analytics page back down. */
class AnalyticsDebtRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        // Analytics' month picker uses PostgreSQL's TO_CHAR; give the SQLite test database an equivalent.
        DB::connection()->getPdo()->sqliteCreateFunction('TO_CHAR', fn ($value) => substr((string) $value, 0, 7));

        $roleId = DB::table('roles')->insertGetId(['name' => 'Analytics Tester', 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('categories')->insertGetId(['name' => 'Drinks', 'created_at' => now(), 'updated_at' => now()]);
        $productId = DB::table('products')->insertGetId(['name' => 'Water', 'category_id' => $categoryId, 'created_at' => now(), 'updated_at' => now()]);

        $this->staff = User::forceCreate([
            'name' => 'Ama', 'username' => 'ama', 'phone' => '0241112222', 'role_id' => $roleId,
            'password' => '482913', 'must_change_password' => false,
        ]);
        $this->actingAs($this->staff);

        // 3 March: expected 500, collected 400 (short 100). 4 March: over by 50. 5 March: expected 230, collected 200 (short 30).
        foreach ([['2026-03-03', 500, 400, 0], ['2026-03-04', 300, 350, 0], ['2026-03-05', 250, 200, 20]] as [$date, $drinks, $collected, $onTheHouse]) {
            DB::table('daily_sales_summaries')->insert([
                'sales_date' => $date, 'drinks_total' => $drinks, 'food_total' => 0, 'on_the_house' => $onTheHouse,
                'total_cash' => $collected, 'total_momo' => 0, 'total_hubtel' => 0, 'product_id' => $productId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function pay(float $amount): void
    {
        (new DebtLedger)->recordPayment(Carbon::create(2026, 3, 1), 'Kofi Mensah', null, $amount, 'cash', Carbon::create(2026, 4, 10), null, $this->staff);
    }

    private function analytics()
    {
        return Livewire::test(Analytics::class)
            ->set('timeframe', 'custom')
            ->set('startDate', '2026-03-01')
            ->set('endDate', '2026-03-31');
    }

    public function test_total_loss_is_the_gross_figure_until_debt_is_paid(): void
    {
        $analytics = $this->analytics();

        // -100 + 50 - 30 = -80
        $this->assertEqualsWithDelta(-80, (float) $analytics->get('accumulatedLosses'), 0.01);
        $this->assertEqualsWithDelta(0, (float) $analytics->get('debtRecovered'), 0.01);
        $this->assertEqualsWithDelta(-80, (float) $analytics->get('netLosses'), 0.01);
        $analytics->call('loadCharts')->assertDontSee('Recovered from debt payments');
    }

    public function test_a_debt_payment_lowers_the_loss_on_the_page(): void
    {
        $this->pay(100);

        $analytics = $this->analytics();

        $this->assertEqualsWithDelta(-80, (float) $analytics->get('accumulatedLosses'), 0.01);
        $this->assertEqualsWithDelta(100, (float) $analytics->get('debtRecovered'), 0.01);
        $this->assertEqualsWithDelta(20, (float) $analytics->get('netLosses'), 0.01);

        $analytics->call('loadCharts') // the discrepancies card sits with the charts
            ->assertSee('Recovered from debt payments')
            ->assertSee('GH₵ 100.00')
            ->assertSee('Before debt payments: GH₵ -80.00');
    }

    public function test_paying_the_whole_debt_leaves_only_the_over_collection(): void
    {
        $this->pay(130);

        $this->assertEqualsWithDelta(50, (float) $this->analytics()->get('netLosses'), 0.01);
    }

    public function test_voiding_the_payment_puts_the_loss_back(): void
    {
        $this->pay(100);
        $payment = \App\Models\DebtPayment::firstOrFail();

        (new DebtLedger)->void($payment, 'Entered by mistake', $this->staff);

        $this->assertEqualsWithDelta(-80, (float) $this->analytics()->get('netLosses'), 0.01);
    }

    public function test_a_range_outside_the_paid_month_is_not_affected(): void
    {
        $this->pay(130);

        $analytics = Livewire::test(Analytics::class)
            ->set('timeframe', 'custom')
            ->set('startDate', '2026-02-01')
            ->set('endDate', '2026-02-28');

        $this->assertEqualsWithDelta(0, (float) $analytics->get('debtRecovered'), 0.01);
    }
}

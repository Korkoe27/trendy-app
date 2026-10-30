<?php

namespace Tests\Feature;

use App\Models\DebtPayment;
use App\Models\User;
use App\Services\DebtLedger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DebtLedgerTest extends TestCase
{
    use RefreshDatabase;

    private DebtLedger $ledger;

    private User $staff;

    private Carbon $month;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = new DebtLedger;
        $this->month = Carbon::create(2026, 3, 1);

        $roleId = DB::table('roles')->insertGetId(['name' => 'Ledger Tester', 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('categories')->insertGetId(['name' => 'Drinks', 'created_at' => now(), 'updated_at' => now()]);
        $this->productId = DB::table('products')->insertGetId(['name' => 'Water', 'category_id' => $categoryId, 'created_at' => now(), 'updated_at' => now()]);

        $this->staff = User::forceCreate([
            'name' => 'Ama Bartender',
            'username' => 'ama',
            'role_id' => $roleId,
            'password' => 'secret',
        ]);

        // Day 1: expected 500, collected 400 -> shortfall 100
        $this->summary('2026-03-03', drinks: 500, cash: 300, momo: 100);
        // Day 2: expected 300, collected 350 -> over-collected by 50, offsets the short days (net debt 80)
        $this->summary('2026-03-04', drinks: 300, cash: 350);
        // Day 3: expected 200 (food 50, on the house 20 -> 230), collected 200 -> shortfall 30
        $this->summary('2026-03-05', drinks: 200, food: 50, onTheHouse: 20, hubtel: 200);
        // Another month, must be ignored
        $this->summary('2026-04-01', drinks: 999, cash: 0);
    }

    private function summary(string $date, float $drinks, float $cash = 0, float $momo = 0, float $hubtel = 0, float $food = 0, float $onTheHouse = 0): void
    {
        DB::table('daily_sales_summaries')->insert([
            'sales_date' => $date,
            'drinks_total' => $drinks,
            'food_total' => $food,
            'on_the_house' => $onTheHouse,
            'total_cash' => $cash,
            'total_momo' => $momo,
            'total_hubtel' => $hubtel,
            'product_id' => $this->productId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pay(float $amount, ?Carbon $month = null): DebtPayment
    {
        return $this->ledger->recordPayment(
            $month ?? $this->month,
            'Kofi Mensah',
            '0240000000',
            $amount,
            'cash',
            Carbon::create(2026, 4, 10),
            null,
            $this->staff,
        );
    }

    public function test_monthly_debt_nets_over_collected_days_against_short_days(): void
    {
        // 100 short - 50 over + 30 short
        $this->assertSame(80.0, $this->ledger->monthlyDebt($this->month));
        $this->assertSame(80.0, $this->ledger->monthlyNet($this->month));

        $days = $this->ledger->dailyDifferences($this->month);
        $this->assertCount(3, $days);
        $this->assertSame([100.0, -50.0, 30.0], $days->pluck('difference')->all());
    }

    public function test_debt_is_the_same_figure_as_analytics_total_loss(): void
    {
        // Analytics' formula: sum of (collected - expected) over the month's summaries.
        $analyticsLoss = DB::table('daily_sales_summaries')
            ->whereBetween('sales_date', ['2026-03-01', '2026-03-31'])
            ->get()
            ->sum(fn ($s) => ($s->total_cash + $s->total_momo + $s->total_hubtel) - ($s->drinks_total + $s->food_total - $s->on_the_house));

        $this->assertEqualsWithDelta(-$analyticsLoss, $this->ledger->monthlyDebt($this->month), 0.001);
    }

    public function test_a_month_that_is_net_over_collected_has_no_debt(): void
    {
        $this->summary('2026-05-02', drinks: 100, cash: 300); // over by 200
        $this->summary('2026-05-03', drinks: 100, cash: 50);  // short by 50
        $may = Carbon::create(2026, 5, 1);

        $this->assertSame(0.0, $this->ledger->monthlyDebt($may));
        $this->assertSame(-150.0, $this->ledger->monthlyNet($may));

        $this->expectException(\DomainException::class);
        $this->pay(10, $may);
    }

    public function test_cents_are_not_lost_to_per_day_rounding(): void
    {
        $this->summary('2026-06-02', drinks: 10.004, cash: 0);
        $this->summary('2026-06-03', drinks: 10.004, cash: 0);

        // Two days each 10.004 short: 20.008 -> 20.01, like Analytics would show.
        $this->assertSame(20.01, $this->ledger->monthlyDebt(Carbon::create(2026, 6, 1)));
    }

    public function test_partial_payment_reduces_outstanding_and_records_staff(): void
    {
        $payment = $this->pay(50);

        $this->assertSame(30.0, $this->ledger->outstanding($this->month));
        $this->assertSame('Ama Bartender', $payment->received_by_name);
        $this->assertSame($this->staff->id, $payment->received_by);
        $this->assertEquals(80.0, (float) $payment->outstanding_before);
        $this->assertEquals(30.0, (float) $payment->outstanding_after);
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'debt_payment_create', 'entity_id' => $payment->id]);
    }

    public function test_payments_can_clear_the_month_exactly(): void
    {
        $this->pay(50);
        $this->pay(30);

        $summary = $this->ledger->summary($this->month);
        $this->assertSame(0.0, $summary['outstanding']);
        $this->assertSame(80.0, $summary['paid']);
    }

    public function test_overpayment_is_rejected(): void
    {
        $this->pay(50);

        $this->expectException(\DomainException::class);
        $this->pay(31);
    }

    public function test_zero_and_negative_amounts_are_rejected(): void
    {
        foreach ([0, -5] as $amount) {
            try {
                $this->pay($amount);
                $this->fail('Expected DomainException for amount '.$amount);
            } catch (\DomainException) {
                $this->assertSame(0, DebtPayment::count());
            }
        }
    }

    public function test_cannot_pay_a_month_with_no_debt(): void
    {
        $this->expectException(\DomainException::class);
        $this->pay(10, Carbon::create(2026, 2, 1));
    }

    public function test_voiding_restores_outstanding(): void
    {
        $payment = $this->pay(50);
        $this->assertSame(30.0, $this->ledger->outstanding($this->month));

        $this->ledger->void($payment, 'Wrong month', $this->staff);

        $this->assertSame(80.0, $this->ledger->outstanding($this->month));
        $this->assertNotNull($payment->fresh()->voided_at);
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'debt_payment_void', 'entity_id' => $payment->id]);
    }

    public function test_void_requires_a_reason_and_cannot_repeat(): void
    {
        $payment = $this->pay(10);

        try {
            $this->ledger->void($payment, '  ', $this->staff);
            $this->fail('Expected DomainException for empty reason');
        } catch (\DomainException) {
            // expected
        }

        $this->ledger->void($payment, 'Mistake', $this->staff);

        $this->expectException(\DomainException::class);
        $this->ledger->void($payment, 'Again', $this->staff);
    }

    public function test_editing_a_closed_day_changes_debt_but_not_recorded_payments(): void
    {
        $this->pay(20);

        // Day 1 is corrected after the fact: collected is now 450 -> shortfall 50 instead of 100
        DB::table('daily_sales_summaries')->where('sales_date', '2026-03-03')->update(['total_cash' => 350]);

        $this->assertSame(30.0, $this->ledger->monthlyDebt($this->month));
        $this->assertSame(20.0, $this->ledger->paidFor($this->month));
        $this->assertSame(10.0, $this->ledger->outstanding($this->month));
    }

    public function test_overpaid_after_edit_is_reported_not_hidden(): void
    {
        $this->pay(80);

        // Day 1 now fully collected: net becomes -20 (over-collected), so there is no debt left.
        DB::table('daily_sales_summaries')->where('sales_date', '2026-03-03')->update(['total_cash' => 400]);

        $summary = $this->ledger->summary($this->month);
        $this->assertSame(0.0, $summary['outstanding']);
        $this->assertSame(80.0, $summary['overpaid']);
    }

    // ---------------------------------------------------------------- recovered loss (Analytics)

    private function recovered(string $from, string $to): float
    {
        return $this->ledger->recoveredBetween(Carbon::parse($from), Carbon::parse($to));
    }

    public function test_nothing_is_recovered_until_a_payment_is_made(): void
    {
        $this->assertSame(0.0, $this->recovered('2026-03-01', '2026-03-31'));
    }

    public function test_a_payment_recovers_that_much_of_the_whole_month(): void
    {
        $this->pay(50);
        $this->assertSame(50.0, $this->recovered('2026-03-01', '2026-03-31'));

        $this->pay(30);
        $this->assertSame(80.0, $this->recovered('2026-03-01', '2026-03-31'));
    }

    public function test_a_part_month_range_gets_its_proportional_share(): void
    {
        $this->pay(65);

        // Spread over the short days: 3 March carries 100 of the 130 short, 5 March carries 30.
        $this->assertSame(50.0, $this->recovered('2026-03-01', '2026-03-03'));
        $this->assertSame(15.0, $this->recovered('2026-03-04', '2026-03-31'));
        $this->assertSame(0.0, $this->recovered('2026-03-04', '2026-03-04'), 'An over-collected day recovers nothing');
        $this->assertSame(0.0, $this->recovered('2026-03-06', '2026-03-31'), 'No shortfall in range, nothing recovered');
    }

    public function test_it_never_exceeds_the_shortfall_in_the_range(): void
    {
        $this->pay(80);

        $this->assertSame(61.54, $this->recovered('2026-03-01', '2026-03-03')); // 80 x 100/130
        $this->assertSame(18.46, $this->recovered('2026-03-04', '2026-03-31')); // 80 x 30/130
        $this->assertSame(80.0, $this->recovered('2026-02-01', '2026-03-31'));
        $this->assertEqualsWithDelta(80.0, $this->recovered('2026-03-01', '2026-03-03') + $this->recovered('2026-03-04', '2026-03-31'), 0.01);
    }

    public function test_overpayment_after_an_edit_is_capped_at_the_month_debt(): void
    {
        $this->pay(80);
        DB::table('daily_sales_summaries')->where('sales_date', '2026-03-03')->update(['total_cash' => 350]); // day 1 short 50 now, net debt 30

        $this->assertSame(30.0, $this->recovered('2026-03-01', '2026-03-31'));
    }

    public function test_voided_payments_do_not_count(): void
    {
        $payment = $this->pay(50);
        $this->assertSame(50.0, $this->recovered('2026-03-01', '2026-03-31'));

        $this->ledger->void($payment, 'Wrong month', $this->staff);

        $this->assertSame(0.0, $this->recovered('2026-03-01', '2026-03-31'));
    }

    public function test_a_range_spanning_two_months_sums_each_months_recovery(): void
    {
        $this->pay(80);
        // April has a 999 shortfall on the 1st and nothing paid yet.
        $this->assertSame(80.0, $this->recovered('2026-03-01', '2026-04-30'));

        $this->ledger->recordPayment(Carbon::create(2026, 4, 1), 'Kofi', null, 500, 'cash', Carbon::create(2026, 4, 10), null, $this->staff);
        $this->assertSame(580.0, $this->recovered('2026-03-01', '2026-04-30'));
    }
}

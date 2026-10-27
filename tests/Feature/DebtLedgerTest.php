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

        $roleId = DB::table('roles')->insertGetId(['name' => 'Bartender', 'created_at' => now(), 'updated_at' => now()]);
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
        // Day 2: expected 300, collected 350 -> over-collected, must NOT offset other days
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

    public function test_monthly_debt_counts_only_shortfall_days(): void
    {
        $this->assertSame(130.0, $this->ledger->monthlyDebt($this->month));
        $this->assertCount(2, $this->ledger->dailyShortfalls($this->month));
    }

    public function test_partial_payment_reduces_outstanding_and_records_staff(): void
    {
        $payment = $this->pay(50);

        $this->assertSame(80.0, $this->ledger->outstanding($this->month));
        $this->assertSame('Ama Bartender', $payment->received_by_name);
        $this->assertSame($this->staff->id, $payment->received_by);
        $this->assertEquals(130.0, (float) $payment->outstanding_before);
        $this->assertEquals(80.0, (float) $payment->outstanding_after);
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'debt_payment_create', 'entity_id' => $payment->id]);
    }

    public function test_payments_can_clear_the_month_exactly(): void
    {
        $this->pay(100);
        $this->pay(30);

        $summary = $this->ledger->summary($this->month);
        $this->assertSame(0.0, $summary['outstanding']);
        $this->assertSame(130.0, $summary['paid']);
    }

    public function test_overpayment_is_rejected(): void
    {
        $this->pay(100);

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
        $payment = $this->pay(100);
        $this->assertSame(30.0, $this->ledger->outstanding($this->month));

        $this->ledger->void($payment, 'Wrong month', $this->staff);

        $this->assertSame(130.0, $this->ledger->outstanding($this->month));
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
        $this->pay(40);

        // Day 1 is corrected after the fact: collected is now 450 -> shortfall 50 instead of 100
        DB::table('daily_sales_summaries')->where('sales_date', '2026-03-03')->update(['total_cash' => 350]);

        $this->assertSame(80.0, $this->ledger->monthlyDebt($this->month));
        $this->assertSame(40.0, $this->ledger->paidFor($this->month));
        $this->assertSame(40.0, $this->ledger->outstanding($this->month));
    }

    public function test_overpaid_after_edit_is_reported_not_hidden(): void
    {
        $this->pay(130);

        DB::table('daily_sales_summaries')->where('sales_date', '2026-03-03')->update(['total_cash' => 400]);

        $summary = $this->ledger->summary($this->month);
        $this->assertSame(0.0, $summary['outstanding']);
        $this->assertSame(100.0, $summary['overpaid']);
    }
}

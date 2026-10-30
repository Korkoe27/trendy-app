<?php

namespace App\Services;

use App\Models\ActivityLogs;
use App\Models\DebtPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DebtLedger
{
    public const METHODS = ['cash', 'momo', 'hubtel'];

    /**
     * Days in the month where money collected was less than expected from the stock count.
     * Uses the same expected/collected formula as Analytics::calculateLossMetrics().
     */
    public function dailyShortfalls(Carbon $month): Collection
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        return DB::table('daily_sales_summaries')
            ->whereBetween('sales_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('sales_date')
            ->get()
            ->map(function ($summary) {
                $expected = (float) $summary->drinks_total
                    + (float) $summary->food_total
                    - (float) $summary->on_the_house;

                $collected = (float) $summary->total_cash
                    + (float) $summary->total_momo
                    + (float) $summary->total_hubtel;

                return [
                    'date' => $summary->sales_date,
                    'expected' => round($expected, 2),
                    'collected' => round($collected, 2),
                    'shortfall' => round($expected - $collected, 2),
                ];
            })
            ->filter(fn ($day) => $day['shortfall'] >= 0.01)
            ->values();
    }

    public function monthlyDebt(Carbon $month): float
    {
        return round($this->dailyShortfalls($month)->sum('shortfall'), 2);
    }

    public function paidFor(Carbon $month): float
    {
        return round((float) DebtPayment::active()
            ->whereDate('period_month', $month->copy()->startOfMonth()->toDateString())
            ->sum('amount'), 2);
    }

    /**
     * How much of the shortfall inside a date range has since been paid back, for Analytics.
     *
     * A payment belongs to the month whose debt it settles. Within each month the paid amount is
     * spread over that month's shortfall days in proportion, so a full month recovers exactly what
     * was paid and a part-month range (the last 7 days) gets its share. Overpayment never counts,
     * so the result can never exceed the shortfall inside the range.
     */
    public function recoveredBetween(Carbon $start, Carbon $end): float
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();
        $recovered = 0.0;

        for ($month = $start->copy()->startOfMonth(); $month->lte($end); $month->addMonth()) {
            $shortfalls = $this->dailyShortfalls($month);
            $monthDebt = (float) $shortfalls->sum('shortfall');

            if ($monthDebt <= 0) {
                continue;
            }

            $paid = min($this->paidFor($month), $monthDebt);

            if ($paid <= 0) {
                continue;
            }

            $inRange = (float) $shortfalls
                ->filter(fn ($day) => Carbon::parse($day['date'])->betweenIncluded($start, $end))
                ->sum('shortfall');

            $recovered += $paid * ($inRange / $monthDebt);
        }

        return round($recovered, 2);
    }

    /**
     * Can be negative if a later edit to a closed day shrank the month's debt below what
     * has already been paid.
     */
    public function outstanding(Carbon $month): float
    {
        return round($this->monthlyDebt($month) - $this->paidFor($month), 2);
    }

    public function summary(Carbon $month): array
    {
        $debt = $this->monthlyDebt($month);
        $paid = $this->paidFor($month);
        $outstanding = round($debt - $paid, 2);

        return [
            'debt' => $debt,
            'paid' => $paid,
            'outstanding' => max(0.0, $outstanding),
            'overpaid' => max(0.0, -$outstanding),
        ];
    }

    /**
     * @throws \DomainException when the payment is invalid (message is safe to show to staff)
     */
    public function recordPayment(
        Carbon $month,
        string $payerName,
        ?string $payerPhone,
        float $amount,
        string $method,
        Carbon $paidAt,
        ?string $notes,
        User $staff,
    ): DebtPayment {
        $amount = round($amount, 2);
        $month = $month->copy()->startOfMonth();

        if (! in_array($method, self::METHODS, true)) {
            throw new \DomainException('Invalid payment method.');
        }

        if ($amount <= 0) {
            throw new \DomainException('Amount must be greater than zero.');
        }

        return DB::transaction(function () use ($month, $payerName, $payerPhone, $amount, $method, $paidAt, $notes, $staff) {
            $this->lockMonth($month);

            // Re-check inside the lock so two staff cannot over-collect the same month at once.
            $outstanding = $this->outstanding($month);

            if ($outstanding <= 0) {
                throw new \DomainException('There is no outstanding debt for '.$month->format('F Y').'.');
            }

            if ($amount - $outstanding > 0.001) {
                throw new \DomainException('Amount exceeds the outstanding debt of ₵'.number_format($outstanding, 2).' for '.$month->format('F Y').'.');
            }

            $payment = DebtPayment::create([
                'period_month' => $month->toDateString(),
                'payer_name' => trim($payerName),
                'payer_phone' => $payerPhone ? trim($payerPhone) : null,
                'amount' => $amount,
                'payment_method' => $method,
                'paid_at' => $paidAt->toDateString(),
                'notes' => $notes ? trim($notes) : null,
                'received_by' => $staff->id,
                'received_by_name' => $staff->name ?? 'Unknown',
                'outstanding_before' => $outstanding,
                'outstanding_after' => round($outstanding - $amount, 2),
            ]);

            ActivityLogs::create([
                'user_id' => $staff->id,
                'action_type' => 'debt_payment_create',
                'description' => 'Recorded debt payment of ₵'.number_format($amount, 2).' from '.$payment->payer_name.' for '.$month->format('F Y'),
                'entity_type' => 'debt_payment',
                'entity_id' => $payment->id,
                'metadata' => [
                    'period_month' => $month->format('Y-m'),
                    'amount' => $amount,
                    'payment_method' => $method,
                    'outstanding_before' => $outstanding,
                    'outstanding_after' => $payment->outstanding_after,
                    'timestamp' => now(),
                ],
            ]);

            return $payment;
        });
    }

    /**
     * @throws \DomainException
     */
    public function void(DebtPayment $payment, string $reason, User $staff): DebtPayment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \DomainException('Please give a reason for voiding this payment.');
        }

        return DB::transaction(function () use ($payment, $reason, $staff) {
            $this->lockMonth($payment->period_month);

            $payment = DebtPayment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->isVoided()) {
                throw new \DomainException('This payment has already been voided.');
            }

            $payment->update([
                'voided_at' => now(),
                'voided_by' => $staff->id,
                'void_reason' => $reason,
            ]);

            ActivityLogs::create([
                'user_id' => $staff->id,
                'action_type' => 'debt_payment_void',
                'description' => 'Voided debt payment of ₵'.number_format((float) $payment->amount, 2).' from '.$payment->payer_name,
                'entity_type' => 'debt_payment',
                'entity_id' => $payment->id,
                'metadata' => [
                    'period_month' => $payment->period_month->format('Y-m'),
                    'amount' => (float) $payment->amount,
                    'reason' => $reason,
                    'timestamp' => now(),
                ],
            ]);

            return $payment;
        });
    }

    private function lockMonth(Carbon $month): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(?)', [crc32('debt_payments_'.$month->format('Y-m'))]);
        }
    }
}

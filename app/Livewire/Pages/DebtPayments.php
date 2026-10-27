<?php

namespace App\Livewire\Pages;

use App\Models\DebtPayment;
use App\Services\DebtLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class DebtPayments extends Component
{
    use WithPagination;

    public $selectedMonth = '';

    public $allMonths = false;

    public $searchTerm = '';

    public $showPaymentModal = false;

    public $paymentMonth = '';

    public $payerName = '';

    public $payerPhone = '';

    public $amount = '';

    public $paymentMethod = 'cash';

    public $paidAt = '';

    public $notes = '';

    public $showVoidModal = false;

    public $voidingPaymentId = null;

    public $voidReason = '';

    protected $queryString = ['selectedMonth', 'searchTerm'];

    public function mount()
    {
        $this->selectedMonth = $this->normalizeMonth($this->selectedMonth);
    }

    protected function rules()
    {
        return [
            'paymentMonth' => 'required|date_format:Y-m|before_or_equal:'.now()->format('Y-m'),
            'payerName' => 'required|string|max:255',
            'payerPhone' => 'nullable|string|max:30',
            'amount' => 'required|numeric|min:0.01',
            'paymentMethod' => 'required|in:'.implode(',', DebtLedger::METHODS),
            'paidAt' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:500',
        ];
    }

    protected $messages = [
        'paymentMonth.before_or_equal' => 'You cannot settle debt for a future month.',
        'paidAt.before_or_equal' => 'The payment date cannot be in the future.',
        'payerName.required' => 'Enter the name of the person paying.',
    ];

    public function updatedSelectedMonth()
    {
        $this->selectedMonth = $this->normalizeMonth($this->selectedMonth);
        $this->resetPage();
    }

    public function updatedSearchTerm()
    {
        $this->resetPage();
    }

    public function updatedAllMonths()
    {
        $this->resetPage();
    }

    public function openPaymentModal()
    {
        abort_unless(Auth::user()?->hasPermission('create', 'debt_payments'), 403);

        $this->resetValidation();
        $this->reset(['payerName', 'payerPhone', 'amount', 'notes']);
        $this->paymentMethod = 'cash';
        $this->paymentMonth = $this->selectedMonth;
        $this->paidAt = now()->format('Y-m-d');
        $this->showPaymentModal = true;
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->resetValidation();
    }

    public function savePayment(DebtLedger $ledger)
    {
        abort_unless(Auth::user()?->hasPermission('create', 'debt_payments'), 403);

        $this->validate();

        try {
            $payment = $ledger->recordPayment(
                Carbon::createFromFormat('Y-m-d', $this->paymentMonth.'-01'),
                $this->payerName,
                $this->payerPhone ?: null,
                (float) $this->amount,
                $this->paymentMethod,
                Carbon::parse($this->paidAt),
                $this->notes ?: null,
                Auth::user(),
            );
        } catch (\DomainException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        } catch (\Throwable $e) {
            Log::error('Debt payment failed', ['error' => $e->getMessage()]);
            $this->addError('amount', 'Something went wrong while saving the payment. Please try again.');

            return;
        }

        session()->flash('message', 'Payment of ₵'.number_format((float) $payment->amount, 2).' recorded. Outstanding for '.$payment->period_month->format('F Y').' is now ₵'.number_format((float) $payment->outstanding_after, 2).'.');

        $this->selectedMonth = $this->paymentMonth;
        $this->showPaymentModal = false;
        $this->resetPage();
    }

    public function openVoidModal($paymentId)
    {
        abort_unless(Auth::user()?->hasPermission('delete', 'debt_payments'), 403);

        $this->voidingPaymentId = $paymentId;
        $this->voidReason = '';
        $this->resetValidation();
        $this->showVoidModal = true;
    }

    public function closeVoidModal()
    {
        $this->showVoidModal = false;
        $this->voidingPaymentId = null;
        $this->voidReason = '';
        $this->resetValidation();
    }

    public function voidPayment(DebtLedger $ledger)
    {
        abort_unless(Auth::user()?->hasPermission('delete', 'debt_payments'), 403);

        $this->validate(['voidReason' => 'required|string|max:500'], [
            'voidReason.required' => 'Please give a reason for voiding this payment.',
        ]);

        $payment = DebtPayment::find($this->voidingPaymentId);

        if (! $payment) {
            $this->closeVoidModal();

            return;
        }

        try {
            $ledger->void($payment, $this->voidReason, Auth::user());
        } catch (\DomainException $e) {
            $this->addError('voidReason', $e->getMessage());

            return;
        }

        session()->flash('message', 'Payment voided. The outstanding debt has been restored.');
        $this->closeVoidModal();
    }

    public function render(DebtLedger $ledger)
    {
        $month = Carbon::createFromFormat('Y-m-d', $this->selectedMonth.'-01');

        $payments = DebtPayment::query()
            ->when(! $this->allMonths, fn ($q) => $q->whereDate('period_month', $month->toDateString()))
            ->when($this->searchTerm !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('payer_name', 'like', '%'.$this->searchTerm.'%')
                        ->orWhere('payer_phone', 'like', '%'.$this->searchTerm.'%')
                        ->orWhere('received_by_name', 'like', '%'.$this->searchTerm.'%');
                });
            })
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(15);

        $modalSummary = null;
        $amountAfter = null;

        if ($this->showPaymentModal && preg_match('/^\d{4}-\d{2}$/', (string) $this->paymentMonth)) {
            $modalMonth = Carbon::createFromFormat('Y-m-d', $this->paymentMonth.'-01');
            $modalSummary = $ledger->summary($modalMonth);

            if (is_numeric($this->amount) && (float) $this->amount > 0) {
                $amountAfter = round($modalSummary['outstanding'] - (float) $this->amount, 2);
            }
        }

        return view('livewire.pages.debt-payments', [
            'payments' => $payments,
            'summary' => $ledger->summary($month),
            'shortfalls' => $ledger->dailyShortfalls($month),
            'month' => $month,
            'modalSummary' => $modalSummary,
            'amountAfter' => $amountAfter,
            'methods' => DebtLedger::METHODS,
        ]);
    }

    private function normalizeMonth($value): string
    {
        $value = (string) $value;
        $current = now()->format('Y-m');

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) || $value > $current) {
            return $current;
        }

        return $value;
    }
}

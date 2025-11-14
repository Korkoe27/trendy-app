<?php

namespace App\Livewire\Pages;

use App\Models\Categories;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class Spending extends Component
{
    use WithPagination;

    // Search and Filter Properties
    public $searchTerm = '';
    public $selectedCategory = 'all';
    public $selectedMonth = '';

    // Modal Properties
    public $showAddExpenseModal = false;
    public $showEditExpenseModal = false;
    public $viewExpenseModal = false;
    public $showExportModal = false;

    // Form Properties
    public $expenseId;
    public $reference;
    public $amount;
    public $description;
    public $date;
    public $payment_method ;
    public $vendor;
    public $status;
    public $category;
    public $notes;
    public $paid_by;

    // Selected Expense for viewing
    public $selectedExpense;

    // Export Filters
    public $exportFilters = [
        'category' => 'all',
        'vendor' => '',
        'amount_min' => '',
        'amount_max' => '',
        'date_from' => '',
        'date_to' => '',
        'status' => 'all',
    ];

    protected $queryString = ['searchTerm', 'selectedCategory', 'selectedMonth'];

    protected function rules()
    {
        return [
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'date' => 'required|date',
            'payment_method' => 'required|in:CASH,BANK TRANSFER,MOBILE MONEY,CREDIT',
            'vendor' => 'required|string|max:255',
            'status' => 'required|in:PENDING,CONFIRMED,CANCELLED',
            'category' => 'required|string',
            'notes' => 'nullable|string|max:1000',
            'paid_by' => 'nullable|string|max:255',
        ];
    }

    public function mount()
    {
        $this->date = now()->format('Y-m-d');
        $this->paid_by = Auth::user()->name ?? '';
    }

    public function updatingSearchTerm()
    {
        $this->resetPage();
    }

    public function updatingSelectedCategory()
    {
        $this->resetPage();
    }

    public function updatingSelectedMonth()
    {
        $this->resetPage();
    }

    public function showAddExpenseModal()
    {
        $this->resetForm();
        $this->generateReference();
        $this->showAddExpenseModal = true;
    }

    public function closeAddModal()
    {
        $this->showAddExpenseModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    public function closeEditModal()
    {
        $this->showEditExpenseModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    public function closeViewModal()
    {
        $this->viewExpenseModal = false;
        $this->selectedExpense = null;
    }

    private function generateReference()
    {
        $date = now()->format('Ymd');
        $count = Expense::whereDate('created_at', now())->count() + 1;
        $this->reference = 'EXP-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    private function resetForm()
    {
        $this->reset([
            'expenseId',
            'reference',
            'amount',
            'description',
            'vendor',
            'notes',
            'category'
        ]);
        $this->date = now()->format('Y-m-d');
        $this->payment_method = 'CASH';
        $this->status = 'PENDING';
        $this->paid_by = Auth::user()->name ?? '';
    }

    public function saveExpense()
    {
        $this->validate();

        Expense::create([
            'reference' => $this->reference,
            'amount' => $this->amount,
            'description' => $this->description,
            'incurred_at' => $this->date,
            'payment_method' => $this->payment_method,
            'supplier' => $this->vendor,
            'status' => strtolower($this->status),
            'paid_by' => $this->paid_by,
            // Note: category and notes fields don't exist in your migration
            'category' => $this->category,
            'notes' => $this->notes,
            // You'll need to add them or store differently
        ]);

        session()->flash('message', 'Expense added successfully!');
        $this->closeAddModal();
        $this->resetPage();
    }

    public function editExpense($id)
    {
        $expense = Expense::findOrFail($id);
        
        $this->expenseId = $expense->id;
        $this->reference = $expense->reference;
        $this->amount = $expense->amount;
        $this->description = $expense->description;
        $this->date = Carbon::parse($expense->incurred_at)->format('Y-m-d');
        $this->payment_method = $expense->payment_method;
        $this->vendor = $expense->supplier;
        $this->status = strtoupper($expense->status);
        $this->paid_by = $expense->paid_by;
        // Load category and notes if columns exist
        
        $this->showEditExpenseModal = true;
    }

    public function updateExpense()
    {
        $this->validate();

        $expense = Expense::findOrFail($this->expenseId);
        $expense->update([
            'amount' => $this->amount,
            'description' => $this->description,
            'incurred_at' => $this->date,
            'payment_method' => $this->payment_method,
            'supplier' => $this->vendor,
            'status' => strtolower($this->status),
            'paid_by' => $this->paid_by,
        ]);

        session()->flash('message', 'Expense updated successfully!');
        $this->closeEditModal();
    }

    public function viewExpense($id)
    {
        $this->selectedExpense = Expense::findOrFail($id);
        // Map database fields to UI expectations
        $this->selectedExpense->vendor = $this->selectedExpense->supplier;
        $this->selectedExpense->date = $this->selectedExpense->incurred_at;
        $this->selectedExpense->status = strtoupper($this->selectedExpense->status);
        // Add category if it exists, otherwise use a default
        $this->selectedExpense->category = $this->selectedExpense->category ?? 'OPERATIONAL';
        
        $this->viewExpenseModal = true;
    }

    public function confirmPendingTransaction($id)
    {
        $expense = Expense::findOrFail($id);
        $expense->update(['status' => 'paid']);
        
        session()->flash('message', 'Transaction confirmed successfully!');
        $this->closeViewModal();
    }

    public function deleteExpense($id)
    {
        Expense::findOrFail($id)->delete();
        session()->flash('message', 'Expense deleted successfully!');
    }

    public function resetExportFilters()
    {
        $this->exportFilters = [
            'category' => 'all',
            'vendor' => '',
            'amount_min' => '',
            'amount_max' => '',
            'date_from' => '',
            'date_to' => '',
            'status' => 'all',
        ];
    }

    public function exportExpenses()
    {
        $query = $this->applyExportFilters(Expense::query());
        
        $expenses = $query->get();
        
        // Generate CSV
        $filename = 'expenses_export_' . now()->format('Y-m-d_His') . '.csv';
        $handle = fopen('php://temp', 'w');
        
        // Headers
        fputcsv($handle, [
            'Reference',
            'Description',
            'Vendor',
            'Date',
            'Amount',
            'Payment Method',
            'Status',
            'Paid By',
            'Created At'
        ]);
        
        // Data
        foreach ($expenses as $expense) {
            fputcsv($handle, [
                $expense->reference,
                $expense->description,
                $expense->supplier,
                Carbon::parse($expense->incurred_at)->format('Y-m-d'),
                number_format($expense->amount, 2),
                $expense->payment_method,
                ucfirst($expense->status),
                $expense->paid_by,
                $expense->created_at->format('Y-m-d H:i:s'),
            ]);
        }
        
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        
        $this->showExportModal = false;
        
        return response()->streamDownload(function() use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function applyExportFilters($query)
    {
        // Vendor filter
        if (!empty($this->exportFilters['vendor'])) {
            $query->where('supplier', 'like', '%' . $this->exportFilters['vendor'] . '%');
        }

        // Amount range
        if (!empty($this->exportFilters['amount_min'])) {
            $query->where('amount', '>=', $this->exportFilters['amount_min']);
        }
        if (!empty($this->exportFilters['amount_max'])) {
            $query->where('amount', '<=', $this->exportFilters['amount_max']);
        }

        // Date range
        if (!empty($this->exportFilters['date_from'])) {
            $query->whereDate('incurred_at', '>=', $this->exportFilters['date_from']);
        }
        if (!empty($this->exportFilters['date_to'])) {
            $query->whereDate('incurred_at', '<=', $this->exportFilters['date_to']);
        }

        // Status filter
        if ($this->exportFilters['status'] !== 'all') {
            $query->where('status', strtolower($this->exportFilters['status']));
        }

        // Category filter (if column exists)
        // if ($this->exportFilters['category'] !== 'all') {
        //     $query->where('category', $this->exportFilters['category']);
        // }

        return $query;
    }

    private function getExpensesQuery()
    {
        $query = Expense::query();

        // Search
        if (!empty($this->searchTerm)) {
            $query->where(function($q) {
                $q->where('reference', 'like', '%' . $this->searchTerm . '%')
                ->orWhere('description', 'like', '%' . $this->searchTerm . '%')
                ->orWhere('supplier', 'like', '%' . $this->searchTerm . '%');
            });
        }

        // Category filter (if column exists)
        // if ($this->selectedCategory !== 'all') {
        //     $query->where('category', $this->selectedCategory);
        // }

        // Month filter
        if ($this->selectedMonth === 'last') {
            $query->whereMonth('incurred_at', now()->subMonth()->month)
                ->whereYear('incurred_at', now()->subMonth()->year);
        } elseif (empty($this->selectedMonth)) {
            $query->whereMonth('incurred_at', now()->month)
                ->whereYear('incurred_at', now()->year);
        }

        return $query->latest('incurred_at');
    }

    private function calculateSummaryStats()
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $lastMonth = now()->subMonth()->month;
        $lastMonthYear = now()->subMonth()->year;

        $thisMonthTotal = Expense::whereMonth('incurred_at', $currentMonth)
            ->whereYear('incurred_at', $currentYear)
            ->where('status', '=', 'paid')
            ->sum('amount');

        $lastMonthTotal = Expense::whereMonth('incurred_at', $lastMonth)
            ->whereYear('incurred_at', $lastMonthYear)
            ->where('status', '=', 'canceled')
            ->sum('amount');

        $pendingTotal = Expense::where('status', 'pending')
            ->sum('amount');

        $pendingCount = Expense::where('status', 'pending')
            ->count();

        $change = $lastMonthTotal > 0 
            ? (($thisMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100 
            : 0;

        return [
            'this_month' => $thisMonthTotal,
            'last_month' => $lastMonthTotal,
            'pending_total' => $pendingTotal,
            'pending_count' => $pendingCount,
            'change' => $change,
        ];
    }

    public function render()
    {

        $categories = DB::table('expenses')?->select('category')->get();
        // dd( $categories);
        $expenses = $this->getExpensesQuery()->paginate(15);
        
        // Map database fields to UI expectations for each expense
        $expenses->getCollection()->transform(function($expense) {
            $expense->vendor = $expense->supplier;
            $expense->date = $expense->incurred_at;
            $expense->status = strtoupper($expense->status);
            $expense->category = $expense->category ?? 'OPERATIONAL';
            return $expense;
        });

        $stats = $this->calculateSummaryStats();
        
        // Get categories - you'll need to create this model or use a config array
        // $categories = ExpenseCategory::all();
        // Or use a simple array if you don't have a categories table:
        // $categories = collect([
        //     (object)['name' => 'STOCK PURCHASE'],
        //     (object)['name' => 'OPERATIONAL'],
        //     (object)['name' => 'EQUIPMENT'],
        //     (object)['name' => 'MAINTENANCE'],
        // ]);

        return view('livewire.pages.spending', [
            'expenses' => $expenses,
            'categories' => $categories,
            'stats' => $stats,
        ]);
    }
}
<?php

namespace App\Livewire\Pages;

use App\Models\{ActivityLogs,Expense,User};
use App\Models\Categories;
use App\Models\ExpenseCategory;
use Livewire\{Component,WithPagination};
use Illuminate\Support\Facades\{DB,Auth,Log};
use Carbon\Carbon;

class Spending extends Component
{
    use WithPagination;

    // Search and Filter Properties
    public $searchTerm = '';
    public $selectedCategory = 'all';
    public $selectedMonth = '';

    // Modal Properties
    public $openAddExpenseModal = false;
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

    public $recordType = 'expense';
    public $vendor;
    public $status;
    public $category;
    public $notes;
    public $paid_by;

    // Replace existing form properties with these:
    public $expenseItems = [];
    public $expenseDate;
    public $expenseNotes = '';
    public $expensePaymentMethod = 'cash';
    public $expenseStatus = 'pending';
    public $expensePaidBy;

    // Add categories list for item selection
    public $expenseCategories = [];

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
            'description' => 'nullable|string|max:500',
            'date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,momo,credit',
            'vendor' => 'required|string|max:255',
            'status' => 'required|in:pending,confirmed,cancelled',
            'category' => 'required|string',
            'notes' => 'nullable|string|max:1000',
            'paid_by' => 'nullable|string|max:255',
        ];
    }

    public function mount()
    {
        $this->expenseDate = now()->format('Y-m-d');
        $this->expensePaidBy = Auth::user()->name ?? '';
        
        // Load expense categories
        $this->expenseCategories = DB::table('expenses')
            ->select('category')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->toArray();
        
        // Add default categories if none exist
        if (empty($this->expenseCategories)) {
            $this->expenseCategories = ['STOCK PURCHASE', 'OPERATIONAL', 'EQUIPMENT', 'MAINTENANCE', 'UTILITIES', 'SALARIES'];
        }
    }

    public function updatingSearchTerm()
    {
        $this->resetPage();
    }

    public function openAddModal()
{
    $this->resetExpenseForm();
    $this->generateReferenceFromLastRecord();
    $this->openAddExpenseModal = true;
}


    public function updatingSelectedCategory()
    {
        $this->resetPage();
    }

    public function updatingSelectedMonth()
    {
        $this->resetPage();
    }

    public function openAddExpenseModal()
    {
        $this->resetExpenseForm();
        $this->generateReferenceFromLastRecord();
        $this->openAddExpenseModal = true;
    }

    public function addExpenseItem()
    {
        $this->expenseItems[] = [
            'category' => '',
            'description' => '',
            'vendor' => '',
            'amount' => '',
            'new_category' => '',
        ];
    }

    public function removeExpenseItem($index)
    {
        if (count($this->expenseItems) > 1) {
            unset($this->expenseItems[$index]);
            $this->expenseItems = array_values($this->expenseItems);
        }
    }
private function resetExpenseForm()
{
    $this->expenseItems = [
        [
            'category' => '',
            'description' => '',
            'vendor' => '',
            'amount' => '',
            'new_category' => '',
        ]
    ];
    $this->expenseNotes = '';
    $this->expenseDate = now()->format('Y-m-d');
    $this->expensePaymentMethod = 'cash';
    $this->expenseStatus = 'pending';
    $this->expensePaidBy = Auth::user()->name ?? '';
    $this->reference = null; // Reset the reference
    $this->resetErrorBag();
}

    public function closeAddModal()
    {
        $this->openAddExpenseModal = false;
        $this->resetExpenseForm();
        $this->resetValidation();
    }

    public function closeEditModal()
    {
        $this->showEditExpenseModal = false;
        $this->resetForm();
        $this->resetValidation();
        $this->resetValidation();
    }

    public function closeViewModal()
    {
        $this->viewExpenseModal = false;
        $this->selectedExpense = null;
        $this->recordType = 'expense';
    }

private function generateReferenceFromLastRecord()
{
    $today = now()->format('Ymd');
    
    // Get the last expense reference from the database
    $lastExpense = Expense::where('reference', 'like', "EXP-{$today}-%")
        ->latest('id')
        ->first();
    
    if ($lastExpense && $lastExpense->reference) {
        // Extract the numeric part
        preg_match('/EXP-\d{8}-(\d{4})/', $lastExpense->reference, $matches);
        $lastNumber = !empty($matches[1]) ? (int) $matches[1] : 0;
        $newNumber = $lastNumber + 1;
    } else {
        // First expense of the day
        $newNumber = 1;
    }
    
    // Generate and store the reference (but don't save to DB yet)
    $this->reference = 'EXP-' . $today . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
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
        $this->payment_method = 'cash';
        $this->status = 'pending';
        $this->paid_by = Auth::user()->name ?? '';
    }

public function saveExpense()
{

    $this->validate([
        'expenseItems.*.category' => 'required_without:expenseItems.*.new_category|string|nullable',
        'expenseItems.*.new_category' => 'nullable|string|max:255',
        'expenseItems.*.vendor' => 'required|string|max:255',
        'expenseItems.*.amount' => 'required|numeric|min:0.01',
        'expenseDate' => 'required|date|before_or_equal:today',
        'expensePaymentMethod' => 'required|in:cash,bank_transfer,momo,credit',
        'expenseStatus' => 'required|in:pending,paid,canceled',
        'expenseNotes' => 'nullable|string|max:1000',
        'expensePaidBy' => 'nullable|string|max:255',
    ], [
        'expenseItems.*.vendor.required' => 'Vendor is required',
        'expenseItems.*.amount.required' => 'Amount is required',
        'expenseItems.*.amount.min' => 'Amount must be greater than zero',
        ]);
        
        // dd($this->expenseItems);
    // Filter out empty items
    $validItems = array_filter($this->expenseItems, function ($item) {
        return !empty($item['vendor']) && 
               !empty($item['amount']);
    });

    if (empty($validItems)) {
        $this->addError('expenseItems', 'At least one expense item is required');
        return;
    }

    try {
        DB::beginTransaction();

        if (!$this->reference) {
    $this->generateReferenceFromLastRecord();
}

        $referenceExists = Expense::where('reference', $this->reference)->exists();
                if ($referenceExists) {
            // Regenerate reference number
            $this->generateReferenceFromLastRecord();
        }
        $totalAmount = 0;
        $processedItems = [];
        $vendors = [];
        $categories = [];

        foreach ($validItems as $item) {
            // Resolve category
            $category = !empty($item['new_category']) 
                ? trim($item['new_category']) 
                : $item['category'];
            
            $categories[] = $category;
            $vendors[$item['vendor']] = $item['vendor'];
            
            $totalAmount += (float)$item['amount'];
            


            $processedItems[] = [
    'product_id'    => "",
    'product_name'  => "",
    'sku'           => "",
    'barcode'       => "",

    'category'      => $category,
    'description'   => $item['description'],
    'vendor'        => $item['vendor'],

    'quantity'      => "",
    'paid_quantity' => (float) $item['amount'],
    'free_quantity' => "",

    'unit_cost'     => "",
    'total_cost'    => (float) $item['amount'],

    'supplier_id'   => "",
    'supplier_name' => $item['vendor'],
];
        }

        // dd($this->reference);

        // Create main expense record
        $vendorsList = implode(', ', array_unique($vendors));
        $categoriesList = implode(', ', array_unique($categories));
        
        $expense = Expense::create([
            'reference' => $this->reference,
            'amount' => $totalAmount,
            'description' => count($processedItems) > 1 
                ? "Multiple expenses across " . count($processedItems) . " items"
                : $processedItems[0]['description'],
            'incurred_at' => $this->expenseDate,
            'payment_method' => $this->expensePaymentMethod,
            'supplier' => $vendorsList,
            'status' => strtolower($this->expenseStatus),
            'paid_by' => Auth::user()->name ?? 'Unknown',
            'category' => $categoriesList,
            'notes' => $this->expenseNotes,
            'items' => $processedItems,
            'metadata' => [
                'total_items' => count($processedItems),
                'vendors' => array_values($vendors),
                'categories' => array_unique($categories),
                'created_by' => [
                    'id' => Auth::id(),
                    'name' => Auth::user()->name ?? 'Unknown',
                    'email' => Auth::user()->email ?? null,
                    'timestamp' => now()->toIso8601String(),
                ],
                'financial_summary' => [
                    'total_amount' => $totalAmount,
                    'items_breakdown' => array_map(function($item) {
                        return [
                            'description' => $item['description'],
                            'amount' => $item['total_cost'],
                        ];
                    }, $processedItems),
                ],
            ],
        ]);

        // Create activity log
        ActivityLogs::create([
            'user_id' => Auth::id(),
            'action_type' => 'expense_create',
            'description' => "Created expense with " . count($processedItems) . " item(s): {$categoriesList}",
            'entity_type' => 'expense',
            'entity_id' => $expense->id,
            'metadata' => json_encode([
                'expense_reference' => $expense->reference,
                'total_amount' => $totalAmount,
                'total_items' => count($processedItems),
                'vendors' => array_values($vendors),
                'timestamp' => now(),
            ]),
        ]);

        DB::commit();

        session()->flash('message', 'Expense invoice created successfully with ' . count($processedItems) . ' item(s)!');
        $this->closeAddModal();
        $this->resetPage();

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Expense creation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        $this->addError('expenseItems', 'Failed to create expense: ' . $e->getMessage());
    }
}

public function editExpense($id)
{
    $expense = Expense::findOrFail($id);

    // Check if this is a stock record
    if (str_starts_with($expense->reference, 'STK-') || $expense->category === 'inventory') {
        session()->flash('error', 'Stock records cannot be edited from this interface. Please use the Stock management page.');
        return;
    }

    $paid_by = User::find($expense->paid_by);
    $this->expenseId = $expense->id;
    $this->reference = $expense->reference;
    $this->amount = $expense->amount;
    $this->description = $expense?->description;
    $this->date = Carbon::parse($expense->incurred_at)->format('Y-m-d');
    $this->payment_method = $expense->payment_method;
    $this->vendor = $expense->supplier;
    $this->status = strtoupper($expense->status);
    $this->paid_by = $paid_by->name ?? 'Unknown';
    $this->category = $expense->category;
    $this->notes = $expense->notes;
    
    $this->showEditExpenseModal = true;
}

public function updateExpense()
{
    // Determine if this is a stock record
    $isStockRecord = str_starts_with($this->reference, 'STK-') || 
                     $this->category === 'inventory';

    if ($isStockRecord) {
        // Stock records should not be edited from the expense interface
        session()->flash('error', 'Stock records cannot be edited from this interface. Please use the Stock management page.');
        $this->closeEditModal();
        return;
    }

    $this->validate([
        'date' => 'required|date',
        'category' => 'required|string',
        'amount' => 'required|numeric|min:0',
        'vendor' => 'required|string',
        'payment_method' => 'required|string',
        'status' => 'required|string',
        'description' => 'nullable|string',
    ]);

    $expense = Expense::findOrFail($this->expenseId);
    
    // Preserve existing items and metadata
    $metadata = is_array($expense->metadata) ? $expense->metadata : json_decode($expense->metadata, true) ?? [];
    
    // Update metadata with edit information
    $metadata['last_updated'] = [
        'timestamp' => now(),
        'user_id' => Auth::id(),
        'user_name' => Auth::user()->name ?? 'Unknown',
    ];

    $expense->update([
        'incurred_at' => $this->date,
        'category' => $this->category,
        'amount' => $this->amount,  // Fixed: was 'total_cost'
        'supplier' => $this->vendor,
        'payment_method' => $this->payment_method,
        'status' => strtolower($this->status),
        'description' => $this->description,
        'notes' => $this->notes,
        'paid_by' => Auth::id(), // Store user ID instead of name
        'metadata' => $metadata, // Already an array based on your model cast
    ]);

    // Create activity log
    ActivityLogs::create([
        'user_id' => Auth::id(),
        'action_type' => 'expense_update',
        'description' => "Expense updated: {$this->reference}",
        'entity_type' => 'expense',
        'entity_id' => $expense->id,
        'metadata' => json_encode([
            'expense_reference' => $this->reference,
            'category' => $this->category,
            'amount' => $this->amount,
            'vendor' => $this->vendor,
            'timestamp' => now(),
        ]),
    ]);

    $this->closeEditModal();
    session()->flash('message', 'Expense updated successfully!');
}
public function viewExpense($id)
{
    $this->selectedExpense = Expense::findOrFail($id);
    
    // Determine record type based on category or reference prefix
    $this->recordType = (str_starts_with($this->selectedExpense->reference, 'STK-') || 
                        $this->selectedExpense->category === 'inventory') 
                        ? 'stock' : 'expense';

    $paid_by = User::find($this->selectedExpense->paid_by);
    $this->selectedExpense->paid_by = $paid_by ? $paid_by->name : 'Unknown';
    
    $this->selectedExpense->vendor = $this->selectedExpense->supplier;
    $this->selectedExpense->date = $this->selectedExpense->incurred_at;
    $this->selectedExpense->status = strtoupper($this->selectedExpense->status);
    $this->selectedExpense->category = $this->selectedExpense->category ?? 'OPERATIONAL';
    
    $this->viewExpenseModal = true;
}

    public function confirmPendingTransaction($id)
    {
        $expense = Expense::findOrFail($id);
        
        $metadata = json_decode($expense->metadata, true) ?? [];
        $metadata['confirmed_at'] = [
            'timestamp' => now(),
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name ?? 'Unknown',
        ];
        
        $expense->update([
            'status' => 'confirmed',
            'metadata' => json_encode($metadata)
        ]);
        
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
<?php

namespace App\Livewire\Pages;

use App\Models\ActivityLogs;
use App\Models\Categories;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class Stocks extends Component
{
    use WithPagination;

    public $searchTerm = '';

    public $viewStockModal = false;

    public $editStockModal = false;

    public $selectedStock;

    public $editSupplier;

    public $editTotalUnits;

    public $showExportModal = false;

    public $editStockItem = [];

    public $editTotalCost;

    public $editRestockDate;

    public $editNotes;

    public $selectedCategory = 'all';

    public $selectedDate;

    public $restockDate = '';

    public $showTakeStockModal = false;

    public $addNewStockModal = false;

    // New stock modal properties
    public $newStockItems = [];

    public $notes = '';

    public $suppliers = [];

    public $products = [];

    protected $rules = [
        'newStockItems.*.product_id' => 'required|exists:products,id',
        'newStockItems.*.input_units' => 'nullable|numeric|min:0',
        'newStockItems.*.input_boxes' => 'nullable|numeric|min:0',
        'newStockItems.*.total_cost' => 'required|numeric|min:0',
        'newStockItems.*.free_units' => 'numeric|min:0',
        'newStockItems.*.supplier_id' => 'nullable|exists:suppliers,id', // Changed
        'newStockItems.*.new_supplier_name' => 'nullable|string|max:255',
        'restockDate' => 'required|date|before_or_equal:today',
        'notes' => 'nullable|string|max:1000',
    ];

    protected $messages = [
        'newStockItems.*.product_id.required' => 'Product selection is required',
        'newStockItems.*.product_id.exists' => 'Selected product does not exist',
        'newStockItems.*.input_boxes.numeric' => 'Boxes must be a valid number',
        'newStockItems.*.input_units.numeric' => 'Units must be a valid number',
        'newStockItems.*.free_units.numeric' => 'Units must be a valid number',
        'newStockItems.*.total_cost.required' => 'Total cost is required',
        'newStockItems.*.total_cost.numeric' => 'Total cost must be a valid number',
        'newStockItems.*.supplier_id.exists' => 'Selected supplier does not exist',
        'restockDate.required' => 'Restock date is required',
        'restockDate.date' => 'Restock date must be a valid date',
        'restockDate.before_or_equal' => 'Restock date cannot be in the future',
    ];

    // Add these properties to your Stocks class
    public $exportFilters = [
        'category_id' => 'all',
        'supplier' => '',
        'stock_min' => '',
        'stock_max' => '',
        'cost_price_min' => '',
        'cost_price_max' => '',
        'total_cost_min' => '',
        'total_cost_max' => '',
        'margin_min' => '',
        'margin_max' => '',
        'restock_date_from' => '',
        'restock_date_to' => '',
    ];

    public function mount()
    {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->restockDate = Carbon::today()->format('Y-m-d');
        $this->products = Product::with('category')->where('is_active', true)->get();

        $this->suppliers = Supplier::with('stocks')->orderBy('name')->get();

        // $this->products = Product::all();
        $this->resetNewStockForm();
    }

    public function updatedRestockDate($value)
    {
        // Ensure restock date is not in the future
        if ($value && strtotime($value) > time()) {
            $this->restockDate = Carbon::today()->format('Y-m-d');
            session()->flash('error', 'Restock date cannot be in the future');
        }
    }

    public function updatedSearchTerm()
    {
        $this->resetPage();
    }

    public function updatedSelectedCategory()
    {
        $this->resetPage();
    }

    public function viewStock($stockId)
    {
        $this->selectedStock = Stock::with('product.category')->findOrFail($stockId);
        Log::debug('View stock details: '.$this->selectedStock);
        $this->viewStockModal = true;
    }

public function editStock($stockId)
{
    $this->selectedStock = Stock::with('product.category', 'supplier')->findOrFail($stockId);

    $this->editStockItem = [
        'product_id' => $this->selectedStock->product_id,
        'input_units' => $this->selectedStock->total_units,
        'calculated_total_units' => $this->selectedStock->total_units,
        'free_units' => $this->selectedStock->free_units,
        'total_cost' => $this->selectedStock->total_cost,
        'supplier_id' => $this->selectedStock->supplier_id, // Changed
        'new_supplier_name' => '', // Added
        'calculated_cost_price' => $this->selectedStock->cost_price,
        'calculated_profit_margin' => $this->selectedStock->cost_margin,
    ];

    $this->editRestockDate = $this->selectedStock->restock_date;
    $this->editNotes = $this->selectedStock->notes;

    $this->editStockModal = true;
}

    public function closeViewStockModal()
    {
        $this->viewStockModal = false;
        $this->selectedStock = null;
    }

    public function closeEditStockModal()
    {
        $this->editStockModal = false;
        $this->selectedStock = null;
        $this->editStockItem = [];
        $this->editRestockDate = null;
        $this->editNotes = null;
    }

    public function showAddNewStockModal()
    {
        $this->addNewStockModal = true;
        $this->restockDate = Carbon::today()->format('Y-m-d');
        $this->resetNewStockForm();
    }

    public function closeAddStockModal()
    {
        $this->addNewStockModal = false;
        $this->resetNewStockForm();
    }

    public function resetNewStockForm()
    {
        $this->newStockItems = [
            [
                'product_id' => '',
                'input_boxes' => '',
                'input_units' => '',
                'calculated_total_units' => 0,
                'free_units' => 0,
                'total_cost' => '',
                'supplier_id' => '', 
                'new_supplier_name' => '',
                'calculated_cost_price' => 0,
                'calculated_profit_margin' => 0,
            ],
        ];
        $this->notes = '';
        $this->resetErrorBag();
    }

    public function addStockItem()
    {
        $this->newStockItems[] = [
            'product_id' => '',
            'input_boxes' => '',
            'input_units' => '',
            'calculated_total_units' => 0,
            'free_units' => 0,
            'total_cost' => '',
            'supplier_id' => '', // Changed from 'supplier'
            'new_supplier_name' => '',
            'calculated_cost_price' => 0,
            'calculated_profit_margin' => 0,
        ];
    }

    public function exportStocks()
    {
        $query = Stock::with(['product.category']);

        // Apply filters
        if ($this->exportFilters['category_id'] !== 'all') {
            $query->whereHas('product', function ($q) {
                $q->where('category_id', $this->exportFilters['category_id']);
            });
        }

        if (! empty($this->exportFilters['supplier'])) {
            $query->where('supplier', 'like', '%'.$this->exportFilters['supplier'].'%');
        }

        if (! empty($this->exportFilters['stock_min'])) {
            $query->where('total_units', '>=', $this->exportFilters['stock_min']);
        }

        if (! empty($this->exportFilters['stock_max'])) {
            $query->where('total_units', '<=', $this->exportFilters['stock_max']);
        }

        if (! empty($this->exportFilters['cost_price_min'])) {
            $query->where('cost_price', '>=', $this->exportFilters['cost_price_min']);
        }

        if (! empty($this->exportFilters['cost_price_max'])) {
            $query->where('cost_price', '<=', $this->exportFilters['cost_price_max']);
        }

        if (! empty($this->exportFilters['total_cost_min'])) {
            $query->where('total_cost', '>=', $this->exportFilters['total_cost_min']);
        }

        if (! empty($this->exportFilters['total_cost_max'])) {
            $query->where('total_cost', '<=', $this->exportFilters['total_cost_max']);
        }

        if (! empty($this->exportFilters['margin_min'])) {
            $query->where('cost_margin', '>=', $this->exportFilters['margin_min']);
        }

        if (! empty($this->exportFilters['margin_max'])) {
            $query->where('cost_margin', '<=', $this->exportFilters['margin_max']);
        }

        if (! empty($this->exportFilters['restock_date_from'])) {
            $query->whereDate('restock_date', '>=', $this->exportFilters['restock_date_from']);
        }

        if (! empty($this->exportFilters['restock_date_to'])) {
            $query->whereDate('restock_date', '<=', $this->exportFilters['restock_date_to']);
        }

        if ($this->exportFilters['has_notes'] !== 'all') {
            if ($this->exportFilters['has_notes'] === 'yes') {
                $query->whereNotNull('notes')->where('notes', '!=', '');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('notes')->orWhere('notes', '');
                });
            }
        }

        $stocks = $query->get();

        $filePath = storage_path('app/stocks_export_'.now()->format('Y-m-d_His').'.csv');

        $header = [
            'product_name',
            'category',
            'sku',
            'barcode',
            'supplier',
            'total_units',
            'free_units',
            'boxes_equivalent',
            'total_cost',
            'cost_price',
            'selling_price',
            'profit_margin',
            'restock_date',
            'notes',
        ];

        $file = fopen($filePath, 'w');
        fputcsv($file, $header);

        foreach ($stocks as $stock) {
            $boxesEquivalent = $stock->product->units_per_box > 0
                ? round($stock->total_units / $stock->product->units_per_box, 2)
                : 0;

            fputcsv($file, [
                $stock->product->name,
                $stock->product->category->name,
                $stock->product->sku ?? '',
                $stock->product->barcode ?? '',
                $stock->supplier->name ?? '',
                $stock->total_units,
                $stock->free_units,
                $boxesEquivalent,
                $stock->total_cost,
                $stock->cost_price,
                $stock->product->selling_price,
                $stock->cost_margin,
                $stock->restock_date ? Carbon::parse($stock->restock_date)->format('Y-m-d') : '',
                $stock->notes ?? '',
            ]);
        }

        fclose($file);

        $this->showExportModal = false;
        $this->reset('exportFilters');

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function resetExportFilters()
    {
        $this->exportFilters = [
            'category_id' => 'all',
            'supplier' => '',
            'stock_min' => '',
            'stock_max' => '',
            'cost_price_min' => '',
            'cost_price_max' => '',
            'total_cost_min' => '',
            'total_cost_max' => '',
            'margin_min' => '',
            'margin_max' => '',
            'restock_date_from' => '',
            'restock_date_to' => '',
            'has_notes' => 'all',
        ];
    }

    public function removeStockItem($index)
    {
        if (count($this->newStockItems) > 1) {
            unset($this->newStockItems[$index]);
            $this->newStockItems = array_values($this->newStockItems);
        }
    }

    public function updatedNewStockItems($value, $key)
    {
        $parts = explode('.', $key);
        $index = $parts[0];
        $field = $parts[1];

        if (! isset($this->newStockItems[$index]['product_id']) || empty($this->newStockItems[$index]['product_id'])) {
            return;
        }

        // Recalculate when relevant fields change
        if (in_array($field, ['input_units', 'total_cost'])) {
            // For edit modal, input_units IS the total units (no boxes calculation needed)
            if ($this->editStockModal) {
                $this->newStockItems[$index]['calculated_total_units'] = (int) ($this->newStockItems[$index]['input_units'] ?? 0);
            } else {
                $this->calculateTotalUnits($index);
            }
            $this->calculateCostAndMargin($index);
        }
    }

    /**
     * Calculate total units based on boxes and individual units input
     */
    public function calculateTotalUnits($index)
    {
        if (! isset($this->newStockItems[$index]['product_id']) || empty($this->newStockItems[$index]['product_id'])) {
            $this->newStockItems[$index]['calculated_total_units'] = 0;

            return;
        }

        $product = Product::find($this->newStockItems[$index]['product_id']);
        if (! $product) {
            $this->newStockItems[$index]['calculated_total_units'] = 0;

            return;
        }

        $inputBoxes = (int) ($this->newStockItems[$index]['input_boxes'] ?? 0);
        $inputUnits = (int) ($this->newStockItems[$index]['input_units'] ?? 0);
        $unitsPerBox = (int) ($product->units_per_box ?? 1);

        // Calculate total units: (boxes * units_per_box) + individual_units
        $totalUnits = ($inputBoxes * $unitsPerBox) + $inputUnits;

        $this->newStockItems[$index]['calculated_total_units'] = $totalUnits;

        Log::debug("Total units calculation for index $index");
    }

    private function calculateCostAndMargin($index)
    {
        if (! isset($this->newStockItems[$index]['product_id']) || empty($this->newStockItems[$index]['product_id'])) {
            return;
        }

        $product = Product::find($this->newStockItems[$index]['product_id']);
        if (! $product) {
            return;
        }

        $totalCost = (float) ($this->newStockItems[$index]['total_cost'] ?? 0);
        $totalUnits = $this->newStockItems[$index]['calculated_total_units'] ?? 0;
        $sellingPrice = (float) $product->selling_price;

        if ($totalCost > 0 && $totalUnits > 0) {
            // Calculate cost price per unit (total_cost / total_units)
            $costPrice = $totalCost / $totalUnits;

            // Calculate profit margin per unit (selling_price - cost_price)
            $profitMargin = $sellingPrice - $costPrice;

            $this->newStockItems[$index]['calculated_cost_price'] = round($costPrice, 2);
            $this->newStockItems[$index]['calculated_profit_margin'] = round($profitMargin, 2);

            Log::debug("Cost calculation for index $index");
        } else {
            $this->newStockItems[$index]['calculated_cost_price'] = 0;
            $this->newStockItems[$index]['calculated_profit_margin'] = 0;
        }
    }

    public function updatedEditStockItem($value, $key)
    {
        $field = str_replace('editStockItem.', '', $key);

        // Recalculate when relevant fields change
        if (in_array($field, ['input_units', 'total_cost'])) {
            // Update calculated_total_units
            $this->editStockItem['calculated_total_units'] = (int) ($this->editStockItem['input_units'] ?? 0);

            // Use existing calculateCostAndMargin method by passing a pseudo-index
            $this->calculateEditItemCostAndMargin();
        }
    }

    private function calculateEditItemCostAndMargin()
    {
        if (! $this->selectedStock || ! isset($this->editStockItem['product_id'])) {
            return;
        }

        $product = $this->selectedStock->product;
        $totalCost = (float) ($this->editStockItem['total_cost'] ?? 0);
        $totalUnits = (int) ($this->editStockItem['calculated_total_units'] ?? 0);
        $sellingPrice = (float) $product->selling_price;

        if ($totalCost > 0 && $totalUnits > 0) {
            $costPrice = $totalCost / $totalUnits;
            $profitMargin = $sellingPrice - $costPrice;

            $this->editStockItem['calculated_cost_price'] = round($costPrice, 2);
            $this->editStockItem['calculated_profit_margin'] = round($profitMargin, 2);
        } else {
            $this->editStockItem['calculated_cost_price'] = 0;
            $this->editStockItem['calculated_profit_margin'] = 0;
        }
    }


    private function resolveSupplier($item)
    {
        if (!empty($item['new_supplier_name'])) {
            $supplier = Supplier::firstOrCreate(
                ['name' => trim($item['new_supplier_name'])],
                ['name' => trim($item['new_supplier_name'])]
            );
            return $supplier->id;
        } elseif (!empty($item['supplier_id'])) {
            return $item['supplier_id'];
        }
        
        return null;
    }

    /**
     * Get formatted expense details for invoice/receipt view
     */
    public function getExpenseDetailsForReceipt($expenseId)
    {
        $expense = Expense::findOrFail($expenseId);
        
        $items = json_decode($expense->items, true) ?? [];
        $metadata = json_decode($expense->metadata, true) ?? [];
        
        // Calculate subtotal and discounts
        $subtotal = array_sum(array_column($items, 'total_cost'));
        $discount = $subtotal - $expense->amount;
        
        return [
            'expense' => $expense,
            'items' => $items,
            'metadata' => $metadata,
            'invoice_details' => [
                'invoice_number' => $expense->reference,
                'issued' => Carbon::parse($expense->incurred_at)->format('m/d/y'),
                'due_date' => Carbon::parse($expense->incurred_at)->addDays(14)->format('m/d/y'),
            ],
            'from' => [
                'name' => config('app.name', 'Your Business'),
                'email' => config('mail.from.address'),
            ],
            'to' => [
                'name' => $expense->supplier ?? 'Multiple Suppliers',
                'suppliers' => $metadata['suppliers'] ?? [],
            ],
            'financial' => [
                'subtotal' => number_format($subtotal, 2),
                'discount' => number_format(abs($discount), 2),
                'total' => number_format($expense->amount, 2),
            ],
            'summary' => [
                'total_items' => $metadata['total_items'] ?? count($items),
                'total_units' => $metadata['total_units'] ?? 0,
                'total_free_units' => $metadata['total_free_units'] ?? 0,
                'restock_date' => $metadata['restock_date'] ?? null,
            ],
        ];
    }

    public function updateStock()
    {
        $this->validate([
            'editStockItem.supplier_id' => 'nullable|exists:suppliers,id',
            'editStockItem.input_units' => 'required|numeric|min:0',
            'editStockItem.total_cost' => 'required|numeric|min:0',
            'editRestockDate' => 'required|date|before_or_equal:today',

            'editNotes' => 'nullable|string|max:1000',
        ]);


        $supplierId = null;
        if (!empty($this->editStockItem['new_supplier_name'])) {
            $supplier = Supplier::firstOrCreate(
                ['name' => trim($this->editStockItem['new_supplier_name'])],
                ['name' => trim($this->editStockItem['new_supplier_name'])]
            );
            $supplierId = $supplier->id;
        } elseif (!empty($this->editStockItem['supplier_id'])) {
            $supplierId = $this->editStockItem['supplier_id'];
        }

        $this->selectedStock->update([
            'supplier_id' => $supplierId,
            'total_units' => $this->editStockItem['calculated_total_units'],
            'total_cost' => $this->editStockItem['total_cost'],
            'cost_price' => $this->editStockItem['calculated_cost_price'],
            'cost_margin' => $this->editStockItem['calculated_profit_margin'],
            'restock_date' => $this->editRestockDate,
            'notes' => $this->editNotes,
        ]);

        // Create activity log
        $productName = $this->selectedStock->product->name;
        // $product = $this->selectedStock->product->id;


        $supplierName = $supplierId ? Supplier::find($supplierId)->name : 'N/A';
        ActivityLogs::create([
            'user_id' => Auth::id(),
            'action_type' => 'stock_update',
            'description' => "Stock entry updated for {$productName}",
            'entity_type' => 'stock_record',
            'entity_id' => $this->selectedStock->id,
            'metadata' => json_encode([
                'product_name' => $productName,
                'supplier' => $supplierName,
                'total_units' => $this->editStockItem['calculated_total_units'],
                'total_cost' => $this->editStockItem['total_cost'],
                'cost_price' => $this->editStockItem['calculated_cost_price'],
                'profit_margin' => $this->editStockItem['calculated_profit_margin'],
                'restock_date' => $this->editRestockDate,
                'timestamp' => now(),
            ]),
        ]);

        $this->closeEditStockModal();
        session()->flash('message', 'Stock entry updated successfully!');
    }

    public function saveNewStock()
    {
        $this->validate();

        // Filter out empty items
        $validItems = array_filter($this->newStockItems, function ($item) {
            return ! empty($item['product_id']) &&
                $item['calculated_total_units'] > 0 &&
                ! empty($item['total_cost']);
        });

        Log::debug('Valid stock items for addition: ', $validItems);

        if (empty($validItems)) {
            $this->addError('newStockItems', 'At least one item with quantities, total cost, and supplier is required');

            return;
        }

        // Validate products exist BEFORE transaction
        $productIds = array_column($validItems, 'product_id');
        $existingProducts = Product::whereIn('id', $productIds)->pluck('id')->toArray();
        $missingIds = array_diff($productIds, $existingProducts);

        if (! empty($missingIds)) {
            $this->addError('newStockItems', 'Products not found: '.implode(', ', $missingIds));

            return;
        }

        $successCount = 0;
        $totalExpenseAmount = 0;

        try {
            DB::beginTransaction();

            $restockDate = $this->restockDate ?: Carbon::today()->format('Y-m-d');

            // Fetch all products at once
            $products = Product::select('id', 'name', 'barcode', 'sku', 'selling_price', 'units_per_box')
                ->with('currentStock')
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $stockCreateRows = [];
            $expenseItems = [];
            $productDetails = [];
            $supplierNames = [];

            foreach ($validItems as $index => $item) {
                $product = $products->get($item['product_id']);

                if (! $product) {
                    throw new \Exception('Product not found for item at position '.($index + 1));
                }

                // Validate item
                $this->validateStockItem($item, $index, $product);



                $supplierId = $this->resolveSupplier($item);
                $supplier = $supplierId ? Supplier::find($supplierId) : null; 
                
                
                if ($supplier) {
                    $supplierNames[$supplier->id] = $supplier->name;
                }


                $freeUnits = (int) ($item['free_units'] ?? 0);
                $totalUnitsToAdd = (int) $item['calculated_total_units'] + $freeUnits;
                $totalCost = (float) $item['total_cost'];
                // $supplier = trim($item['supplier']);
                $costPrice = (float) $item['calculated_cost_price'];
                $costMargin = (float) $item['calculated_profit_margin'];

                $totalExpenseAmount += $totalCost;


            $expenseItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'category' => $product->category->name ?? null,
                'quantity' => $totalUnitsToAdd,
                'paid_quantity' => (int)$item['calculated_total_units'],
                'free_quantity' => $freeUnits,
                'unit_cost' => $costPrice,
                'total_cost' => $totalCost,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplier['name'] ?? null,
            ];

                $stockCreateRows[] = [
                    'product_id' => $product->id,
                    'total_units' => $totalUnitsToAdd,
                    'supplier_id' => $supplierId,
                    'total_cost' => $totalCost,
                    'cost_price' => $costPrice,
                    'cost_margin' => $costMargin,
                    'free_units' => $freeUnits,
                    'notes' => $this->notes,
                    'restock_date' => $restockDate,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

            $productDetails[] = [
                'name' => $product->name,
                'sku' => $product->sku,
                'units_added' => $totalUnitsToAdd,
                'free_units' => $freeUnits,
                'cost' => $totalCost,
                'supplier' => $supplier['name'],
            ];

                $successCount++;
            }


            Log::debug("prepared stock count");
            // Batch insert new stock records
            if (! empty($stockCreateRows)) {
                DB::table('stocks')->insert($stockCreateRows);
                Log::info('Created new stock entries', ['count' => count($stockCreateRows)]);
            }

        $productNamesList = implode(', ', array_column($productDetails, 'name'));
        $truncatedProductList = strlen($productNamesList) > 200
            ? substr($productNamesList, 0, 197) . '...'
            : $productNamesList;

            

            $totalPaidQuantity = array_sum(array_column($expenseItems, 'paid_quantity'));
$totalFreeQuantity = array_sum(array_column($expenseItems, 'free_quantity'));
$totalQuantity = array_sum(array_column($expenseItems, 'quantity'));
$expense = Expense::create([
    'reference' => 'STK-' . strtoupper(uniqid()),
    'amount' => $totalExpenseAmount,
    'description' => "Stock replenishment for {$successCount} product(s): {$truncatedProductList}",
    'incurred_at' => Carbon::parse($restockDate),
    'payment_method' => 'inventory',
    'paid_by' => Auth::id(),
    'category' => 'inventory',
    'notes' => $this->notes,
    'status' => 'pending',
    'supplier' => implode(', ', array_unique($supplierNames)),
    'items' => json_encode($expenseItems), // Already contains all product details
    'metadata' => json_encode([
        'restock_date' => $restockDate,
        'total_items' => $successCount,
        'total_units' => $totalQuantity,
        'total_paid_units' => $totalPaidQuantity,
        'total_free_units' => $totalFreeQuantity,
        'suppliers' => $supplierNames,
        'created_by' => [
            'id' => Auth::id(),
            'name' => Auth::user()->name ?? 'Unknown',
            'email' => Auth::user()->email ?? null,
            'timestamp' => now()->toIso8601String(),
        ],
        'products_summary' => $productDetails,
        'financial_summary' => [
            'total_cost' => $totalExpenseAmount,
            'average_unit_cost' => $totalQuantity > 0 ? round($totalExpenseAmount / $totalQuantity, 2) : 0,
            'items_breakdown' => array_map(function($item) {
                return [
                    'product' => $item['product_name'],
                    'cost' => $item['total_cost'],
                ];
            }, $expenseItems),
        ],
    ]),
]);

        ActivityLogs::create([
            'user_id' => Auth::id(),
            'action_type' => 'stock_update',
            'description' => "Stock added for {$successCount} product(s): {$truncatedProductList}",
            'entity_type' => 'stock_record',
            'entity_id' => 'bulk_update',
            'metadata' => json_encode([
                'expense_id' => $expense->id,
                'expense_reference' => $expense->reference,
                'total_items_added' => $successCount,
                'total_expense' => $totalExpenseAmount,
                'restock_date' => $restockDate,
                'timestamp' => now(),
            ]),
        ]);

            DB::commit();

            // Success actions AFTER commit
            $this->closeAddStockModal();
            session()->flash('message', "Successfully added stock for {$successCount} product(s)");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Stock update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addError('newStockItems', $e->getMessage());
            session()->flash('error', 'Stock update failed: '.$e->getMessage());
        }
    }

    private function createActivityLog($product, $stockItem, $costMargin, $costPrice, $totalUnitsAdded)
    {
        $description = "Stock updated for {$product->name}";

        // Calculate boxes equivalent for logging
        $boxesEquivalent = $product->units_per_box > 0 ? round($totalUnitsAdded / $product->units_per_box, 2) : 0;

        $metadata = [
            'product_name' => $product->name,
            'product_id' => $product->id,
            'supplier' => $stockItem['supplier'],
            'input_boxes' => (int) ($stockItem['input_boxes'] ?? 0),
            'input_units' => (int) ($stockItem['input_units'] ?? 0),
            'total_units_added' => $totalUnitsAdded,
            'boxes_equivalent' => $boxesEquivalent,
            'total_cost' => (float) $stockItem['total_cost'],
            'cost_price_per_unit' => round($costPrice, 2),
            'selling_price' => (float) $product->selling_price,
            'cost_margin_per_unit' => round($costMargin, 2),
            'notes' => $this->notes,
            'timestamp' => now(),
        ];

        ActivityLogs::create([
            'user_id' => Auth::id(),
            'action_type' => 'stock_update',
            'description' => $description,
            'entity_type' => 'stock_record',
            'entity_id' => $product->barcode ?? $product->sku ?? $product->id,
            'metadata' => json_encode($metadata),
        ]);
    }

    public function deleteStockEntry($productId)
    {
        $stock = Stock::where('product_id', $productId)->first();

        if ($stock) {
            $product = Product::find($productId);
            $boxesEquivalent = $product->units_per_box > 0 ? round($stock->total_units / $product->units_per_box, 2) : 0;

            // Create activity log for deletion
            ActivityLogs::create([
                'user_id' => Auth::id(),
                'action_type' => 'stock_delete',
                'description' => "Stock entry deleted for {$product->name}",
                'entity_type' => 'stock_record',
                'entity_id' => $productId,
                'metadata' => json_encode([
                    'product_name' => $product->name,
                    'deleted_units' => $stock->total_units,
                    'deleted_boxes_equivalent' => $boxesEquivalent,
                    'supplier' => $stock->supplier,
                    'total_cost' => $stock->total_cost,
                    'cost_price' => $stock->cost_price,
                    'timestamp' => now(),
                ]),
            ]);

            $stock->delete();
        }

        session()->flash('message', 'Stock entry deleted successfully');
    }

        private function validateStockItem($item, $index, $product)
        {
            $errors = [];

            // Updated supplier validation
            if (empty($item['supplier_id']) && empty($item['new_supplier_name'])) {
                $errors[] = 'Supplier is required (select existing or enter new)';
            }

            if (!isset($item['total_cost']) || $item['total_cost'] <= 0) {
                $errors[] = 'Total cost must be greater than zero';
            }

            if (!isset($item['calculated_total_units']) || $item['calculated_total_units'] <= 0) {
                $errors[] = 'Total units must be greater than zero';
            }

            if (!isset($item['calculated_cost_price']) || $item['calculated_cost_price'] <= 0) {
                $errors[] = 'Invalid cost price calculation. Please check your inputs';
            }

            if (!empty($errors)) {
                $productName = $product ? $product->name : 'Product at position ' . ($index + 1);
                throw new \Exception("{$productName}: " . implode(', ', $errors));
            }
        }

    public function render()
    {
        $categories = Categories::all();

        // Using window function for PostgreSQL (more efficient)
        $query = Stock::with(['product.category'])
            ->whereRaw('id IN (
        SELECT DISTINCT ON (product_id) id
        FROM stocks
        ORDER BY product_id, created_at DESC
        )');

        if ($this->searchTerm) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'like', '%'.$this->searchTerm.'%')
                    ->orWhere('barcode', 'like', '%'.$this->searchTerm.'%')
                    ->orWhere('sku', 'like', '%'.$this->searchTerm.'%');
            });
        }

        // dd($query);
        if ($this->selectedCategory !== 'all') {
            $query->whereHas('product.category', function ($q) {
                $q->where('name', $this->selectedCategory);
            });
        }

        $stocks = $query->paginate(10);

        return view('livewire.pages.stocks', [
            'stocks' => $stocks,
            'categories' => $categories,
        ]);
    }
}

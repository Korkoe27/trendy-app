<?php

namespace App\Livewire\Pages;

use App\Models\ActivityLogs;
use App\Models\Categories;
use App\Models\Product;
use App\Models\Stock;
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

    public $editStockItem = [];

    public $editTotalCost;

    // public $editCostPrice;
    // public $editCostMargin;
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

    public $products = [];

    protected $rules = [
        'newStockItems.*.product_id' => 'required|exists:products,id',
        'newStockItems.*.input_units' => 'nullable|numeric|min:0',
        'newStockItems.*.input_boxes' => 'nullable|numeric|min:0',
        'newStockItems.*.total_cost' => 'required|numeric|min:0',
        'newStockItems.*.supplier' => 'required|string|max:255',
        'restockDate' => 'required|date|before_or_equal:today',
        'notes' => 'nullable|string|max:1000',
    ];

    protected $messages = [
        'newStockItems.*.product_id.required' => 'Product selection is required',
        'newStockItems.*.product_id.exists' => 'Selected product does not exist',
        'newStockItems.*.input_boxes.numeric' => 'Boxes must be a valid number',
        'newStockItems.*.input_units.numeric' => 'Units must be a valid number',
        'newStockItems.*.total_cost.required' => 'Total cost is required',
        'newStockItems.*.total_cost.numeric' => 'Total cost must be a valid number',
        'newStockItems.*.supplier.required' => 'Supplier is required',
        'restockDate.required' => 'Restock date is required',
        'restockDate.date' => 'Restock date must be a valid date',
        'restockDate.before_or_equal' => 'Restock date cannot be in the future',
    ];

    public function mount()
    {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->restockDate = Carbon::today()->format('Y-m-d');
        $this->products = Product::with('category')->where('is_active', true)->get();
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
        $this->selectedStock = Stock::with('product.category')->findOrFail($stockId);

        // Initialize editStockItem array with existing data (similar to newStockItems structure)
        $this->editStockItem = [
            'product_id' => $this->selectedStock->product_id,
            'input_units' => $this->selectedStock->total_units,
            'calculated_total_units' => $this->selectedStock->total_units,
            'total_cost' => $this->selectedStock->total_cost,
            'supplier' => $this->selectedStock->supplier,
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
                'total_cost' => '',
                'supplier' => '',
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
            'total_cost' => '',
            'supplier' => '',
            'calculated_cost_price' => 0,
            'calculated_profit_margin' => 0,
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

    public function updateStock()
    {
        $this->validate([
            'editStockItem.supplier' => 'required|string|max:255',
            'editStockItem.input_units' => 'required|numeric|min:0',
            'editStockItem.total_cost' => 'required|numeric|min:0',
            'editRestockDate' => 'required|date|before_or_equal:today',
            'editNotes' => 'nullable|string|max:1000',
        ]);

        $this->selectedStock->update([
            'supplier' => $this->editStockItem['supplier'],
            'total_units' => $this->editStockItem['calculated_total_units'],
            'total_cost' => $this->editStockItem['total_cost'],
            'cost_price' => $this->editStockItem['calculated_cost_price'],
            'cost_margin' => $this->editStockItem['calculated_profit_margin'],
            'restock_date' => $this->editRestockDate,
            'notes' => $this->editNotes,
        ]);

        // Create activity log
        $productName = $this->selectedStock->product->name;
        ActivityLogs::create([
            'user_id' => Auth::id(),
            'action_type' => 'stock_update',
            'description' => "Stock entry updated for {$productName}",
            'entity_type' => 'stock_record',
            'entity_id' => $this->selectedStock->id,
            'metadata' => json_encode([
                'product_name' => $productName,
                'supplier' => $this->editStockItem['supplier'],
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
    // Replace the saveNewStock() method in Stocks.php with this refactored version

    public function saveNewStock()
    {
        $this->validate();

        // Filter out empty items
        $validItems = array_filter($this->newStockItems, function ($item) {
            return ! empty($item['product_id']) &&
                $item['calculated_total_units'] > 0 &&
                ! empty($item['total_cost']);
        });

        if (empty($validItems)) {
            $this->addError('newStockItems', 'At least one item with quantities, total cost, and supplier is required');

            return;
        }

        $restockDate = $this->restockDate ?: Carbon::today()->format('Y-m-d');

        // Get all product IDs for batch query
        $productIds = array_column($validItems, 'product_id');

        // Fetch all products at once
        $products = Product::select('id', 'name', 'barcode', 'sku')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        // Fetch all existing stocks at once
        $existingStocks = Stock::whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        $stockCreateRows = [];
        $stockUpdateRows = [];
        $successCount = 0;

        foreach ($validItems as $item) {
            $product = $products->get($item['product_id']);
            if (! $product) {
                continue;
            }

            $totalUnitsToAdd = (int) $item['calculated_total_units'];
            $totalCost = (float) $item['total_cost'];
            $supplier = $item['supplier'];
            $costPrice = (float) $item['calculated_cost_price'];
            $costMargin = (float) $item['calculated_profit_margin'];

            $existingStock = $existingStocks->get($item['product_id']);

            if ($existingStock) {
                // Prepare update data
                $stockUpdateRows[] = [
                    'id' => $existingStock->id,
                    'total_units' => $existingStock->total_units + $totalUnitsToAdd,
                    'supplier' => $supplier,
                    'total_cost' => $totalCost,
                    'cost_price' => $costPrice,
                    'cost_margin' => $costMargin,
                    'notes' => $this->notes,
                    'restock_date' => $restockDate,
                    'updated_at' => now(),
                ];
            } else {
                // Prepare create data
                $stockCreateRows[] = [
                    'product_id' => $product->id,
                    'total_units' => $totalUnitsToAdd,
                    'supplier' => $supplier,
                    'total_cost' => $totalCost,
                    'cost_price' => $costPrice,
                    'cost_margin' => $costMargin,
                    'notes' => $this->notes,
                    'restock_date' => $restockDate,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $successCount++;
        }

        // Batch insert new stocks
        if (! empty($stockCreateRows)) {
            DB::table('stocks')->insert($stockCreateRows);
            Log::info('Created new stock entries', ['count' => count($stockCreateRows)]);
        }

        // Batch update existing stocks
        if (! empty($stockUpdateRows)) {
            foreach ($stockUpdateRows as $updateData) {
                DB::table('stocks')
                    ->where('id', $updateData['id'])
                    ->update([
                        'total_units' => $updateData['total_units'],
                        'supplier' => $updateData['supplier'],
                        'total_cost' => $updateData['total_cost'],
                        'cost_price' => $updateData['cost_price'],
                        'cost_margin' => $updateData['cost_margin'],
                        'notes' => $updateData['notes'],
                        'restock_date' => $updateData['restock_date'],
                        'updated_at' => $updateData['updated_at'],
                    ]);
            }
            Log::info('Updated existing stock entries', ['count' => count($stockUpdateRows)]);
        }

        $this->closeAddStockModal();
        session()->flash('message', "Successfully updated stock for {$successCount} product(s)");

        // Create activity log
        ActivityLogs::create([
            'user_id' => Auth::id(),
            'action_type' => 'stock_update',
            'description' => "Stock updated for {$successCount} product(s) (Restock Date: ".Carbon::parse($restockDate)->format('M j, Y').')',
            'entity_type' => 'stock_record',
            'entity_id' => 'bulk_update',
            'metadata' => json_encode([
                'total_items_updated' => $successCount,
                'new_entries' => count($stockCreateRows),
                'updated_entries' => count($stockUpdateRows),
                'restock_date' => $restockDate,
                'timestamp' => now(),
            ]),
        ]);
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

    public function render()
    {
        $categories = Categories::all();

        $query = Stock::with(['product.category']);

        if ($this->searchTerm) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'like', '%'.$this->searchTerm.'%')
                    ->orWhere('barcode', 'like', '%'.$this->searchTerm.'%')
                    ->orWhere('sku', 'like', '%'.$this->searchTerm.'%');
            });
        }

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

<?php

namespace App\Livewire\Pages;

use App\Models\{Categories, Product, Stock, ActivityLogs};
use Livewire\{Component, WithPagination};
use Carbon\Carbon;
use Illuminate\Support\Facades\{Log, Auth};

class Stocks extends Component
{
    use WithPagination;

    public $searchTerm = '';
    public $selectedCategory = 'all';
    public $selectedDate;
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
        'notes' => 'nullable|string|max:1000',
    ];

    protected $messages = [
        'newStockItems.*.product_id.required' => 'Product selection is required',
        'newStockItems.*.product_id.exists' => 'Selected product does not exist',
        'newStockItems.*.input_boxes.numeric' => 'Boxes must be a valid number',
        'newStockItems.*.input_units.numeric' => 'Units must be a valid number',
        'newStockItems.*.total_cost.required' => 'Total cost is required',
        'newStockItems.*.total_cost.numeric' => 'Total cost must be a valid number',
        'newStockItems.*.supplier.required' => 'Supplier is required'
    ];

    public function mount()
    {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->products = Product::with('category')->where('is_active', true)->get();
        $this->resetNewStockForm();
    }

    public function updatedSearchTerm()
    {
        $this->resetPage();
    }

    public function updatedSelectedCategory()
    {
        $this->resetPage();
    }

    public function showAddNewStockModal()
    {
        $this->addNewStockModal = true;
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
            ]
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

        if (!isset($this->newStockItems[$index]['product_id']) || empty($this->newStockItems[$index]['product_id'])) {
            return;
        }

        // Recalculate total units and costs when any relevant field changes
        if (in_array($field, ['input_boxes', 'input_units', 'total_cost'])) {
            $this->calculateTotalUnits($index);
            $this->calculateCostAndMargin($index);
        }
    }

    /**
     * Calculate total units based on boxes and individual units input
     */
    public function calculateTotalUnits($index)
    {
        if (!isset($this->newStockItems[$index]['product_id']) || empty($this->newStockItems[$index]['product_id'])) {
            $this->newStockItems[$index]['calculated_total_units'] = 0;
            return;
        }

        $product = Product::find($this->newStockItems[$index]['product_id']);
        if (!$product) {
            $this->newStockItems[$index]['calculated_total_units'] = 0;
            return;
        }

        $inputBoxes = (int) ($this->newStockItems[$index]['input_boxes'] ?? 0);
        $inputUnits = (int) ($this->newStockItems[$index]['input_units'] ?? 0);
        $unitsPerBox = (int) ($product->units_per_box ?? 1);

        // Calculate total units: (boxes * units_per_box) + individual_units
        $totalUnits = ($inputBoxes * $unitsPerBox) + $inputUnits;
        
        $this->newStockItems[$index]['calculated_total_units'] = $totalUnits;

        Log::info("Total units calculation for index $index", [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'input_boxes' => $inputBoxes,
            'input_units' => $inputUnits,
            'units_per_box' => $unitsPerBox,
            'calculated_total_units' => $totalUnits
        ]);
    }

    private function calculateCostAndMargin($index)
    {
        if (!isset($this->newStockItems[$index]['product_id']) || empty($this->newStockItems[$index]['product_id'])) {
            return;
        }

        $product = Product::find($this->newStockItems[$index]['product_id']);
        if (!$product) {
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

            Log::info("Cost calculation for index $index", [
                'total_cost' => $totalCost,
                'total_units' => $totalUnits,
                'cost_price_per_unit' => $costPrice,
                'selling_price' => $sellingPrice,
                'profit_margin' => $profitMargin
            ]);
        } else {
            $this->newStockItems[$index]['calculated_cost_price'] = 0;
            $this->newStockItems[$index]['calculated_profit_margin'] = 0;
        }
    }

    public function saveNewStock()
    {
        $this->validate();

        // Filter out empty items
        $validItems = array_filter($this->newStockItems, function($item) {
            return !empty($item['product_id']) && 
                   $item['calculated_total_units'] > 0 && 
                   !empty($item['total_cost']) && 
                   !empty($item['supplier']);
        });

        if (empty($validItems)) {
            $this->addError('newStockItems', 'At least one item with quantities, total cost, and supplier is required');
            return;
        }

        $successCount = 0;

        foreach ($validItems as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) continue;

            $totalUnitsToAdd = (int) $item['calculated_total_units'];
            $totalCost = (float) $item['total_cost'];
            $supplier = $item['supplier'];
            
            // Calculate cost price and margin using the calculated values
            $costPrice = (float) $item['calculated_cost_price'];
            $costMargin = (float) $item['calculated_profit_margin'];

            // Get existing stock entry for this product (1:1 relationship)
            $existingStock = Stock::where('product_id', $product->id)->first();

            if ($existingStock) {
                // Update existing stock entry
                $existingStock->update([
                    'total_units' => $existingStock->total_units + $totalUnitsToAdd,
                    'supplier' => $supplier,
                    'total_cost' => $totalCost,
                    'cost_price' => $costPrice,
                    'cost_margin' => $costMargin,
                    'notes' => $this->notes,
                    'updated_at' => now()
                ]);
            } else {
                // Create new stock entry
                Stock::create([
                    'product_id' => $product->id,
                    'total_units' => $totalUnitsToAdd,
                    'supplier' => $supplier,
                    'total_cost' => $totalCost,
                    'cost_price' => $costPrice,
                    'cost_margin' => $costMargin,
                    'notes' => $this->notes
                ]);
            }

            // Create activity log
            // $this->createActivityLog($product, $item, $costMargin, $costPrice, $totalUnitsToAdd);

            $successCount++;
        }

        $this->closeAddStockModal();
        session()->flash('message', "Successfully updated stock for {$successCount} product(s)");

        
        ActivityLogs::create([
            'user_id' => Auth::id(),
            'action_type' => 'stock_update',
            'description' => "Stock updated for {$successCount} product(s)",
            'entity_type' => 'stock_record',
            'entity_id' => 'bulk_update',
            'metadata' => json_encode([
                'total_items_updated' => $successCount,
                'timestamp' => now()
            ])
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
            'timestamp' => now()
        ];

        ActivityLogs::create([
            'user_id' => Auth::id(),
            'action_type' => 'stock_update',
            'description' => $description,
            'entity_type' => 'stock_record',
            'entity_id' => $product->barcode ?? $product->sku ?? $product->id,
            'metadata' => json_encode($metadata)
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
                    'timestamp' => now()
                ])
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
                $q->where('name', 'like', '%' . $this->searchTerm . '%')
                ->orWhere('barcode', 'like', '%' . $this->searchTerm . '%')
                ->orWhere('sku', 'like', '%' . $this->searchTerm . '%');
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
            'categories' => $categories
        ]);
    }
}
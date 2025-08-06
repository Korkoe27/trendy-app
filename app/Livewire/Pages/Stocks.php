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
                'boxes_disabled' => false,
                'units_disabled' => false,
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
            'boxes_disabled' => false,
            'units_disabled' => false,
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

        $product = Product::find($this->newStockItems[$index]['product_id']);
        if (!$product) {
            return;
        }

        $inputBoxes = (float) ($this->newStockItems[$index]['input_boxes'] ?? 0);
        $inputUnits = (float) ($this->newStockItems[$index]['input_units'] ?? 0);
        $unitsPerBox = (float) ($product->units_per_box ?? 1);
        $totalCost = (float) ($this->newStockItems[$index]['total_cost'] ?? 0);

        // Handle box/unit calculations
        if ($field === 'input_boxes' && !empty($value) && $unitsPerBox > 0) {
            // User is inputting boxes - disable units field and calculate total units
            $this->newStockItems[$index]['units_disabled'] = true;
            $this->newStockItems[$index]['boxes_disabled'] = false;
            
            $calculatedUnits = $inputBoxes * $unitsPerBox;
            $this->newStockItems[$index]['input_units'] = $calculatedUnits;
            $this->newStockItems[$index]['calculated_total_units'] = $calculatedUnits;
            
        } elseif ($field === 'input_units' && !empty($value)) {
            // User is inputting units directly - disable boxes and calculate boxes for display
            $this->newStockItems[$index]['boxes_disabled'] = true;
            $this->newStockItems[$index]['units_disabled'] = false;
            
            if ($unitsPerBox > 0) {
                $calculatedBoxes = $inputUnits / $unitsPerBox;
                $this->newStockItems[$index]['input_boxes'] = round($calculatedBoxes, 2);
            }
            $this->newStockItems[$index]['calculated_total_units'] = $inputUnits;
            
        } elseif (empty($value)) {
            // If field is cleared, enable both fields and reset calculations
            if ($field === 'input_boxes') {
                $this->newStockItems[$index]['units_disabled'] = false;
                $this->newStockItems[$index]['input_units'] = '';
                $this->newStockItems[$index]['calculated_total_units'] = 0;
            } elseif ($field === 'input_units') {
                $this->newStockItems[$index]['boxes_disabled'] = false;
                $this->newStockItems[$index]['input_boxes'] = '';
                $this->newStockItems[$index]['calculated_total_units'] = 0;
            }
        }

        // Recalculate costs when total cost or quantities change
        if (in_array($field, ['total_cost', 'input_boxes', 'input_units']) && $totalCost > 0 && $unitsPerBox > 0) {
            $this->calculateCostAndMargin($index);
        }
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
        $unitsPerBox = (float) ($product->units_per_box ?? 1);
        $sellingPrice = (float) $product->selling_price;

        if ($totalCost > 0 && $unitsPerBox > 0) {
            // Calculate cost price per unit (total_cost / units_per_box)
            $costPrice = $totalCost / $unitsPerBox;
            
            // Calculate profit margin per unit (selling_price - cost_price)
            $profitMargin = $sellingPrice - $costPrice;
            
            $this->newStockItems[$index]['calculated_cost_price'] = round($costPrice, 2);
            $this->newStockItems[$index]['calculated_profit_margin'] = round($profitMargin, 2);
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

            $totalUnitsToAdd = (float) $item['calculated_total_units'];
            $totalCost = (float) $item['total_cost'];
            $supplier = $item['supplier'];
            
            // Calculate cost price and margin
            $unitsPerBox = (float) ($product->units_per_box ?? 1);
            $costPrice = $unitsPerBox > 0 ? $totalCost / $unitsPerBox : $totalCost;
            $costMargin = $product->selling_price - $costPrice;

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
            $this->createActivityLog($product, $item, $costMargin, $costPrice, $totalUnitsToAdd);

            $successCount++;
        }

        $this->closeAddStockModal();
        session()->flash('message', "Successfully updated stock for {$successCount} product(s)");
    }

    private function createActivityLog($product, $stockItem, $costMargin, $costPrice, $totalUnitsAdded)
    {
        $description = "Stock updated for {$product->name}";
        
        $metadata = [
            'product_name' => $product->name,
            'product_id' => $product->id,
            'supplier' => $stockItem['supplier'],
            'total_cost' => (float) $stockItem['total_cost'],
            'cost_price' => round($costPrice, 2),
            'selling_price' => (float) $product->selling_price,
            'cost_margin' => round($costMargin, 2),
            'units_added' => $totalUnitsAdded,
            'boxes_equivalent' => $product->units_per_box > 0 ? round($totalUnitsAdded / $product->units_per_box, 2) : 0,
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
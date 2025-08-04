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
        'newStockItems.*.available_units' => 'nullable|numeric|min:0',
        'newStockItems.*.available_boxes' => 'nullable|numeric|min:0',
        'newStockItems.*.cost_price' => 'required|numeric|min:0',
        'newStockItems.*.supplier' => 'string|max:255',
        'notes' => 'nullable|string|max:1000',
    ];

    protected $messages = [
        'newStockItems.*.product_id.required' => 'Product selection is required',
        'newStockItems.*.product_id.exists' => 'Selected product does not exist',
        'newStockItems.*.available_boxes.numeric' => 'Boxes must be a valid number',
        'newStockItems.*.available_units.numeric' => 'Units must be a valid number',
        'newStockItems.*.cost_price.required' => 'Cost price is required',
        'newStockItems.*.cost_price.numeric' => 'Cost price must be a valid number'
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
                'available_boxes' => '',
                'available_units' => '',
                'cost_price' => '',
                'supplier' => '',
                'unit_cost' => '',
                'profit_margin' => '',
                'boxes_disabled' => false,
                'units_disabled' => false,
            ]
        ];
        $this->notes = '';
        $this->resetErrorBag();
    }

    public function addStockItem()
    {
        Log::info('Adding stock item');

        $this->newStockItems[] = [
            'product_id' => '',
            'available_boxes' => '',
            'available_units' => '',
            'cost_price' => '',
            'supplier' => '',
            'unit_cost' => '',
            'profit_margin' => '',
            'boxes_disabled' => false,
            'units_disabled' => false,
        ];
    }

    public function removeStockItem($index)
    {
        if (count($this->newStockItems) > 1) {
            unset($this->newStockItems[$index]);
            $this->newStockItems = array_values($this->newStockItems); // Re-index array
        }
    }

    public function updatedNewStockItems($value, $key)
    {
        // Parse the key to get index and field
        $parts = explode('.', $key);
        $index = $parts[0];
        $field = $parts[1];

        // Only process if we have a product selected
        if (!isset($this->newStockItems[$index]['product_id']) || empty($this->newStockItems[$index]['product_id'])) {
            return;
        }

        $product = Product::find($this->newStockItems[$index]['product_id']);
        if (!$product) {
            return;
        }

        // Convert string values to numbers for calculations
        $availableBoxes = (float) ($this->newStockItems[$index]['available_boxes'] ?? 0);
        $availableUnits = (float) ($this->newStockItems[$index]['available_units'] ?? 0);
        $costPrice = (float) ($this->newStockItems[$index]['cost_price'] ?? 0);
        $unitsPerBox = (float) ($product->units_per_box ?? 1);

        // Handle box/unit calculations
        if ($field === 'available_boxes' && !empty($value) && $unitsPerBox > 0) {
            // User is typing in boxes field - disable units and calculate units
            $this->newStockItems[$index]['units_disabled'] = true;
            $this->newStockItems[$index]['boxes_disabled'] = false;
            
            $calculatedUnits = $availableBoxes * $unitsPerBox;
            $this->newStockItems[$index]['available_units'] = $calculatedUnits;
            
        } elseif ($field === 'available_units' && !empty($value) && $unitsPerBox > 0) {
            // User is typing in units field - disable boxes and calculate boxes
            $this->newStockItems[$index]['boxes_disabled'] = true;
            $this->newStockItems[$index]['units_disabled'] = false;
            
            $calculatedBoxes = $availableUnits / $unitsPerBox;
            $this->newStockItems[$index]['available_boxes'] = round($calculatedBoxes, 2);
            
        } elseif (empty($value)) {
            // If field is cleared, enable both fields
            if ($field === 'available_boxes') {
                $this->newStockItems[$index]['units_disabled'] = false;
                $this->newStockItems[$index]['available_units'] = '';
            } elseif ($field === 'available_units') {
                $this->newStockItems[$index]['boxes_disabled'] = false;
                $this->newStockItems[$index]['available_boxes'] = '';
            }
        }

        // Calculate profit margin when cost price changes
        if ($field === 'cost_price' && $costPrice > 0 && $unitsPerBox > 0) {
            $this->calculateProfitMargin($index);
        }
    }

    private function calculateProfitMargin($index)
    {
        if (!isset($this->newStockItems[$index]['product_id']) || empty($this->newStockItems[$index]['product_id'])) {
            return;
        }

        $product = Product::find($this->newStockItems[$index]['product_id']);
        if (!$product) {
            return;
        }

        $costPrice = (float) ($this->newStockItems[$index]['cost_price'] ?? 0);

        Log::debug("Cost Price". $costPrice);
        $unitsPerBox = (float) ($product->units_per_box ?? 1);
        $sellingPrice = (float) $product->selling_price;

        if ($costPrice > 0 && $unitsPerBox > 0) {
            // Calculate unit cost
            $unitCost = $costPrice / $unitsPerBox;
            
            // Calculate profit margin per unit
            $profitMargin = $sellingPrice - $unitCost;
            
            // Update the item with calculated values
            $this->newStockItems[$index]['unit_cost'] = round($unitCost, 2);
            $this->newStockItems[$index]['profit_margin'] = round($profitMargin, 2);
        }
    }

    public function saveNewStock()
    {
        $this->validate();

        Log::info('Validated form input');

        // Filter out empty items
        $validItems = array_filter($this->newStockItems, function($item) {
            return !empty($item['product_id']) && (
                !empty($item['available_boxes']) || !empty($item['available_units'])
            ) && !empty($item['cost_price']) && !empty($item['supplier']);
        });

        Log::info('Valid items: ' . json_encode($validItems));

        if (empty($validItems)) {
            $this->addError('newStockItems', 'At least one item with quantities, cost price, and supplier is required');
            return;
        }

        $successCount = 0;

        foreach ($validItems as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) continue;

            // Convert to proper numeric values
            $boxesAdded = (float) ($item['available_boxes'] ?? 0);
            $unitsAdded = (float) ($item['available_units'] ?? 0);
            $costPrice = (float) $item['cost_price'];
            $supplier = $item['supplier'];

            // Calculate unit cost and profit margin
            $unitsPerBox = (float) ($product->units_per_box ?? 1);
            $unitCost = $unitsPerBox > 0 ? $costPrice / $unitsPerBox : $costPrice;
            $profitMargin = $product->selling_price - $unitCost;

            // Get existing stock entry for this product
            $existingStock = Stock::where('product_id', $product->id)->first();

            if ($existingStock) {
                // Update existing stock entry
                $existingStock->update([
                    'available_units' => $existingStock->available_units + $unitsAdded,
                    'available_boxes' => $existingStock->available_boxes + $boxesAdded,
                    'supplier' => $supplier,
                    'cost_price' => $costPrice,
                    'cost_margin' => $profitMargin,
                    'notes' => $this->notes,
                    'updated_at' => now()
                ]);
            } else {
                // Create new stock entry
                Stock::create([
                    'product_id' => $product->id,
                    'available_units' => $unitsAdded,
                    'available_boxes' => $boxesAdded,
                    'supplier' => $supplier,
                    'cost_price' => $costPrice,
                    'cost_margin' => $profitMargin,
                    'notes' => $this->notes
                ]);
            }

            // Create activity log
            $this->createActivityLog($product, $item, $profitMargin, $unitCost);

            $successCount++;
        }

        $this->closeAddStockModal();

        session()->flash('message', "Successfully added stock for {$successCount} product(s)");
    }

    private function createActivityLog($product, $stockItem, $profitMargin, $unitCost)
    {
        $description = "Stock updated for {$product->name}";
        
    $metadata = [
        'product_name' => $product->name,
        'product_id' => $product->id,
        'supplier' => $stockItem['supplier'],
        'cost_price' => (float) $stockItem['cost_price'],
        'unit_cost' => round($unitCost, 2),
        'selling_price' => (float) $product->selling_price,
        'profit_margin' => round($profitMargin, 2),
        'boxes_added' => (float) ($stockItem['available_boxes'] ?? 0),
        'units_added' => (float) ($stockItem['available_units'] ?? 0),
        'notes' => $this->notes,
        'timestamp' => now()
    ];

    Log::info('Creating activity log for stock update', $metadata);
    
    ActivityLogs::create([
        'user_id' => 1,
        // 'user_id' => Auth::id(),
        'action_type' => 'stock_update',
        'description' => $description,
        'entity_type' => 'stock_record',
        'entity_id' => $product->barcode ?? $product->sku ?? $product->id,
        'metadata' => json_encode($metadata) // Convert array to JSON string
    ]);

        Log::info('Activity log created for stock update', $metadata);
    }

    public function deleteStockEntry($productId)
    {
        $stock = Stock::where('product_id', $productId)->first();
        
        if ($stock) {
            $product = Product::find($productId);
            
            // Create activity log for deletion
            ActivityLogs::create([
                'user_id' => 1,
                // 'user_id' => Auth::id(),
                'action_type' => 'stock_delete',
                'description' => "Stock entry deleted for {$product->name}",
                'entity_type' => 'stock_record',
                'entity_id' => $productId,
                'metadata' => [
                    'product_name' => $product->name,
                    'deleted_boxes' => $stock->available_boxes,
                    'deleted_units' => $stock->available_units,
                    'supplier' => $stock->supplier,
                    'cost_price' => $stock->cost_price,
                    'timestamp' => now()->toISOString()
                ]
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
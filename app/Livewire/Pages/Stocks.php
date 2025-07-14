<?php

namespace App\Livewire\Pages;

use App\Models\Categories;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Category;
use Livewire\Component;
use Livewire\WithPagination;
use Carbon\Carbon;

class Stocks extends Component
{
    use WithPagination;

    public $searchTerm = '';
    public $selectedCategory = 'all';
    public $selectedDate;
    public $showTakeStockModal = false;
    public $addNewStockModal = false;
    public $stockData = [];

    public function mount()
    {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->loadStockData();
    }

    public function updatedSearchTerm()
    {
        $this->resetPage();
    }

    public function updatedSelectedCategory()
    {
        $this->resetPage();
    }

    public function updatedSelectedDate()
    {
        $this->loadStockData();
    }

    public function loadStockData()
    {
        $products = Product::with('category')->where('is_active', true)->get();
        
        $this->stockData = [];
        
        foreach ($products as $product) {
            $todayStock = Stock::where('product_id', $product->id)
                ->whereDate('created_at', $this->selectedDate)
                ->first();
            
            $this->stockData[$product->id] = [
                'product' => $product,
                'opening_units' => $todayStock->opening_units ?? 0,
                'opening_boxes' => $todayStock->opening_boxes ?? 0,
                'added_units' => $todayStock->added_units ?? 0,
                'closing_units' => $todayStock->closing_units ?? 0,
                'closing_boxes' => $todayStock->closing_boxes ?? 0,
                'sales_units' => $todayStock->sales_units ?? 0,
                'sales_boxes' => $todayStock->sales_boxes ?? 0,
                'has_stock_entry' => $todayStock !== null
            ];
        }
    }

    public function openTakeStockModal()
    {
        $this->showTakeStockModal = true;
        $this->loadStockData();
    }

    public function closeTakeStockModal()
    {
        $this->showTakeStockModal = false;
        $this->loadStockData();
    }
    public function openAddStockModal()
    {
        $this->addNewStockModal = true;
    }

    public function closeAddStockModal()
    {
        $this->addNewStockModal = false;
        $this->loadStockData();
    }

    public function updateStockField($productId, $field, $value)
    {
        if (isset($this->stockData[$productId])) {
            $this->stockData[$productId][$field] = $value;
            
            // Auto-calculate sales when closing stock is updated
            if ($field === 'closing_units' || $field === 'closing_boxes') {
                $this->calculateSales($productId);
            }
        }
    }

    private function calculateSales($productId)
    {
        $data = $this->stockData[$productId];
        
        // Calculate sales: Opening + Added - Closing = Sales
        $salesUnits = ($data['opening_units'] + $data['added_units']) - $data['closing_units'];
        $salesBoxes = ($data['opening_boxes']) - $data['closing_boxes'];
        
        $this->stockData[$productId]['sales_units'] = max(0, $salesUnits);
        $this->stockData[$productId]['sales_boxes'] = max(0, $salesBoxes);
    }

    public function saveStockEntry($productId)
    {
        $data = $this->stockData[$productId];
        
        Stock::updateOrCreate(
            [
                'product_id' => $productId,
                'created_at' => Carbon::parse($this->selectedDate)->startOfDay()
            ],
            [
                'opening_units' => $data['opening_units'],
                'opening_boxes' => $data['opening_boxes'],
                'added_units' => $data['added_units'],
                'closing_units' => $data['closing_units'],
                'closing_boxes' => $data['closing_boxes'],
                'sales_units' => $data['sales_units'],
                'sales_boxes' => $data['sales_boxes'],
                'updated_at' => now()
            ]
        );

        $this->stockData[$productId]['has_stock_entry'] = true;
        
        session()->flash('message', 'Stock entry saved successfully for ' . $data['product']->name);
    }

    public function saveAllStockEntries()
    {
        foreach ($this->stockData as $productId => $data) {
            $this->saveStockEntry($productId);
        }
        
        $this->closeTakeStockModal();
        session()->flash('message', 'All stock entries saved successfully for ' . Carbon::parse($this->selectedDate)->format('M d, Y'));
    }

    public function deleteStockEntry($productId)
    {
        Stock::where('product_id', $productId)
            ->whereDate('created_at', $this->selectedDate)
            ->delete();
        
        $this->loadStockData();
        session()->flash('message', 'Stock entry deleted successfully');
    }

    public function render()
    {
        $categories = Categories::all();
        
        $query = Stock::with(['product.category'])
            ->whereDate('created_at', $this->selectedDate);

        if ($this->searchTerm) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'like', '%' . $this->searchTerm . '%')
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
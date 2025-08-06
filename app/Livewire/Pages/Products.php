<?php

namespace App\Livewire\Pages;

use App\Models\{Categories, Product, Stock};
use Livewire\Component;
use Livewire\{WithPagination,WithoutUrlPagination};

class Products extends Component
{
    use WithPagination, WithoutUrlPagination; 
    
    public $searchTerm = '';
    public $selectedCategory = 'all';
    
    protected $queryString = ['searchTerm', 'selectedCategory'];
    
    public function updatingSearchTerm()
    {
        $this->resetPage();
    }
    
    public function updatingSelectedCategory()
    {
        $this->resetPage();
    }
    
    public function getFilteredProductsProperty()
    {
        $query = Product::with(['category', 'stocks'])
            ->when($this->searchTerm, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('sku', 'like', '%' . $this->searchTerm . '%');
                });
            })
            ->when($this->selectedCategory !== 'all', function ($query) {
                $query->whereHas('category', function ($q) {
                    $q->where('name', $this->selectedCategory);
                });
            })
            ->orderBy('name');
            
        return $query->paginate(10);
    }
    
    public function getCategoriesProperty()
    {
        return Categories::orderBy('name')->get();
    }
    
    public function getCurrentStock($productId)
    {
        // Ensure product_id is cast as integer
        $productId = (int) $productId;
        
        // Get the latest stock entry for this product
        $stock = Stock::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->first();
        
        return $stock ? $stock->total_units : 0;
    }
    
    public function getStockStatus($productId, $stockLimit)
    {
        // Ensure product_id is cast as integer
        $productId = (int) $productId;
        $currentStock = $this->getCurrentStock($productId);
        
        if ($currentStock <= 0) {
            return [
                'text' => 'No Stock',
                'color' => 'text-red-600 bg-red-100',
                'current' => $currentStock
            ];
        } elseif ($stockLimit && $currentStock <= $stockLimit) {
            return [
                'text' => 'Low Stock',
                'color' => 'text-yellow-600 bg-yellow-100',
                'current' => $currentStock
            ];
        } else {
            return [
                'text' => 'Good Stock',
                'color' => 'text-green-600 bg-green-100',
                'current' => $currentStock
            ];
        }
    }
    
    public function calculateMargin($sellingPrice, $productId)
    {
        // Ensure product_id is cast as integer
        $productId = (int) $productId;
        
        $stock = Stock::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->first();

        $costPrice = $stock ? $stock->cost_price : 0;
        
        if ($costPrice <= 0) {
            return 0;
        }
        
        return ($sellingPrice - $costPrice);
    }
    
    public function toggleProductStatus($productId)
    {
        // Ensure product_id is cast as integer
        $productId = (int) $productId;
        
        $product = Product::find($productId);
        if ($product) {
            $product->update(['is_active' => !$product->is_active]);
            session()->flash('message', 'Product status updated successfully!');
        }
    }
    
    public function deleteProduct($productId)
    {
        // Ensure product_id is cast as integer
        $productId = (int) $productId;
        
        $product = Product::find($productId);
        if ($product) {
            $product->delete();
            session()->flash('message', 'Product deleted successfully!');
        }
    }
    
    public function render()
    {
        return view('livewire.pages.products', [
            'products' => $this->filteredProducts,
            'categories' => $this->categories
        ]);
    }
}
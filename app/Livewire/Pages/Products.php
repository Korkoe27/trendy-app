<?php

namespace App\Livewire\Pages;

use App\Models\Categories;
use Livewire\Component;
use App\Models\Product;
use App\Models\Category;
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
        $query = Product::with('category')
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
    
    public function getStockStatus($currentStock, $minStock)
    {
        if ($currentStock <= 0) {
            return [
                'text' => 'Out of Stock',
                'color' => 'text-red-600 bg-red-100'
            ];
        } elseif ($currentStock <= $minStock) {
            return [
                'text' => 'Low Stock',
                'color' => 'text-yellow-600 bg-yellow-100'
            ];
        } else {
            return [
                'text' => 'In Stock',
                'color' => 'text-green-600 bg-green-100'
            ];
        }
    }
    
    public function calculateMargin($costPrice, $sellingPrice)
    {
        if ($costPrice <= 0) {
            return 0;
        }
        
        return (($sellingPrice - $costPrice) / $costPrice) * 100;
    }
    
    public function toggleProductStatus($productId)
    {
        $product = Product::find($productId);
        if ($product) {
            $product->update(['is_active' => !$product->is_active]);
            session()->flash('message', 'Product status updated successfully!');
        }
    }
    
    public function deleteProduct($productId)
    {
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
<?php

namespace App\Livewire\Pages;

use App\Models\{ActivityLogs, Categories, Product, Stock};
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\{WithPagination, WithoutUrlPagination};

class Products extends Component
{
    use WithPagination, WithoutUrlPagination;

    public $product = null;          // currently viewed product
    public $showModal = false;       // single flag for modal
    public $searchTerm = '';

    public $productId;
    public $name = '';
    public $category_id = '';
    public $barcode = '';
    public $selling_price = '';
    public $stock_limit = '';
    public $is_active = true;
    public $editModal = false;
    public $selectedCategory = 'all';
    public $stockHistory = [];
    protected $listeners = ['editProduct'];

    protected $queryString = ['searchTerm', 'selectedCategory'];

    
    protected $rules = [
        'name' => 'required|string|max:255',
        'category_id' => 'required|exists:categories,id',
        'barcode' => 'nullable|string|max:255',
        'selling_price' => 'required|numeric|min:0',
        'stock_limit' => 'nullable|integer|min:0',
        'is_active' => 'boolean'
    ];

    public function updatingSearchTerm()   { $this->resetPage(); }
    public function updatingSelectedCategory() { $this->resetPage(); }

    public function viewProduct($productId)
    {
        $this->product = Product::with(['category', 'stocks'])->find((int) $productId);

        if ($this->product) {
            $this->stockHistory = Stock::where('product_id', $this->product->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $this->showModal = true;
        }
    }

    public function closeModal()
    {
        $this->reset(['showModal', 'product', 'stockHistory']);
    }

    // ----- Derived data for list -----
    public function getFilteredProductsProperty()
    {
        $query = Product::with(['category', 'stocks'])
            ->when($this->searchTerm, fn ($q) =>
                $q->where(function ($qq) {
                    $qq->where('name', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('sku', 'like', '%' . $this->searchTerm . '%');
                })
            )
            ->when($this->selectedCategory !== 'all', fn ($q) =>
                $q->whereHas('category', fn ($qq) =>
                    $qq->where('name', $this->selectedCategory)
                )
            )
            ->orderBy('name');

        return $query->paginate(10);
    }

    public function getCategoriesProperty()
    {
        return Categories::orderBy('name')->get();
    }

    // ----- Helpers -----
    public function getCurrentStock(int $productId): int
    {
        $stock = Stock::where('product_id', $productId)->latest('created_at')->first();
        return $stock?->total_units ?? 0;
    }

    public function getStockStatus(int $productId, ?int $stockLimit): array
    {
        $currentStock = $this->getCurrentStock($productId);

        if ($currentStock <= 0) {
            return ['text' => 'No Stock', 'color' => 'text-red-600 bg-red-100', 'current' => $currentStock];
        }

        if ($stockLimit && $currentStock <= $stockLimit) {
            return ['text' => 'Low Stock', 'color' => 'text-yellow-600 bg-yellow-100', 'current' => $currentStock];
        }

        return ['text' => 'Good Stock', 'color' => 'text-green-600 bg-green-100', 'current' => $currentStock];
    }

    public function calculateMargin(float $sellingPrice, int $productId): float
    {
        $stock = Stock::where('product_id', $productId)->latest('created_at')->first();
        $costPrice = $stock?->cost_price ?? 0;
        if ($costPrice <= 0) { return 0; }
        return ($sellingPrice - $costPrice);
    }

    public function toggleProductStatus($productId)
    {
        $product = Product::find((int) $productId);
        if ($product) {
            $product->update(['is_active' => ! $product->is_active]);
            session()->flash('message', 'Product status updated successfully!');
        }
    }


    
    
    public function editProduct($productId)
    {
        $this->productId = $productId;
        $product = Product::find($productId);
        
        if ($product) {
            $this->name = $product->name;
            $this->category_id = $product->category_id;
            $this->barcode = $product->barcode;
            $this->selling_price = $product->selling_price;
            $this->stock_limit = $product->stock_limit;
            $this->is_active = $product->is_active;
            $this->editModal = true;
        }
    }
    
    public function updateProduct()
    {
        $this->validate();
        
        $product = Product::find($this->productId);
        
        if ($product) {
            $product->update([
                'name' => $this->name,
                'category_id' => $this->category_id,
                'barcode' => $this->barcode,
                'selling_price' => $this->selling_price,
                'stock_limit' => $this->stock_limit,
                'is_active' => $this->is_active,
            ]);
            
            $this->closeEditModal();
            $this->dispatch('productUpdated');
            session()->flash('message', 'Product updated successfully!');
        }
        $metadata = [
            'product_id' => $this->productId,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'barcode' => $this->barcode,
            'selling_price' => $this->selling_price,
            'stock_limit' => $this->stock_limit,
            'is_active' => $this->is_active,
        ];
        $description = "Product ID {$this->productId} updated by " . Auth::id();
            ActivityLogs::create([
            'user_id'=> Auth::id(),
            // 'user_id'=>1,
            'action_type'=>'create_product',
            'description' => $description,
            'entity_type' => 'product_update',
            'metadata' => json_encode($metadata),
            'entity_id'=>null
        ]);
    }
    
    public function closeEditModal()
    {
        $this->editModal = false;
        $this->reset(['name', 'category_id', 'barcode', 'selling_price', 'stock_limit', 'is_active']);
        $this->resetValidation();
    }

    public function deleteProduct($productId)
    {
        $product = Product::find((int) $productId);
        if ($product) {
            $product->delete();
            session()->flash('message', 'Product deleted successfully!');
            $this->resetPage();
        }


            $metadata = [
            'product_id' => $this->productId,
            'name' => $product->name,
            'category_id' => $this->category_id,
            'barcode' => $this->barcode,
            'selling_price' => $this->selling_price,
            'stock_limit' => $this->stock_limit,
            'is_active' => $this->is_active,
        ];
        $description = "Product ID {$this->productId} updated by " . Auth::id();
            ActivityLogs::create([
            'user_id'=> Auth::id(),
            // 'user_id'=>1,
            'action_type'=>'delete_product',
            'description' => $description,
            'entity_type' => 'product_delete',
            'metadata' => json_encode($metadata),
            'entity_id'=>null
        ]);
    }

    public function render()
    {
        return view('livewire.pages.products', [
            'products'   => $this->filteredProducts,
            'categories' => $this->categories,
        ]);
    }
}

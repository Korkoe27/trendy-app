<?php

namespace App\Livewire\Pages;

use App\Models\ActivityLogs;
use App\Models\Categories;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

class Products extends Component
{
    use WithoutUrlPagination,WithFileUploads, WithPagination;

    public $product = null;          // currently viewed product

    public $showModal = false;       // single flag for modal

    public $searchTerm = '';

    public $importFile;

    public bool $showImportModal = false;

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
        'importFile' => 'nullable|file|mimes:xlsx,xls,csv|max:2048',
        'is_active' => 'boolean',
    ];

    public function updatingSearchTerm()
    {
        $this->resetPage();
    }

    public function updatingSelectedCategory()
    {
        $this->resetPage();
    }

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

    public function exportProducts()
{
    $filePath = storage_path('app/products_export_' . now()->format('Y-m-d_His') . '.csv');
    
    $header = [
        'name',
        'category_id',
        'sku',
        'barcode',
        'stock_limit',
        'selling_price',
        'units_per_box',
        'is_active'
    ];
    
    $file = fopen($filePath, 'w');
    fputcsv($file, $header);
    
    // Export existing products (optional - remove if you only want template)
    $products = Product::with('category')->get();
    foreach ($products as $product) {
        fputcsv($file, [
            $product->name,
            $product->category->name,
            $product->sku ?? '',
            $product->barcode ?? '',
            $product->stock_limit ?? '',
            $product->selling_price,
            $product->units_per_box ?? '',
            $product->is_active ? '1' : '0'
        ]);
    }
    
    fclose($file);
    
    return response()->download($filePath)->deleteFileAfterSend(true);
}

public function exportTemplate()
{
    $filePath = storage_path('app/products_import_template.csv');
    
    $header = [
        'name',
        'category_id',
        'sku',
        'barcode',
        'stock_limit',
        'selling_price',
        'units_per_box',
        'is_active'
    ];
    
    $file = fopen($filePath, 'w');
    fputcsv($file, $header);
    
    // Add example row
    fputcsv($file, [
        'small club beer',
        '1',
        'SKU123',
        '1234567890',
        '100',
        '5.50',
        '24',
        '1'
    ]);
    
    fclose($file);
    
    return response()->download($filePath)->deleteFileAfterSend(true);
}

public function importProducts()
{
    $this->validate([
        'importFile' => 'required|file|mimes:xlsx,xls,csv|max:2048'
    ]);
    
    try {
        $path = $this->importFile->getRealPath();
        $file = fopen($path, 'r');
        
        // Read and validate header
        $header = fgetcsv($file);
        $expectedHeader = ['name', 'category_id', 'sku', 'barcode', 'stock_limit', 'selling_price', 'units_per_box', 'is_active'];
        
        if ($header !== $expectedHeader) {
            $this->addError('importFile', 'Invalid file format. Please use the correct template.');
            fclose($file);
            return;
        }
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $rowNumber = 1;
        
        while (($row = fgetcsv($file)) !== false) {
            $rowNumber++;
            
            try {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }
                
                // Prepare data
                $productData = [
                    'name' => strtolower(trim($row[0])),
                    'category_id' => trim($row[1]),
                    'sku' => !empty($row[2]) ? strtolower(trim($row[2])) : null,
                    'barcode' => !empty($row[3]) ? trim($row[3]) : null,
                    'stock_limit' => !empty($row[4]) ? (int)$row[4] : null,
                    'selling_price' => !empty($row[5]) ? (float)$row[5] : 0.00,
                    'units_per_box' => !empty($row[6]) ? (int)$row[6] : 0,
                    'is_active' => !empty($row[7]) ? (bool)$row[7] : true,
                ];
                
                // Validate required fields
                if (empty($productData['name']) || empty($productData['category_id'])) {
                    $errors[] = "Row {$rowNumber}: Name and Category are required";
                    $errorCount++;
                    continue;
                }
                
                // Check if category exists
                if (!Categories::find($productData['category_id'])) {
                    $errors[] = "Row {$rowNumber}: Category ID {$productData['category_id']} does not exist";
                    $errorCount++;
                    continue;
                }
                
                // Create product
                Product::create($productData);
                $successCount++;
                
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
        }
        
        fclose($file);
        
        // Log activity
        ActivityLogs::create([
            'user_id' => Auth::id(),
            'action_type' => 'import_products',
            'description' => "Imported {$successCount} products from CSV/Excel",
            'entity_type' => 'product_import',
            'metadata' => json_encode([
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'errors' => $errors
            ]),
            'entity_id' => null
        ]);
        
        // Show results
        if ($successCount > 0) {
            $message = "Successfully imported {$successCount} product(s)";
            if ($errorCount > 0) {
                $message .= " with {$errorCount} error(s)";
            }
            session()->flash('message', $message);
            
            if (!empty($errors)) {
                session()->flash('import_errors', array_slice($errors, 0, 10)); // Show first 10 errors
            }
        } else {
            $this->addError('importFile', 'No products were imported. ' . implode(', ', array_slice($errors, 0, 5)));
        }
        
        $this->showImportModal = false;
        $this->reset('importFile');
        
    } catch (\Exception $e) {
        $this->addError('importFile', 'Import failed: ' . $e->getMessage());
        Log::error('Product import error', ['error' => $e->getMessage()]);
    }
}
    public function getFilteredProductsProperty()
    {
        $query = Product::with(['category', 'stocks'])
            ->when($this->searchTerm, fn ($q) => $q->where(function ($qq) {
                $qq->where('name', 'like', '%'.$this->searchTerm.'%')
                    ->orWhere('sku', 'like', '%'.$this->searchTerm.'%');
            })
            )
            ->when($this->selectedCategory !== 'all', fn ($q) => $q->whereHas('category', fn ($qq) => $qq->where('name', $this->selectedCategory)
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
        if ($costPrice <= 0) {
            return 0;
        }

        return $sellingPrice - $costPrice;
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
        $description = "Product ID {$this->productId} updated by ".Auth::id();
        ActivityLogs::create([
            'user_id' => Auth::id(),
            // 'user_id'=>1,
            'action_type' => 'create_product',
            'description' => $description,
            'entity_type' => 'product_update',
            'metadata' => json_encode($metadata),
            'entity_id' => null,
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
        $description = "Product ID {$this->productId} updated by ".Auth::id();
        ActivityLogs::create([
            'user_id' => Auth::id(),
            // 'user_id'=>1,
            'action_type' => 'delete_product',
            'description' => $description,
            'entity_type' => 'product_delete',
            'metadata' => json_encode($metadata),
            'entity_id' => null,
        ]);
    }

    public function render()
    {
        return view('livewire.pages.products', [
            'products' => $this->filteredProducts,
            'categories' => $this->categories,
        ]);
    }
}

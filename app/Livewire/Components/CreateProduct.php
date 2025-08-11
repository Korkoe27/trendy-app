<?php

namespace App\Livewire\Components;

use App\Models\{Categories,Product,ActivityLogs};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class CreateProduct extends Component
{
    public bool $showModal = false;
    public array $newProducts = [];
    public $categories = [];

    protected array $rules = [
        'newProducts.*.name' => 'required|string|max:255|unique:products,name',
        'newProducts.*.category_id' => 'required|exists:categories,id',
        'newProducts.*.sku' => 'nullable|string|max:255|unique:products,sku',
        'newProducts.*.barcode' => 'nullable|string|max:255|unique:products,barcode',
        'newProducts.*.stock_limit' => 'nullable|integer|min:0', // Fixed: Removed invalid 'products,stock_limit'
        'newProducts.*.selling_price' => 'required|numeric|min:0',
        'newProducts.*.units_per_box' => 'nullable|numeric|min:0',
    ];

    protected array $messages = [
        'newProducts.*.name.required' => 'Product name is required',
        'newProducts.*.name.unique' => 'Product name must be unique',
        'newProducts.*.category_id.required' => 'Category selection is required',
        'newProducts.*.category_id.exists' => 'Selected category does not exist',
        'newProducts.*.sku.unique' => 'SKU must be unique',
        'newProducts.*.barcode.unique' => 'Barcode must be unique',
        'newProducts.*.selling_price.required' => 'Selling price is required',
        'newProducts.*.selling_price.numeric' => 'Selling price must be a valid number',
        'newProducts.*.units_per_box.numeric' => 'Units per box must be a valid number',
    ];

    public function mount()
    {
        $this->categories = Categories::all();
        $this->resetProductForm();
    }

    public function resetProductForm()
    {
        $this->newProducts = [[
            'name' => '',
            'category_id' => '',
            'sku' => '',
            'barcode' => '',
            'stock_limit' => '',
            'selling_price' => '',
            'units_per_box' => '',
        ]];

        $this->resetErrorBag();
    }

    public function addProduct()
    {
        $this->newProducts[] = [
            'name' => '',
            'category_id' => '',
            'sku' => '',
            'barcode' => '',
            'stock_limit' => '',
            'selling_price' => '',
            'units_per_box' => '',
        ];
    }

    public function removeProduct($index)
    {
        if (count($this->newProducts) > 1) {
            unset($this->newProducts[$index]);
            $this->newProducts = array_values($this->newProducts); // Re-index array
        }
    }


    public function saveProducts()
    {
        $this->validateProducts();

        Log::info('Saving products', ['products' => $this->newProducts]);
        $validProducts = array_filter($this->newProducts, function ($product) {
            return !empty($product['name']) &&
                !empty($product['category_id']) &&
                !empty($product['selling_price']);
        });

        Log::info('Valid products for saving', ['validProducts' => $validProducts]);
        if (empty($validProducts)) {
            $this->addError('newProducts', 'At least one complete product is required');
            return;
        }

        $successCount = 0;
        $errors = [];

        foreach ($validProducts as $index => $productData) {
            try {
                // Lowercase string fields
                foreach ($productData as $key => $value) {
                    if (is_string($value)) {
                        $productData[$key] = strtolower($value);
                    }
                }

                $cleanedData = array_filter($productData, function ($value) {
                    return $value !== '' && $value !== null;
                });

                $cleanedData['selling_price'] = $cleanedData['selling_price'] ?? 0.00;
                $cleanedData['units_per_box'] = $cleanedData['units_per_box'] ?? 0.00;

                Product::create($cleanedData);
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "Product " . ($index + 1) . ": " . $e->getMessage();
            }
        }


        if ($successCount > 0) {
            $this->showModal = false;
            $this->resetProductForm();

            $message = "Successfully created {$successCount} product(s)";
            if (!empty($errors)) {
                $message .= ". Errors: " . implode(', ', $errors);
            }

            session()->flash('message', $message);
        } else {
            foreach ($errors as $error) {
                $this->addError('newProducts', $error);
            }
        }

        $description = "Created {$successCount} new product(s)";

        $metadata = [
            'products' => $validProducts,
        ];

        ActivityLogs::create([
            'user_id'=> Auth::id(),
            // 'user_id'=>1,
            'action_type'=>'create_product',
            'description' => $description,
            'entity_type' => 'product_creation',
            'metadata' => json_encode($metadata),
            'entity_id'=>null
        ]);

        Log::info('Activity log created for products creation: ', $metadata);
        return redirect()->route('products');
    }

    private function validateProducts()
    {
        $names = [];
        $skus = [];
        $barcodes = [];

        foreach ($this->newProducts as $index => $product) {
            if (!empty($product['name'])) {
                if (in_array($product['name'], $names)) {
                    $this->addError("newProducts.{$index}.name", 'Duplicate product name in form');
                }
                $names[] = $product['name'];
            }

            if (!empty($product['sku'])) {
                if (in_array($product['sku'], $skus)) {
                    $this->addError("newProducts.{$index}.sku", 'Duplicate SKU in form');
                }
                $skus[] = $product['sku'];
            }

            if (!empty($product['barcode'])) {
                if (in_array($product['barcode'], $barcodes)) {
                    $this->addError("newProducts.{$index}.barcode", 'Duplicate barcode in form');
                }
                $barcodes[] = $product['barcode'];
            }
        }

        $this->validate();
    }

    public function render()
    {
        return view('livewire.components.create-product');
    }
}
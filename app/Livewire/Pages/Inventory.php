<?php

namespace App\Livewire\Pages;

use App\Models\ActivityLogs;
use App\Models\DailySales;
use App\Models\DailySalesSummary;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class Inventory extends Component
{
    public $showTakeInventoryModal = false;

    public $showDetailsModal = false;

    public $selectedRecord = null;

    public $currentStep = 1;

    public $salesDate;

    public $allProducts;

    public $allStocks;

    // public $errors = [];
    public $dateError = null;

    public $stockErrors = [];

    private $productStockMap = [];

    public $isEditing = false;

    public $editingRecordId = null;

    public $editingOriginalRecord = null;

    public $selectedDate;

    // Form data
    public $onTheHouse = '';

    public $snooker = '';

    public $cashAmount = '';

    public $momoAmount = '';

    public $creditCustomerData = [];

    public $showCreditCustomerModal = false;

    public $pendingCreditProducts = [];

    public $foodTotal = '';

    public $hubtelAmount = '';

    public $productStocks = [];

    private $stocksCache = [];

    public function mount()
    {

        $currentHour = (int) now()->format('H');
        if ($currentHour >= 0 && $currentHour < 6) {
            $this->selectedDate = now()->subDay()->format('Y-m-d');
            $this->salesDate = now()->subDay()->format('Y-m-d');
        } else {
            $this->selectedDate = now()->format('Y-m-d');
            $this->salesDate = now()->format('Y-m-d');
        }

        // OPTIMIZATION: Preload ALL data in 2 queries instead of N queries
        $this->allProducts = DB::table('products')
            ->select('id', 'name', 'units_per_box', 'selling_price', 'is_active')
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $productIds = $this->allProducts->pluck('id')->toArray();

        // Get LATEST stock for each product (most recent created_at)
        $this->allStocks = DB::table('stocks as s1')
            ->select('s1.id', 's1.product_id', 's1.total_units', 's1.cost_price', 's1.cost_margin')
            ->whereIn('s1.product_id', $productIds)
            ->where('s1.total_units', '>', 0)
            ->whereRaw('s1.created_at = (SELECT MAX(s2.created_at) FROM stocks s2 WHERE s2.product_id = s1.product_id)')
            ->get()
            ->keyBy('product_id');

        // Create a fast lookup map
        foreach ($this->allStocks as $stock) {
            $this->productStockMap[$stock->product_id] = $stock->id;
        }
    }

    protected $rules = [
        'cashAmount' => 'required|numeric|min:0',
        'momoAmount' => 'required|numeric|min:0',
        'hubtelAmount' => 'required|numeric|min:0',
        'foodTotal' => 'required|numeric|min:0',
        'onTheHouse' => 'required|numeric|min:0',
        'snooker' => 'required|numeric|min:0',
    ];

    public function updatedCashAmount($value)
    {
        if ($value < 0) {
            $this->cashAmount = 0;

            return;
        }
        $this->cashAmount = $value;
        $this->validateOnly('cashAmount');
    }

    public function updatedMomoAmount($value)
    {
        if ($value < 0) {
            $this->momoAmount = 0;

            return;
        }

        $this->momoAmount = $value;
        $this->validateOnly('momoAmount');
    }

    public function updatedSalesDate($value)
    {
        // Ensure sales date is not in the future
        if ($value && strtotime($value) > time()) {
            $this->salesDate = now()->format('Y-m-d');
            session()->flash('error', 'Sales date cannot be in the future');
        }
    }

    protected function validateStockInputs()
    {
        $this->stockErrors = [];
        $hasErrors = false;

        foreach ($this->productStocks as $productId => $stockData) {
            $product = $this->allProducts[$productId] ?? null;
            if (! $product) {
                continue;
            }

            $closingUnits = (float) ($stockData['closing_units'] ?? 0);

            // Check if closing units is empty
            if (! filled($stockData['closing_units'])) {
                $this->stockErrors[$productId] = 'Please enter closing stock';
                Log::debug("Closing units empty for product ID: {$productId}");
                $hasErrors = true;

                continue;
            }

            // Get opening stock based on edit mode
            if ($this->isEditing && isset($stockData['original_opening_stock'])) {
                $openingStock = $stockData['original_opening_stock'];
                Log::debug("Using original opening stock for product ID: {$productId} - Opening Stock: {$openingStock}");
            } else {
                $currentStock = $this->allStocks[$productId] ?? null;
                $openingStock = $currentStock ? $currentStock->total_units : 0;
                Log::debug("Using current stock for product ID: {$productId} - Opening Stock: {$openingStock}");
            }

            // Check if closing stock is greater than opening stock
            if ($closingUnits > $openingStock) {
                $this->stockErrors[$productId] = "Closing stock ({$closingUnits}) cannot exceed opening stock ({$openingStock})";
                Log::debug("Closing stock exceeds opening stock for product ID: {$productId}");
                $hasErrors = true;
            }

            // **NEW: Validate credit units**
            $creditUnits = (float) ($stockData['credit_units'] ?? 0);
            $damagedUnits = (float) ($stockData['damaged_units'] ?? 0);

            if ($creditUnits > 0) {
                // Credit units cannot exceed units available for sale
                $maxCreditUnits = $openingStock - $closingUnits - $damagedUnits;

                if ($creditUnits > $maxCreditUnits) {
                    $this->stockErrors[$productId] = "Credit units ({$creditUnits}) cannot exceed available units ({$maxCreditUnits})";
                    Log::debug("Credit units exceed available units for product ID: {$productId}");
                    $hasErrors = true;
                }
            }
        }

        return ! $hasErrors;
    }

    public function updatedHubtelAmount($value)
    {
        $this->hubtelAmount = $value;
    }

    public function updateFoodTotal($value)
    {
        $this->foodTotal = $value;
    }

    public function updatedOnTheHouse($value)
    {
        if ($value < 0) {
            $this->onTheHouse = 0;

            return;
        }
        $this->onTheHouse = $value;
    }

    public function updateSnooker($value)
    {
        if ($value < 0) {
            $this->snooker = 0;

            return;
        }
        $this->onTheHouse = $value;
    }

    public function collectCreditCustomerInfo()
    {
        // Check if there are any products with credit units
        $hasCredits = false;
        $this->pendingCreditProducts = [];

        foreach ($this->productStocks as $productId => $stockData) {
            $creditUnits = (float) ($stockData['credit_units'] ?? 0);
            if ($creditUnits > 0) {
                $hasCredits = true;
                $product = $this->allProducts[$productId] ?? null;
                if ($product) {
                    $this->pendingCreditProducts[$productId] = [
                        'name' => $product->name,
                        'units' => $creditUnits,
                        'amount' => $creditUnits * ($product->selling_price ?? 0),
                    ];

                    // Initialize customer data if not exists
                    if (! isset($this->creditCustomerData[$productId])) {
                        $this->creditCustomerData[$productId] = [
                            'name' => '',
                            'phone' => '',
                        ];
                    }
                }
            }
        }

        if ($hasCredits) {
            $this->showCreditCustomerModal = true;

            return true;
        }

        return false;
    }

    public function closeCreditCustomerModal()
    {
        $this->showCreditCustomerModal = false;
    }

    public function proceedWithInventory()
    {
        // Validate credit customer data
        foreach ($this->pendingCreditProducts as $productId => $creditInfo) {
            $customerData = $this->creditCustomerData[$productId] ?? null;
            if (! $customerData || empty($customerData['name']) || empty($customerData['phone'])) {
                session()->flash('error', 'Please provide customer name and phone for all credit items.');

                return;
            }
        }

        $this->showCreditCustomerModal = false;

        // Now call the actual submit
        if ($this->isEditing) {
            $this->updateInventory();
        } else {
            $this->actuallySubmitInventory();
        }
    }

    public function openTakeInventoryModal()
    {
        $this->showTakeInventoryModal = true;
        $this->resetForm();
        $this->loadProductStocks();
    }

    public function openEditModal($recordId)
    {
        $this->isEditing = true;
        $this->editingRecordId = $recordId;
        $this->editingOriginalRecord = $this->getDailySalesRecord($recordId);

        // dd($this->editingOriginalRecord);
        if (! $this->editingOriginalRecord) {
            session()->flash('error', 'Record not found');

            return;
        }

        $this->salesDate = $this->editingOriginalRecord['date'];
        // Pre-fill form with existing data
        $this->cashAmount = $this->editingOriginalRecord['total_cash'];
        $this->momoAmount = $this->editingOriginalRecord['total_momo'];
        $this->hubtelAmount = $this->editingOriginalRecord['total_hubtel'];
        $this->foodTotal = $this->editingOriginalRecord['food_total'];
        $this->snooker = $this->editingOriginalRecord['snooker'];
        $this->onTheHouse = $this->editingOriginalRecord['on_the_house'] ?? 0;

        // Load existing product stocks
        $this->loadEditingProductStocks();

        $this->showTakeInventoryModal = true;
        // $this->currentStep = auth()->user()->role === 'admin' ? 1 : 4; // Start at money step for admin, stock step for others
        $this->currentStep = Auth::user() ? 1 : 2;
    }

    public function getOriginalOpeningStock($productId)
    {
        return $this->productStocks[$productId]['original_opening_stock'] ?? 0;
    }

    private function loadEditingProductStocks()
    {
        $salesDate = $this->editingOriginalRecord['date'];

        Log::debug('Loading editing product stocks for date: '.$salesDate);

        $dailySales = DB::table('daily_sales')
            ->where('sales_date', $salesDate)
            ->get()
            ->keyBy('product_id');

        $productIdsWithSales = $dailySales->pluck('product_id')->toArray();

        Log::debug('Product IDs with sales on that date: ', $productIdsWithSales);

        $products = Product::whereIn('id', $productIdsWithSales)->get();

        Log::debug('Total products to load for editing: '.$products->count());

        foreach ($products as $product) {
            $existingProduct = collect($this->editingOriginalRecord['products'])
                ->firstWhere('product_name', $product->name);

            $dailySale = $dailySales[$product->id] ?? null;
            $openingStockForDisplay = $dailySale ? $dailySale->opening_stock : 0;

            if ($existingProduct) {
                $this->productStocks[$product->id] = [
                    'closing_boxes' => '',
                    'closing_units' => (string) $existingProduct->closing_stock,
                    'damaged_units' => (string) ($existingProduct->damaged_units ?? ''),
                    'credit_units' => (string) ($existingProduct->credit_units ?? ''),
                    'product' => $product,
                    'original_opening_stock' => $openingStockForDisplay,
                ];
            } else {
                $this->productStocks[$product->id] = [
                    'closing_boxes' => '',
                    'closing_units' => '',
                    'damaged_units' => '',
                    'credit_units' => '',
                    'product' => $product,
                    'original_opening_stock' => $openingStockForDisplay,
                ];
            }
        }

        // **NEW: Load existing credit customer data**
        $existingCredits = DB::table('credit_sales')
            ->whereIn('daily_sale_id', function ($query) use ($salesDate) {
                $query->select('id')
                    ->from('daily_sales')
                    ->where('sales_date', $salesDate);
            })
            ->get()
            ->keyBy('product_id');

        foreach ($existingCredits as $productId => $credit) {
            $this->creditCustomerData[$productId] = [
                'name' => $credit->customer_name,
                'phone' => $credit->customer_phone,
            ];
        }
    }

    public function closeTakeInventoryModal()
    {
        $this->showTakeInventoryModal = false;
        $this->isEditing = false;
        $this->editingRecordId = null;
        $this->editingOriginalRecord = null;
        $this->resetForm();
    }

    public function updateInventory()
    {

        if (! $this->isEditing || ! $this->editingRecordId) {
            session()->flash('error', 'Invalid edit operation');

            return;
        }

        Log::debug('Updating inventory - Record ID: '.$this->editingRecordId);

        // Validate based on user role
        if (Auth::user()) {
            try {
                $this->validate([
                    'cashAmount' => 'required|numeric|min:0',
                    'momoAmount' => 'required|numeric|min:0',
                    'hubtelAmount' => 'required|numeric|min:0',
                    'foodTotal' => 'required|numeric|min:0',
                    'snooker' => 'required|numeric|min:0',
                    'onTheHouse' => 'required|numeric|min:0',
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                Log::debug('Validation failed', $e->errors());
                throw $e;
            }
        }

        Log::debug('Product stocks before validation:', [
            'count' => count($this->productStocks),
            'sample' => array_slice($this->productStocks, 0, 2, true), // Log first 2 products
        ]);
        // Validate stock inputs
        if (! $this->validateStockInputs()) {

            Log::debug('Stock validation failed', $this->stockErrors);
            session()->flash('error', 'Please correct the stock errors before updating.');

            return;
        }

        Log::debug('Validated stock inputs successfully');

        DB::transaction(function () {
            $summary = DailySalesSummary::find($this->editingRecordId);

            if (! $summary) {
                throw new \Exception('Summary record not found');
            }

            $salesDate = $this->salesDate ?? $summary->sales_date;

            Log::debug('Editing sales for date: '.$salesDate);

            // Get all existing daily_sales records for this date BEFORE deletion
            $existingDailySales = DB::table('daily_sales')
                ->where('sales_date', $salesDate)
                ->get()
                ->keyBy('product_id');

            // Delete old daily_sales records for this date
            DailySales::where('sales_date', $salesDate)->delete();

            $drinksTotal = 0;
            $totalItemsSold = 0;
            $totalProfit = 0;
            $totalDamaged = 0;
            $totalCredit = 0;
            $totalCreditAmount = 0;
            $totalLossAmount = 0;
            $mostSoldProductId = null;
            $maxUnitsSold = 0;

            $salesRows = [];
            $newStockRows = [];
            $stockIdsToKeep = []; // Track which stock IDs should be preserved

            foreach ($this->productStocks as $productId => $stockData) {
                if (! filled($stockData['closing_units'])) {
                    continue;
                }

                $product = $this->allProducts[$productId] ?? null;

                if (! $product) {
                    Log::warning("Product not found: $productId");

                    continue;
                }

                $closingUnits = (float) ($stockData['closing_units'] ?? 0);
                $damagedUnits = (float) ($stockData['damaged_units'] ?? 0);
                $creditUnits = (float) ($stockData['credit_units'] ?? 0);

                // Get the ORIGINAL opening stock from the existing record
                $openingStock = $stockData['original_opening_stock'] ?? 0;

                Log::debug("Product $product->name - Opening: $openingStock, Closing: $closingUnits");

                if ($closingUnits > $openingStock) {
                    throw new \Exception("Closing stock for $product->name cannot exceed opening stock.");
                }

                $openingBoxes = $product->units_per_box > 0
                    ? floor($openingStock / $product->units_per_box)
                    : 0;

                $closingBoxes = $product->units_per_box > 0
                    ? floor($closingUnits / $product->units_per_box)
                    : 0;

                // Calculate units sold
                $unitsSold = max(0, $openingStock - $closingUnits - $damagedUnits - $creditUnits);

                $sellingPrice = $product->selling_price ?? 0;
                $cashRevenue = $unitsSold * $sellingPrice;
                $creditAmount = $creditUnits * $sellingPrice;
                $lossAmount = $damagedUnits * $sellingPrice;
                $productRevenue = $cashRevenue + $creditAmount;

                // Get the stock entry that was used for the ORIGINAL sale
                $existingSale = $existingDailySales[$productId] ?? null;
                $originalStockId = $existingSale ? $existingSale->stock_id : null;

                // Get cost margin from the ORIGINAL stock used
                $originalStock = $originalStockId
                    ? DB::table('stocks')->find($originalStockId)
                    : null;

                $costMargin = $originalStock ? $originalStock->cost_margin : 0;
                $costPrice = $originalStock ? $originalStock->cost_price : 0;
                $unitProfit = $unitsSold * $costMargin;

                Log::debug("Product $product->name - Units Sold: $unitsSold, Profit: $unitProfit");

                $drinksTotal += $productRevenue;
                $totalItemsSold += $unitsSold;
                $totalProfit += $unitProfit;
                $totalDamaged += $damagedUnits;
                $totalCredit += $creditUnits;
                $totalCreditAmount += $creditAmount;
                $totalLossAmount += $lossAmount;

                if ($unitsSold > $maxUnitsSold) {
                    $maxUnitsSold = $unitsSold;
                    $mostSoldProductId = $productId;
                }

                // Check if we need to create a NEW stock entry or reuse existing
                // We need a new stock ONLY if the closing units changed
                $needsNewStock = false;
                $stockIdToUse = $originalStockId;

                if ($originalStock) {
                    // If closing stock changed, we need to update or create new stock
                    if ($originalStock->total_units != $closingUnits) {
                        $needsNewStock = true;
                    }
                } else {
                    // No original stock found, need to create new
                    $needsNewStock = true;
                }

                if ($needsNewStock) {
                    // Create new stock entry with closing units
                    // Replace the existing $newStockRows[] assignment with:
                    $newStockRows[] = [
                        'product_id' => $productId,
                        'total_units' => $closingUnits,
                        'total_cost' => $currentStock->total_cost ?? 0,
                        'cost_price' => $currentStock->cost_price ?? 0,
                        'cost_margin' => $sellingPrice - ($currentStock->cost_price ?? 0),
                        'free_units' => 0,
                        'notes' => 'Stock updated via inventory - '.$salesDate,
                        'restock_date' => null,
                        'metadata' => json_encode([
                            'source' => 'daily_inventory',
                            'previous_stock_id' => $currentStock->id ?? null,
                            'units_sold' => $unitsSold,
                            'created_by' => Auth::id(),
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $stockIdToUse = null; // Will be set after insert
                } else {
                    // Keep the original stock
                    $stockIdsToKeep[] = $originalStockId;
                }

                // Prepare sales row (stock_id will be updated later if new stock created)
                // Replace the $salesRows[$productId] assignment in updateInventory with:
                $salesRows[$productId] = [
                    'product_id' => $productId,
                    'stock_id' => $stockIdToUse,
                    'sales_date' => $salesDate,
                    'opening_stock' => $openingStock,
                    'closing_stock' => $closingUnits,
                    'opening_boxes' => $openingBoxes,
                    'closing_boxes' => $closingBoxes,
                    'damaged_units' => $damagedUnits,
                    'credit_units' => $creditUnits,
                    'credit_amount' => $creditAmount,
                    'loss_amount' => $lossAmount,
                    'total_amount' => $cashRevenue,
                    'unit_profit' => $unitProfit,
                    'metadata' => json_encode([
                        'edited_by' => Auth::id(),
                        'selling_price' => $sellingPrice,
                        'cost_margin' => $costMargin,
                        'is_edited' => true,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert new stock entries if needed
            if (! empty($newStockRows)) {
                DB::table('stocks')->insert($newStockRows);
                Log::info('Created new stock entries', ['count' => count($newStockRows)]);

                // Get newly created stock IDs
                $newStockIds = DB::table('stocks')
                    ->whereIn('product_id', array_column($newStockRows, 'product_id'))
                    ->where('created_at', '>=', now()->subSeconds(5))
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->keyBy('product_id');

                // Update sales rows with new stock IDs
                foreach ($salesRows as $productId => &$salesRow) {
                    if ($salesRow['stock_id'] === null) {
                        $newStock = $newStockIds[$productId] ?? null;
                        $salesRow['stock_id'] = $newStock ? $newStock->id : null;
                    }
                }
            }

            // Insert updated sales records
            if (! empty($salesRows)) {
                DB::table('daily_sales')->insert(array_values($salesRows));
                Log::debug('Inserted updated sales records', ['count' => count($salesRows)]);
            }

            // Inside updateInventory(), after "Insert updated sales records" section
            if (! empty($salesRows)) {
                DB::table('daily_sales')->insert(array_values($salesRows));
                Log::debug('Inserted updated sales records', ['count' => count($salesRows)]);

                // **NEW: Handle credit sales for edited records**
                // First, delete old credit sales for this date
                DB::table('credit_sales')
                    ->whereIn('daily_sale_id', function ($query) use ($salesDate) {
                        $query->select('id')
                            ->from('daily_sales')
                            ->where('sales_date', $salesDate);
                    })
                    ->delete();

                // Get the newly inserted daily_sales IDs
                $insertedSales = DB::table('daily_sales')
                    ->where('sales_date', $salesDate)
                    ->whereIn('product_id', array_keys($salesRows))
                    ->where('created_at', '>=', now()->subSeconds(5))
                    ->get()
                    ->keyBy('product_id');

                // Create new credit sales if credits exist
                $creditSalesRows = [];
                foreach ($this->productStocks as $productId => $stockData) {
                    $creditUnits = (float) ($stockData['credit_units'] ?? 0);
                    if ($creditUnits > 0 && isset($insertedSales[$productId])) {
                        $product = $this->allProducts[$productId] ?? null;
                        $customerData = $this->creditCustomerData[$productId] ?? null;

                        if ($product && $customerData) {
                            $creditAmount = $creditUnits * ($product->selling_price ?? 0);

                            $creditSalesRows[] = [
                                'daily_sale_id' => $insertedSales[$productId]->id,
                                'product_id' => $productId,
                                'customer_name' => $customerData['name'],
                                'customer_phone' => $customerData['phone'],
                                'units_credited' => $creditUnits,
                                'credit_amount' => $creditAmount,
                                'credit_date' => $salesDate,
                                'status' => 'unpaid',
                                'metadata' => json_encode([
                                    'edited_by' => Auth::id(),
                                    'selling_price' => $product->selling_price ?? 0,
                                    'product_name' => $product->name,
                                    'is_edited' => true,
                                ]),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }

                if (! empty($creditSalesRows)) {
                    DB::table('credit_sales')->insert($creditSalesRows);
                    Log::debug('Created credit sales records for edit', ['count' => count($creditSalesRows)]);
                }
            }
            // Calculate total_money correctly: sum of all payment methods
            $totalMoney = $this->cashAmount + $this->momoAmount + $this->hubtelAmount;

            Log::info('Total money calculated: '.$totalMoney);

            // Update summary with correct calculations
            $updateData = [
                'drinks_total' => $drinksTotal,
                'items_sold' => $totalItemsSold,
                'total_profit' => $totalProfit,
                'total_damaged' => $totalDamaged,
                'total_credit_units' => $totalCredit,
                'total_credit_amount' => $totalCreditAmount,
                'total_loss_amount' => $totalLossAmount,
                'total_cash' => (float) $this->cashAmount,
                'total_momo' => (float) $this->momoAmount,
                'total_hubtel' => (float) $this->hubtelAmount,
                'food_total' => (float) $this->foodTotal,
                'snooker' => (float) $this->snooker,
                'on_the_house' => (float) $this->onTheHouse,
                'total_money' => $totalMoney, // This is correct now
                'sales_date' => $salesDate, // Update date if changed
            ];

            if ($mostSoldProductId) {
                $updateData['product_id'] = $mostSoldProductId;
            }

            $summary->update($updateData);

            Log::info('Summary updated', $updateData);

            ActivityLogs::create([
                'user_id' => Auth::id(),
                'action_type' => 'daily_sales_edit',
                'description' => 'Daily Sales record edited for '.$salesDate,
                'entity_type' => 'inventory',
                'entity_id' => $this->editingRecordId,
                'metadata' => json_encode([
                    'updated_fields' => array_keys($updateData),
                    'drink_sales' => $drinksTotal,
                    'items_sold' => $totalItemsSold,
                    'total_profit' => $totalProfit,
                ]),
            ]);
        });

        $this->closeTakeInventoryModal();
        session()->flash('success', 'Sales record updated successfully!');
    }

    public function resetForm()
    {
        $this->currentStep = 1;
        $currentHour = (int) now()->format('H');
        $this->salesDate = ($currentHour >= 0 && $currentHour < 6)
            ? now()->subDay()->format('Y-m-d')
            : now()->format('Y-m-d');
        $this->productStocks = [];
    }

    public function resetAllFormData()
    {
        $this->currentStep = 1;
        $this->cashAmount = '';
        $this->momoAmount = '';
        $this->hubtelAmount = '';
        $this->foodTotal = '';
        $this->onTheHouse = '';
        $this->productStocks = [];
    }

    public function loadProductStocks()
    {
        foreach ($this->allStocks as $stock) {
            $product = $this->allProducts[$stock->product_id] ?? null;

            Log::debug('Loading stock for product ID '.$stock->product_id.': '.($product ? $product->name : 'not found'));
            if (! $product || $stock->total_units <= 0) {
                continue;
            }

            $this->productStocks[$stock->product_id] = [
                'closing_boxes' => '',
                'closing_units' => '',
                'damaged_units' => '',
                'credit_units' => '',
                'product' => $stock,  // Stock object
                'stock' => $product,  // Product object
            ];
        }
    }

    public function nextStep()
    {
        if ($this->currentStep == 1) {
            // Validate money inputs
            $this->validate([
                'cashAmount' => 'required|numeric|min:0',
                'momoAmount' => 'required|numeric|min:0',
                'hubtelAmount' => 'required|numeric|min:0',
                'snooker' => 'required|numeric|min:0',
                'foodTotal' => 'required|numeric|min:0',
                'onTheHouse' => 'required|numeric|min:0',
            ]);
        }

        $this->currentStep++;
    }

    public function previousStep()
    {
        $this->currentStep--;
    }

    public function calculateTotalUnits($productId)
    {
        $stock = $this->productStocks[$productId] ?? null;

        if (! $stock) {
            return 0;
        }

        $product = $this->allProducts[$productId] ?? null;
        if (! $product) {
            return 0;
        }

        $boxes = (int) ($stock['closing_boxes'] ?? 0);
        $units = (int) ($stock['closing_units'] ?? 0);
        $unitsPerBox = $product->units_per_box ?? 1;

        return $boxes * $unitsPerBox + $units;
    }

    public function calculateExpectedRevenue($productId)
    {
        $entry = $this->productStocks[$productId] ?? null;
        if (! $entry) {
            return 0;
        }

        $product = $this->allProducts[$productId] ?? null;
        $stock = $this->allStocks[$productId] ?? null;

        if (! $product || ! $stock) {
            return 0;
        }

        if ($this->isEditing && isset($entry['original_opening_stock'])) {
            $openingStock = $entry['original_opening_stock'];
        } else {
            $stock = $this->allStocks[$productId] ?? null;
            if (! $stock) {
                return 0;
            }
            $openingStock = $stock->total_units;
        }

        // $openingStock = $stock->total_units;
        $closingStock = $this->calculateTotalUnits($productId);
        $damagedUnits = (float) ($entry['damaged_units'] ?? 0);
        $creditUnits = (float) ($entry['credit_units'] ?? 0);

        $unitsSold = max(0, $openingStock - $closingStock - $damagedUnits - $creditUnits);
        $sellingPrice = $product->selling_price ?? 0;

        return ($unitsSold * $sellingPrice) + ($creditUnits * $sellingPrice);
    }

    public function submitInventory()
    {
        Log::debug('submitting inventory');
        $this->dateError = null;
        $this->stockErrors = [];

        $this->validate([
            'cashAmount' => 'required|numeric|min:0',
            'momoAmount' => 'required|numeric|min:0',
            'hubtelAmount' => 'required|numeric|min:0',
            'snooker' => 'required|numeric|min:0',
            'foodTotal' => 'required|numeric|min:0',
            'onTheHouse' => 'required|numeric|min:0',
        ]);

        Log::debug('validated');

        // Check if there are credits and collect customer info first
        if ($this->collectCreditCustomerInfo()) {
            return; // Will show modal, user clicks proceed
        }

        // If no credits, proceed directly
        $this->actuallySubmitInventory();
    }

    public function actuallySubmitInventory()
    {
        $recordDate = $this->salesDate ?: now()->format('Y-m-d');

        Log::debug('fetched record date: '.$recordDate);

        // Check for existing record using DB facade
        $existingRecord = DB::table('daily_sales_summaries')
            ->where('sales_date', $recordDate)
            ->exists();

        Log::debug('existing record: '.$existingRecord);

        if ($existingRecord) {
            session()->flash('error', 'A sales record already exists for '.\Carbon\Carbon::parse($this->salesDate)->format('M j, Y').'. Please edit the existing record instead.');

            return;
        }

        if (! $this->validateStockInputs()) {
            session()->flash('error', 'Please correct the stock errors below before submitting.');

            return;
        }

        DB::transaction(function () {
            $recordDate = $this->salesDate ?: now()->format('Y-m-d');
            $drinksTotal = 0;
            $totalItemsSold = 0;
            $totalProfit = 0;
            $totalDamaged = 0;
            $totalCredit = 0;
            $totalCreditAmount = 0;
            $totalLossAmount = 0;
            $mostSoldProductId = null;
            $maxUnitsSold = 0;

            $productIds = array_keys($this->productStocks);
            $products = Product::select('id', 'units_per_box', 'selling_price')
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            Log::debug('Retrieved Products: '.$products);

            $stocks = Stock::whereIn('product_id', $productIds)
                ->where('total_units', '>', 0)
                ->get()->keyBy('product_id');

            $salesRows = [];
            $newStockRows = [];
            $stockUpdates = [];

            foreach ($this->productStocks as $productId => $stockData) {
                if (! filled($stockData['closing_units'])) {
                    continue;
                }

                $product = $this->allProducts[$productId] ?? null;
                $currentStock = $this->allStocks[$productId] ?? null;

                Log::debug('Current stock for product '.$productId.': '.($currentStock ? $currentStock->total_units : 'not found'));

                if (! $currentStock || ! $product) {
                    continue;
                }

                $closingBoxes = (float) ($stockData['closing_boxes'] ?? 0);
                $closingUnits = (float) ($stockData['closing_units'] ?? 0);
                $damagedUnits = (float) ($stockData['damaged_units'] ?? 0);
                $creditUnits = (float) ($stockData['credit_units'] ?? 0);

                Log::debug('Processing product ID: '.$productId);
                Log::debug('Closing Units: '.$closingUnits);

                $openingStock = $currentStock->total_units;

                if ($closingUnits > $openingStock) {
                    throw new \Exception('Closing stock for product '.$product->name.' cannot be greater than opening stock.');
                }

                $openingBoxes = $product->units_per_box > 0 ?
                    floor($openingStock / $product->units_per_box) : 0;

                $unitsSold = max(0, $openingStock - $closingUnits - $damagedUnits - $creditUnits);

                Log::debug('Units Sold: '.$unitsSold);
                $sellingPrice = $product->selling_price ?? 0;
                $cashRevenue = $unitsSold * $sellingPrice;
                $creditAmount = $creditUnits * $sellingPrice;
                $lossAmount = $damagedUnits * $sellingPrice;
                $productRevenue = $cashRevenue + $creditAmount;

                $unitProfit = $unitsSold * $currentStock->cost_margin;

                Log::debug('Stock Cost Margin: '.$currentStock->cost_margin);
                Log::debug('unitProfit: '.$unitProfit);

                $drinksTotal += $productRevenue;
                $totalItemsSold += $unitsSold;
                $totalProfit += $unitProfit;
                $totalDamaged += $damagedUnits;
                $totalCredit += $creditUnits;
                $totalCreditAmount += $creditAmount;
                $totalLossAmount += $lossAmount;

                if ($unitsSold > $maxUnitsSold) {
                    $maxUnitsSold = $unitsSold;
                    $mostSoldProductId = $productId;
                }

                Log::info('calculated sales');

                // Replace the existing $newStockRows[] assignment with:
                $newStockRows[] = [
                    'product_id' => $productId,
                    'total_units' => $closingUnits,
                    'total_cost' => $currentStock->total_cost ?? 0,
                    'cost_price' => $currentStock->cost_price ?? 0,
                    'cost_margin' => $sellingPrice - ($currentStock->cost_price ?? 0),
                    'free_units' => 0,
                    'notes' => 'Stock updated via inventory - '.$recordDate,
                    'restock_date' => null,
                    'metadata' => json_encode([
                        'source' => 'daily_inventory',
                        'previous_stock_id' => $currentStock->id ?? null,
                        'units_sold' => $unitsSold,
                        'created_by' => Auth::id(),
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $salesRows[] = [
                    'product_id' => $productId,
                    'stock_id' => null,
                    'sales_date' => $recordDate,
                    'opening_stock' => $openingStock,
                    'closing_stock' => $closingUnits,
                    'opening_boxes' => $openingBoxes,
                    'closing_boxes' => $closingBoxes,
                    'damaged_units' => $damagedUnits,
                    'credit_units' => $creditUnits,
                    'credit_amount' => $creditAmount,
                    'loss_amount' => $lossAmount,
                    'total_amount' => $cashRevenue,
                    'unit_profit' => $unitProfit,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                Log::debug('checked new sales for product: '.$productId);
            }

            Log::debug('prepared all sales rows');

            if (! empty($newStockRows)) {
                DB::table('stocks')->insert($newStockRows);
                Log::info('Created new stock entries', ['count' => count($newStockRows)]);

                $newStockIds = DB::table('stocks')
                    ->whereIn('product_id', array_column($newStockRows, 'product_id'))
                    ->where('created_at', '>=', now()->subSeconds(5))
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->keyBy('product_id');

                foreach ($salesRows as &$salesRow) {
                    $newStock = $newStockIds[$salesRow['product_id']] ?? null;
                    $salesRow['stock_id'] = $newStock ? $newStock->id : null;
                }
            }

            if (! empty($salesRows)) {
                DB::table('daily_sales')->insert($salesRows);
                Log::debug('Submitted new Sales', ['count' => count($salesRows)]);

                // **NEW: Get the inserted daily_sales IDs and create credit sales**
                $insertedSales = DB::table('daily_sales')
                    ->where('sales_date', $recordDate)
                    ->whereIn('product_id', array_column($salesRows, 'product_id'))
                    ->where('created_at', '>=', now()->subSeconds(5))
                    ->get()
                    ->keyBy('product_id');

                // Create credit sales for products with credits
                $creditSalesRows = [];
                foreach ($this->productStocks as $productId => $stockData) {
                    $creditUnits = (float) ($stockData['credit_units'] ?? 0);
                    if ($creditUnits > 0 && isset($insertedSales[$productId])) {
                        $product = $this->allProducts[$productId] ?? null;
                        $customerData = $this->creditCustomerData[$productId] ?? null;

                        if ($product && $customerData) {
                            $creditAmount = $creditUnits * ($product->selling_price ?? 0);

                            $creditSalesRows[] = [
                                'daily_sale_id' => $insertedSales[$productId]->id,
                                'product_id' => $productId,
                                'customer_name' => $customerData['name'],
                                'customer_phone' => $customerData['phone'],
                                'units_credited' => $creditUnits,
                                'credit_amount' => $creditAmount,
                                'credit_date' => $recordDate,
                                'status' => 'unpaid',
                                'metadata' => json_encode([
                                    'selling_price' => $product->selling_price ?? 0,
                                    'recorded_by' => Auth::id(),
                                    'product_name' => $product->name,
                                ]),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }

                if (! empty($creditSalesRows)) {
                    DB::table('credit_sales')->insert($creditSalesRows);
                    Log::debug('Created credit sales records', ['count' => count($creditSalesRows)]);
                }
            }

            Log::debug('updated all stocks');

            if ($mostSoldProductId && $drinksTotal > 0) {
                // Replace the DB::table('daily_sales_summaries')->insert with:
                DB::table('daily_sales_summaries')->insert([
                    'drinks_total' => $drinksTotal,
                    'total_money' => $this->cashAmount + $this->momoAmount + $this->hubtelAmount,
                    'items_sold' => $totalItemsSold,
                    'total_profit' => $totalProfit,
                    'sales_date' => $recordDate,
                    'total_cash' => (float) $this->cashAmount,
                    'total_momo' => (float) $this->momoAmount,
                    'total_hubtel' => (float) $this->hubtelAmount,
                    'food_total' => (float) $this->foodTotal,
                    'on_the_house' => (float) $this->onTheHouse,
                    'snooker' => (float) $this->snooker,
                    'total_damaged' => $totalDamaged,
                    'total_credit_units' => $totalCredit,
                    'total_credit_amount' => $totalCreditAmount,
                    'total_loss_amount' => $totalLossAmount,
                    'product_id' => $mostSoldProductId,
                    'metadata' => json_encode([
                        'recorded_by' => Auth::id(),
                        'products_count' => count($salesRows),
                        'has_credits' => $totalCredit > 0,
                        'has_damages' => $totalDamaged > 0,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('activity_logs')->insert([
                    'user_id' => Auth::id(),
                    'action_type' => 'daily_sales',
                    'description' => 'Daily Sales recorded for '.$recordDate,
                    'entity_type' => 'inventory',
                    'entity_id' => 'inventory',
                    'metadata' => json_encode([
                        'drinks_total' => $drinksTotal,
                        'items_sold' => $totalItemsSold,
                        'total_profit' => $totalProfit,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Log::debug('created summary record');
        });

        $this->closeTakeInventoryModal();
        session()->flash('success', 'Inventory recorded successfully!');
    }

    public function openDetailsModal($recordId)
    {
        $this->selectedRecord = $this->getDailySalesRecord($recordId);
        $this->showDetailsModal = true;
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->selectedRecord = null;
        $this->resetAllFormData();
    }

    public function getDailySalesRecords()
    {
        return DB::table('daily_sales_summaries')
            ->select(
                'sales_date as date',
                'total_cash',
                'total_momo',
                'total_hubtel',
                'snooker',
                'drinks_total',
                'food_total',
                'on_the_house',
                'total_money',
                'items_sold as total_products',
                'id as first_id'
            )
            ->orderBy('sales_date', 'desc')
            ->paginate(15);
    }

    public function getDailySalesRecord($recordId)
    {
        $summary = DB::table('daily_sales_summaries')
            ->where('id', $recordId)
            ->first();

        if (! $summary) {
            return null;
        }

        $targetDate = $summary->sales_date;

        // Single query with all joins
        $dailySales = DB::table('daily_sales as ds')
            ->join('products as p', 'ds.product_id', '=', 'p.id')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->select(
                'p.name as product_name',
                'c.name as category',
                'ds.opening_stock',
                'ds.closing_stock',
                'ds.opening_boxes',
                'ds.closing_boxes',
                'ds.damaged_units',
                'ds.credit_units',
                'ds.credit_amount',
                'ds.loss_amount',
                'ds.total_amount',
                DB::raw('(ds.opening_stock - ds.closing_stock - COALESCE(ds.damaged_units, 0) - COALESCE(ds.credit_units, 0)) as units_sold'),
                DB::raw('(ds.opening_boxes - ds.closing_boxes) as boxes_sold'),
                DB::raw('(ds.total_amount + COALESCE(ds.credit_amount, 0)) as revenue')
            )
            ->where('ds.sales_date', $targetDate)
            ->get();

        return [
            'id' => $recordId,
            'date' => $targetDate,
            'total_cash' => $summary->total_cash,
            'total_momo' => $summary->total_momo,
            'total_hubtel' => $summary->total_hubtel,
            'drinks_total' => $summary->drinks_total,
            'snooker' => $summary->snooker,
            'total_money' => $summary->total_money,
            'on_the_house' => $summary->on_the_house,
            'food_total' => $summary->food_total,
            'total_profit' => $summary->total_profit,
            'total_loss_amount' => $summary->total_loss_amount,
            'total_credit_amount' => $summary->total_credit_amount,
            'total_credit_units' => $summary->total_credit_units,
            'total_damaged' => $summary->total_damaged,
            'products' => $dailySales,
        ];
    }

    public function render()
    {
        $dailySalesRecords = $this->getDailySalesRecords();

        // Use cached products instead of querying again
        $products = collect($this->allProducts->values())
            ->filter(function ($product) {
                $stock = $this->allStocks[$product->id] ?? null;

                return $stock && $stock->total_units > 0;
            })
            ->map(function ($product) {
                $stock = $this->allStocks[$product->id];
                // Convert stdClass to object with stocks property for Blade compatibility
                $product->stocks = $stock;

                return $product;
            });

        return view('livewire.pages.inventory', [
            'dailySalesRecords' => $dailySalesRecords,
            'products' => $products,
        ]);
    }
}

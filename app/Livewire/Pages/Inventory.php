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

    private $productStockMap = [];

    public $isEditing = false;

    public $editingRecordId = null;

    public $editingOriginalRecord = null;

    public $selectedDate;

    // Form data
    public $onTheHouse = '';

    public $cashAmount = '';

    public $momoAmount = '';

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
            ->select('s1.id', 's1.product_id', 's1.total_units', 's1.cost_price','s1.cost_margin')
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

        dd($this->editingOriginalRecord);
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
        $this->onTheHouse = $this->editingOriginalRecord['on_the_house'] ?? 0;

        // Load existing product stocks
        $this->loadEditingProductStocks();

        $this->showTakeInventoryModal = true;
        // $this->currentStep = auth()->user()->role === 'admin' ? 1 : 4; // Start at money step for admin, stock step for others
        $this->currentStep = Auth::user() ? 1 : 6;
    }

    public function getOriginalOpeningStock($productId)
    {
        return $this->productStocks[$productId]['original_opening_stock'] ?? 0;
    }
private function loadEditingProductStocks()
    {
        $salesDate = $this->editingOriginalRecord['date'];

        Log::debug('Loading editing product stocks for date: '.$salesDate);

        // Get the daily_sales for this date
        $dailySales = DB::table('daily_sales')
            ->where('sales_date', $salesDate)
            ->get()
            ->keyBy('product_id');

        // Get all active products
        $products = Product::with('currentStock')
            ->where('is_active', true)
            ->get();

        foreach ($products as $product) {
            // Find existing data for this product in the record being edited
            $existingProduct = collect($this->editingOriginalRecord['products'])
                ->firstWhere('product_name', $product->name);

            // dd($existingProduct);

            $dailySale = $dailySales[$product->id] ?? null;

            // Use the opening stock from the record being edited as the "current" stock
            $openingStockForDisplay = $dailySale ? $dailySale->opening_stock : 0;

            if ($existingProduct) {
                $closingStock = $existingProduct->closing_stock;
                Log::debug('Product ' . $product->name . ' closingStock: ' . $closingStock);
                $closingBoxes = floor($closingStock / ($product->units_per_box ?: 1));
                $remainingUnits = $closingStock - ($closingBoxes * ($product->units_per_box ?: 1));

                $this->productStocks[$product->id] = [
                    'closing_boxes' => $closingBoxes,
                    'closing_units' => $remainingUnits,
                    'damaged_units' => $existingProduct->damaged_units,
                    'credit_units' => $existingProduct->credit_units,
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
    }

    // public function updateInventory()
    // {
    //     if (! $this->isEditing || ! $this->editingRecordId) {
    //         session()->flash('error', 'Invalid edit operation');

    //         return;
    //     }

    //     // Validate based on user role
    //     if (Auth::user()) {
    //         // if (auth()->user()->role === 'admin') {
    //         $this->validate([
    //             'cashAmount' => 'required|numeric|min:0',
    //             'momoAmount' => 'required|numeric|min:0',
    //             'hubtelAmount' => 'required|numeric|min:0',
    //             'foodTotal' => 'required|numeric|min:0',
    //             'onTheHouse' => 'required|numeric|min:0',
    //         ]);
    //     }

    //     DB::transaction(function () {
    //         // Get the original summary record
    //         $summary = DailySalesSummary::find($this->editingRecordId);

    //         if (! $summary) {
    //             throw new \Exception('Summary record not found');
    //         }

    //         // Delete existing daily sales for this date

    //         $salesDate = $summary->sales_date ?: $summary->created_at->format('Y-m-d');
    //         DailySales::where('sales_date', $salesDate)->delete();

    //         $totalRevenue = 0;
    //         $totalItemsSold = 0;
    //         $totalProfit = 0;
    //         $totalDamaged = 0;
    //         $totalCredit = 0;
    //         $totalCreditAmount = 0;
    //         $totalLossAmount = 0;
    //         $mostSoldProductId = null;
    //         $maxUnitsSold = 0;

    //         $salesRows = [];
    //         $stockUpdates = [];
    //         // Process each product's updated inventory (same logic as submitInventory)
    //         foreach ($this->productStocks as $productId => $stockData) {

    //             if (! filled($stockData['closing_boxes']) && ! filled($stockData['closing_units'])) {
    //                 continue;
    //             }

    //             $product = Product::find($productId);
    //             $stock = Stock::where('product_id', $productId)->first();

    //             if (! $stock || ! $product) {
    //                 continue;
    //             }

    //             $closingBoxes = (float) ($stockData['closing_boxes'] ?? 0);
    //             $closingUnits = (float) ($stockData['closing_units'] ?? 0);


    //             $damagedUnits = (float) ($stockData['damaged_units'] ?? 0);
    //             $creditUnits = (float) ($stockData['credit_units'] ?? 0);

    //             $totalClosingUnits = $this->calculateTotalUnits($productId);

    //             // Use original opening stock for consistency
    //             $originalProduct = collect($this->editingOriginalRecord['products'])
    //                 ->firstWhere('product_name', $product->name);
    //             $openingStock = $originalProduct ? $originalProduct->opening_stock : $stock->total_units;

    //             if($closingUnits > $openingStock){
    //                 throw new \Exception('Closing stock for product '.$product->name.' cannot be greater than opening stock.');
    //             }

    //             $openingBoxes = $product->units_per_box > 0 ?
    //                 floor($openingStock / $product->units_per_box) : 0;

    //             $unitsSold = max(0, $openingStock - $totalClosingUnits - $damagedUnits - $creditUnits);
    //             $sellingPrice = $product->selling_price ?? 0;
    //             $cashRevenue = $unitsSold * $sellingPrice;
    //             $creditAmount = $creditUnits * $sellingPrice;
    //             $lossAmount = $damagedUnits * $sellingPrice;
    //             $productRevenue = $cashRevenue + $creditAmount;

    //             $totalSoldUnits = $unitsSold + $creditUnits;
    //             $unitProfit = $totalSoldUnits * (($product->selling_price ?? 0) - ($stock->cost_price ?? 0));

    //             $totalRevenue += $productRevenue;
    //             $totalItemsSold += $totalSoldUnits;
    //             $totalProfit += $unitProfit;
    //             $totalDamaged += $damagedUnits;
    //             $totalCredit += $creditUnits;
    //             $totalCreditAmount += $creditAmount;
    //             $totalLossAmount += $lossAmount;

    //             if ($unitsSold > $maxUnitsSold) {
    //                 $maxUnitsSold = $unitsSold;
    //                 $mostSoldProductId = $productId;
    //             }

    //             // Create updated daily sales record

    //             $salesRows[] = [
    //                 'product_id' => $productId,
    //                 'stock_id' => $stock->id,
    //                 'sales_date' => $salesDate,
    //                 'opening_stock' => $openingStock,
    //                 'closing_stock' => $closingUnits,
    //                 'opening_boxes' => $openingBoxes,
    //                 'closing_boxes' => $closingBoxes,
    //                 'damaged_units' => $damagedUnits,
    //                 'credit_units' => $creditUnits,
    //                 'credit_amount' => $creditAmount,
    //                 'loss_amount' => $lossAmount,
    //                 'total_amount' => $cashRevenue,
    //                 'unit_profit' => $unitProfit,
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ];

    //             $stockUpdates[$stock->id] = $closingUnits;

    //             // // Update stock with new closing values
    //             // $stock->update([
    //             //     'total_units' => $totalClosingUnits,
    //             // ]);
    //         }

    //         if (! empty($salesRows)) {
    //             DB::table('daily_sales')->insert($salesRows);
    //             Log::info('Submitted new Sales', ['count' => count($salesRows)]);
    //         }

    //         if (! empty($stockUpdates)) {
    //             foreach ($stockUpdates as $stockId => $totalUnits) {
    //                 DB::table('stocks')
    //                     ->where('id', $stockId)
    //                     ->update([
    //                         'total_units' => $totalUnits,
    //                         'updated_at' => now(),
    //                     ]);
    //             }
    //         }
    //         // Update the summary record
    //         $updateData = [
    //             'total_revenue' => $totalRevenue,
    //             'items_sold' => $totalItemsSold,
    //             'total_profit' => $totalProfit,
    //             'total_damaged' => $totalDamaged,
    //             'total_credit_units' => $totalCredit,
    //             'total_credit_amount' => $totalCreditAmount,
    //             'total_loss_amount' => $totalLossAmount,
    //         ];

    //         // Only update money fields if user is admin
    //         if (Auth::user()) {
    //             // if (auth()->user()->role === 'admin') {
    //             $updateData['total_cash'] = (float) $this->cashAmount;
    //             $updateData['total_momo'] = (float) $this->momoAmount;
    //             $updateData['total_hubtel'] = (float) $this->hubtelAmount;
    //             $updateData['total_money'] = $this->cashAmount + $this->momoAmount + $this->hubtelAmount + $this->foodTotal;
    //         }

    //         if ($mostSoldProductId) {
    //             $updateData['product_id'] = $mostSoldProductId;
    //         }

    //         $summary->update($updateData);

    //         // Log the edit activity
    //         ActivityLogs::create([
    //             'user_id' => Auth::id(),
    //             'action_type' => 'daily_sales_edit',
    //             'description' => 'Daily Sales record edited for '.$summary->created_at->format('Y-m-d'),
    //             'entity_type' => 'inventory',
    //             'entity_id' => $this->editingRecordId,
    //             'metadata' => json_encode([
    //                 // 'user_role' => auth()->user()->role,

    //                 'updated_fields' => array_keys($updateData),
    //                 'total_revenue' => $totalRevenue,
    //                 'items_sold' => $totalItemsSold,
    //             ]),
    //         ]);
    //     });

    //     $this->closeTakeInventoryModal();
    //     session()->flash('success', 'Sales record updated successfully!');
    // }

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

    // Validate based on user role
    if (Auth::user()) {
        $this->validate([
            'cashAmount' => 'required|numeric|min:0',
            'momoAmount' => 'required|numeric|min:0',
            'hubtelAmount' => 'required|numeric|min:0',
            'foodTotal' => 'required|numeric|min:0',
            'onTheHouse' => 'required|numeric|min:0',
        ]);
    }

    DB::transaction(function () {
        $summary = DailySalesSummary::find($this->editingRecordId);

        if (! $summary) {
            throw new \Exception('Summary record not found');
        }

        $salesDate = $summary->sales_date ?: $summary->created_at->format('Y-m-d');
        DailySales::where('sales_date', $salesDate)->delete();

        $totalRevenue = 0;
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

        foreach ($this->productStocks as $productId => $stockData) {
            if (! filled($stockData['closing_units'])) {
                continue;
            }

            $product = $this->allProducts[$productId] ?? null;
            $currentStock = $this->allStocks[$productId] ?? null;

            if (! $currentStock || ! $product) {
                continue;
            }

            $closingBoxes = (float) ($stockData['closing_boxes'] ?? 0);
            $closingUnits = (float) ($stockData['closing_units'] ?? 0);
            $damagedUnits = (float) ($stockData['damaged_units'] ?? 0);
            $creditUnits = (float) ($stockData['credit_units'] ?? 0);

            // Use original opening stock from editing record
            $openingStock = $stockData['original_opening_stock'] ?? $currentStock->total_units;

            if ($closingUnits > $openingStock) {
                throw new \Exception('Closing stock for product '.$product->name.' cannot be greater than opening stock.');
            }

            $openingBoxes = $product->units_per_box > 0 ?
                floor($openingStock / $product->units_per_box) : 0;

            $unitsSold = max(0, $openingStock - $closingUnits - $creditUnits);
            $sellingPrice = $product->selling_price ?? 0;
            $cashRevenue = $unitsSold * $sellingPrice;
            $creditAmount = $creditUnits * $sellingPrice;
            $lossAmount = $damagedUnits * $sellingPrice;
            $productRevenue = $cashRevenue + $creditAmount;

            $totalSoldUnits = $unitsSold + $creditUnits;
            $unitProfit = $totalSoldUnits * ($currentStock->cost_margin ?? 0);

            $totalRevenue += $productRevenue;
            $totalItemsSold += $totalSoldUnits;
            $totalProfit += $unitProfit;
            $totalDamaged += $damagedUnits;
            $totalCredit += $creditUnits;
            $totalCreditAmount += $creditAmount;
            $totalLossAmount += $lossAmount;

            if ($unitsSold > $maxUnitsSold) {
                $maxUnitsSold = $unitsSold;
                $mostSoldProductId = $productId;
            }

            // Create new stock entry
            $newStockRows[] = [
                'product_id' => $productId,
                'total_units' => $closingUnits,
                'supplier' => $currentStock->supplier ?? null,
                'total_cost' => $currentStock->total_cost ?? 0,
                'cost_price' => $currentStock->cost_price ?? 0,
                'cost_margin' => $sellingPrice - ($currentStock->cost_price ?? 0),
                'free_units' => 0,
                'notes' => 'Stock updated via inventory edit - '.$salesDate,
                'restock_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert new stock entries
        if (! empty($newStockRows)) {

            DB::table('stocks')->insert($newStockRows);
            
            // Get newly created stock IDs
            $newStockIds = DB::table('stocks')
                ->whereIn('product_id', array_column($newStockRows, 'product_id'))
                ->where('created_at', '>=', now()->subSeconds(5))
                ->orderBy('created_at', 'desc')
                ->get()
                ->keyBy('product_id');

            // Create sales rows with correct stock_id
            foreach ($this->productStocks as $productId => $stockData) {
                if (! filled($stockData['closing_units'])) {
                    continue;
                }

                $product = $this->allProducts[$productId] ?? null;
                $currentStock = $this->allStocks[$productId] ?? null;
                $newStock = $newStockIds[$productId] ?? null;

                if (!$product || !$currentStock || !$newStock) {
                    continue;
                }

                $closingBoxes = (float) ($stockData['closing_boxes'] ?? 0);
                $closingUnits = (float) ($stockData['closing_units'] ?? 0);
                $damagedUnits = (float) ($stockData['damaged_units'] ?? 0);
                $creditUnits = (float) ($stockData['credit_units'] ?? 0);
                $openingStock = $stockData['original_opening_stock'] ?? $currentStock->total_units;
                $openingBoxes = $product->units_per_box > 0 ?
                    floor($openingStock / $product->units_per_box) : 0;

                $unitsSold = max(0, $openingStock - $closingUnits - $creditUnits);
                $sellingPrice = $product->selling_price ?? 0;
                $cashRevenue = $unitsSold * $sellingPrice;
                $creditAmount = $creditUnits * $sellingPrice;
                $lossAmount = $damagedUnits * $sellingPrice;
                
                $totalSoldUnits = $unitsSold + $creditUnits;
                $unitProfit = $totalSoldUnits * ($product->currentStock->cost_margin ?? 0);

                $salesRows[] = [
                    'product_id' => $productId,
                    'stock_id' => $newStock->id,
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
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (! empty($salesRows)) {
            DB::table('daily_sales')->insert($salesRows);
        }

        // Calculate total_money correctly: (cash + momo + hubtel + food) - on_the_house
        $totalMoney = $this->cashAmount + $this->momoAmount + $this->hubtelAmount;

        // Update summary
        $updateData = [
            'total_revenue' => $totalRevenue,
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
            'on_the_house' => (float) $this->onTheHouse,
            'total_money' => $totalMoney,
        ];

        if ($mostSoldProductId) {
            $updateData['product_id'] = $mostSoldProductId;
        }

        $summary->update($updateData);

        ActivityLogs::create([
            'user_id' => Auth::id(),
            'action_type' => 'daily_sales_edit',
            'description' => 'Daily Sales record edited for '.$salesDate,
            'entity_type' => 'inventory',
            'entity_id' => $this->editingRecordId,
            'metadata' => json_encode([
                'updated_fields' => array_keys($updateData),
                'drink_sales' => $totalRevenue,
                'items_sold' => $totalItemsSold,
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
        if ($this->currentStep < 6) {
            $this->currentStep++;
        }
        Log::info('next step');
    }

    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
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

        $this->validate([
            'cashAmount' => 'required|numeric|min:0',
            'momoAmount' => 'required|numeric|min:0',
            'hubtelAmount' => 'required|numeric|min:0',
            'foodTotal' => 'required|numeric|min:0',
            'onTheHouse' => 'required|numeric|min:0',
        ]);

        Log::debug('validated');
        $recordDate = $this->salesDate ?: now()->format('Y-m-d');

        Log::debug('fetched record date: '.$recordDate);

        // Check for existing record using DB facade
        $existingRecord = DB::table('daily_sales_summaries')
            ->where('sales_date', $recordDate)
            ->exists();

        Log::debug('existing record: '.$existingRecord);

        if ($existingRecord) {
            session()->flash('error', 'A sales record already exists for '.\Carbon\Carbon::parse($this->salesDate)->format('M j, Y').'. Please edit the existing record instead.');

            // $this->closeTakeInventoryModal();
            return;
        }

        // Log::debug('checked for existing record');
        DB::transaction(function () {
            $recordDate = $this->salesDate ?: now()->format('Y-m-d');
            $totalRevenue = 0;
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

            $stocks = Stock::whereIn('product_id', $productIds)
                ->where('total_units', '>', 0)
                ->get()->keyBy('product_id');

            Log::info('Retrieved Products: '.$products);
            // Process each product's inventory

            $salesRows = [];
            $stockUpdates = [];

            //

            foreach ($this->productStocks as $productId => $stockData) {
                if (! filled($stockData['closing_units'])) {
                    continue;
                }

                // $product = Product::find($productId);
                // $stock = Stock::where('product_id', $productId)->first();
                $product = $this->allProducts[$productId] ?? null;
                // $stock = $this->allStocks[$productId] ?? null;
                $currentStock = $this->allStocks[$productId] ?? null;

                // Log::debug('Current stock for product '.$productId.': '.($currentStock ? $currentStock->total_units : 'not found'));

                if (! $currentStock || ! $product) {
                    continue;
                }

                $closingBoxes = (float) ($stockData['closing_boxes'] ?? 0);
                $closingUnits = (float) ($stockData['closing_units'] ?? 0);
                $damagedUnits = (float) ($stockData['damaged_units'] ?? 0);
                $creditUnits = (float) ($stockData['credit_units'] ?? 0);
                // $totalLosses = $damagedUnits + $creditUnits;

                // $totalClosingUnits = $this->calculateTotalUnits($productId);

                // hubtel+cash+momo

                //total_money = (total_momo + total_cash + total_hubtel) - on_the_house 

                //$difference = total_money - (food total+ total_revenue)


                // Get opening stock (current available units)
                $openingStock = $currentStock->total_units;
                $openingBoxes = $product->units_per_box > 0 ?
                    floor($openingStock / $product->units_per_box) : 0;

                // Calculate units sold and revenue
                $unitsSold = max(0, $openingStock - $closingUnits - $damagedUnits - $creditUnits);

                $sellingPrice = $product->selling_price ?? 0;
                $cashRevenue = $unitsSold * $sellingPrice;
                $creditAmount = $creditUnits * $sellingPrice;
                $lossAmount = $damagedUnits * $sellingPrice;
                $productRevenue = $cashRevenue + $creditAmount;

                // Calculate profit per unit
                // $unitProfit = $unitsSold * (($product->selling_price ?? 0) - ($stock->cost_price ?? 0));

                //profit = margin * unitsSold
                $totalSoldUnits = $unitsSold + $creditUnits;
                // $unitProfit = $totalSoldUnits * (($product->selling_price ?? 0) - ($stock->cost_price ?? 0));

                $unitProfit = $totalSoldUnits * $currentStock->cost_margin;

                Log::debug("unitProfit: ".$unitProfit);

                $totalRevenue += $productRevenue;
                $totalItemsSold += $totalSoldUnits;
                $totalProfit += $unitProfit;
                $totalDamaged += $damagedUnits;
                $totalCredit += $creditUnits;
                $totalCreditAmount += $creditAmount;
                $totalLossAmount += $lossAmount;

                // Track most sold product
                if ($unitsSold > $maxUnitsSold) {
                    $maxUnitsSold = $unitsSold;
                    $mostSoldProductId = $productId;
                }

                Log::info('calculated sales');

                // Create individual daily sales record

                $newStockRows[] = [
                    'product_id' => $productId,
                    'total_units' => $closingUnits,
                    'supplier' => $currentStock->supplier ?? null,
                    'total_cost' => $currentStock->total_cost ?? 0,
                    'cost_price' => $currentStock->cost_price ?? 0,
                    'cost_margin' => $sellingPrice - ($currentStock->cost_price ?? 0),
                    'free_units' => 0,
                    'notes' => 'Stock updated via inventory - '.$recordDate,
                    'restock_date' => null,
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

                //     $stockUpdates[] = [
                //     'id' => $stock->id,
                //     'total_units' => $closingUnits
                // ];

                // after the loop

                Log::debug('checked new sales for product: '.$productId);

            }

            // food

            Log::debug('prepared all sales rows');

            if (! empty($newStockRows)) {
                DB::table('stocks')->insert($newStockRows);
                Log::info('Created new stock entries', ['count' => count($newStockRows)]);

                // Get the newly created stock IDs
                $newStockIds = DB::table('stocks')
                    ->whereIn('product_id', array_column($newStockRows, 'product_id'))
                    ->where('created_at', '>=', now()->subSeconds(5)) // Created in last 5 seconds
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->keyBy('product_id');

                // Update sales rows with correct stock_id
                foreach ($salesRows as &$salesRow) {
                    $newStock = $newStockIds[$salesRow['product_id']] ?? null;
                    $salesRow['stock_id'] = $newStock ? $newStock->id : null;
                }
            }

            if (! empty($salesRows)) {
                DB::table('daily_sales')->insert($salesRows);
                Log::debug('Submitted new Sales', ['count' => count($salesRows)]);
            }

            if (! empty($stockUpdates)) {
                $ids = array_column($stockUpdates, 'id');
                $cases = [];
                foreach ($stockUpdates as $update) {
                    $cases[] = "WHEN {$update['id']} THEN {$update['total_units']}";
                }
                $caseSql = implode(' ', $cases);
                $idsList = implode(',', $ids);

                DB::statement("
                    UPDATE stocks 
                    SET total_units = CASE id {$caseSql} END,
                        updated_at = NOW()
                    WHERE id IN ({$idsList})
                ");
            }

            Log::debug('updated all stocks');
            // Create daily sales summary only if we have sales data
            if ($mostSoldProductId && $totalRevenue > 0) {
                DB::table('daily_sales_summaries')->insert([
                    'total_revenue' => $totalRevenue,
                    'total_money' => $this->cashAmount + $this->momoAmount + $this->hubtelAmount,
                    'items_sold' => $totalItemsSold,
                    'total_profit' => $totalProfit,
                    'sales_date' => $recordDate,
                    'total_cash' => (float) $this->cashAmount,
                    'total_momo' => (float) $this->momoAmount,
                    'total_hubtel' => (float) $this->hubtelAmount,
                    'food_total' => (float) $this->foodTotal,
                    'on_the_house' => (float) $this->onTheHouse,
                    'total_damaged' => $totalDamaged,
                    'total_credit_units' => $totalCredit,
                    'total_credit_amount' => $totalCreditAmount,
                    'total_loss_amount' => $totalLossAmount,
                    'product_id' => $mostSoldProductId,
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
                        'total_revenue' => $totalRevenue,
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
                'total_revenue',
                'food_total',
                'on_the_house',
                'total_money',
                'items_sold as total_products',
                'id as first_id'
            )
            ->orderBy('sales_date', 'desc')
            ->limit(100) // Add limit for performance
            ->get();
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
            'total_revenue' => $summary->total_revenue,
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

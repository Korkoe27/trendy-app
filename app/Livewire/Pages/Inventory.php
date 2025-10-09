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

    public $isEditing = false;

    public $editingRecordId = null;

    public $editingOriginalRecord = null;

    public $selectedDate;

    // Form data
    public $cashAmount = '';

    public $momoAmount = '';

    public $foodTotal = '';

    public $hubtelAmount = '';

    public $productStocks = [];

    private $stocksCache = [];

    public function mount()
    {
        $this->selectedDate = now()->format('Y-m-d');
    }

    protected $rules = [
        'cashAmount' => 'required|numeric|min:0',
        'momoAmount' => 'required|numeric|min:0',
        'hubtelAmount' => 'required|numeric|min:0',
        'foodTotal' => 'required|numeric|min:0',
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

    public function updatedHubtelAmount($value)
    {
        $this->hubtelAmount = $value;
    }

    public function updateFoodTotal($value)
    {
        $this->foodTotal = $value;
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

        if (! $this->editingOriginalRecord) {
            session()->flash('error', 'Record not found');

            return;
        }

        // Pre-fill form with existing data
        $this->cashAmount = $this->editingOriginalRecord['total_cash'];
        $this->momoAmount = $this->editingOriginalRecord['total_momo'];
        $this->hubtelAmount = $this->editingOriginalRecord['total_hubtel'];
        $this->foodTotal = $this->editingOriginalRecord['food_total'];

        // Load existing product stocks
        $this->loadEditingProductStocks();

        $this->showTakeInventoryModal = true;
        // $this->currentStep = auth()->user()->role === 'admin' ? 1 : 4; // Start at money step for admin, stock step for others
        $this->currentStep = Auth::user() ? 1 : 4;
    }

    private function loadEditingProductStocks()
    {
        // $products = Product::with(['stocks', 'category'])->where('is_active', true)->get();

        $products = Stock::with('product')
            ->where('total_units', '>', 0)
            ->get();

        foreach ($products as $product) {
            // Find existing data for this product
            $existingProduct = collect($this->editingOriginalRecord['products'])
                ->firstWhere('product_name', $product->name);

            if ($existingProduct) {
                $this->productStocks[$product->id] = [
                    'closing_boxes' => $existingProduct['closing_boxes'],
                    'closing_units' => $existingProduct['closing_stock'] - ($existingProduct['closing_boxes'] * $product->units_per_box),
                    'damaged_units' => $existingProduct['damaged_units'],
                    'credit_units' => $existingProduct['credit_units'],
                    'product' => $product,
                ];
            } else {
                $this->productStocks[$product->id] = [
                    'closing_boxes' => '',
                    'closing_units' => '',
                    'damaged_units' => '',
                    'credit_units' => '',
                    'product' => $product,
                ];
            }
        }
    }

    public function updateInventory()
    {
        if (! $this->isEditing || ! $this->editingRecordId) {
            session()->flash('error', 'Invalid edit operation');

            return;
        }

        // Validate based on user role
        if (Auth::user()) {
            // if (auth()->user()->role === 'admin') {
            $this->validate([
                'cashAmount' => 'required|numeric|min:0',
                'momoAmount' => 'required|numeric|min:0',
                'hubtelAmount' => 'required|numeric|min:0',
                'foodTotal' => 'required|numeric|min:0',
            ]);
        }

        DB::transaction(function () {
            // Get the original summary record
            $summary = DailySalesSummary::find($this->editingRecordId);

            if (! $summary) {
                throw new \Exception('Summary record not found');
            }

            // Delete existing daily sales for this date
            // DailySales::whereDate('created_at', $summary->created_at->format('Y-m-d'))->delete();

            $targetDay = $summary->created_at->startOfDay();
            $nextDay = $summary->created_at->endOfDay();

            DailySales::whereBetween('created_at', [$targetDay, $nextDay])->delete();

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
            $stockUpdates = [];
            // Process each product's updated inventory (same logic as submitInventory)
            foreach ($this->productStocks as $productId => $stockData) {

                if (! filled($stockData['closing_boxes']) && ! filled($stockData['closing_units'])) {
                    continue;
                }

                $product = Product::find($productId);
                $stock = Stock::where('product_id', $productId)->first();

                if (! $stock || ! $product) {
                    continue;
                }

                $closingBoxes = (float) ($stockData['closing_boxes'] ?? 0);
                $closingUnits = (float) ($stockData['closing_units'] ?? 0);
                $damagedUnits = (float) ($stockData['damaged_units'] ?? 0);
                $creditUnits = (float) ($stockData['credit_units'] ?? 0);

                $totalClosingUnits = $this->calculateTotalUnits($productId);

                // Use original opening stock for consistency
                $originalProduct = collect($this->editingOriginalRecord['products'])
                    ->firstWhere('product_name', $product->name);
                $openingStock = $originalProduct ? $originalProduct['opening_stock'] : $stock->total_units;
                $openingBoxes = $product->units_per_box > 0 ?
                    floor($openingStock / $product->units_per_box) : 0;

                $unitsSold = max(0, $openingStock - $totalClosingUnits - $damagedUnits - $creditUnits);
                $sellingPrice = $product->selling_price ?? 0;
                $cashRevenue = $unitsSold * $sellingPrice;
                $creditAmount = $creditUnits * $sellingPrice;
                $lossAmount = $damagedUnits * $sellingPrice;
                $productRevenue = $cashRevenue + $creditAmount;

                $totalSoldUnits = $unitsSold + $creditUnits;
                $unitProfit = $totalSoldUnits * (($product->selling_price ?? 0) - ($stock->cost_price ?? 0));

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

                // Create updated daily sales record
                // DailySales::create([
                //     'product_id' => $productId,
                //     'stock_id' => $stock->id,
                //     'opening_stock' => $openingStock,
                //     'closing_stock' => $totalClosingUnits,
                //     'opening_boxes' => $openingBoxes,
                //     'closing_boxes' => $closingBoxes,
                //     'damaged_units' => $damagedUnits,
                //     'credit_units' => $creditUnits,
                //     'credit_amount' => $creditAmount,
                //     'loss_amount' => $lossAmount,
                //     'total_amount' => $cashRevenue,
                //     'unit_profit' => $unitProfit,
                //     'created_at' => $summary->created_at, // Keep original date
                //     'updated_at' => now(),
                // ]);

                $salesRows[] = [
                    'product_id' => $productId,
                    'stock_id' => $stock->id,
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

                $stockUpdates[$stock->id] = $closingUnits;

                // // Update stock with new closing values
                // $stock->update([
                //     'total_units' => $totalClosingUnits,
                // ]);
            }

            if (! empty($salesRows)) {
                DB::table('daily_sales')->insert($salesRows);
                Log::info('Submitted new Sales', ['count' => count($salesRows)]);
            }

            if (! empty($stockUpdates)) {
                foreach ($stockUpdates as $stockId => $totalUnits) {
                    DB::table('stocks')
                        ->where('id', $stockId)
                        ->update([
                            'total_units' => $totalUnits,
                            'updated_at' => now(),
                        ]);
                }
            }
            // Update the summary record
            $updateData = [
                'total_revenue' => $totalRevenue,
                'items_sold' => $totalItemsSold,
                'total_profit' => $totalProfit,
                'total_damaged' => $totalDamaged,
                'total_credit_units' => $totalCredit,
                'total_credit_amount' => $totalCreditAmount,
                'total_loss_amount' => $totalLossAmount,
            ];

            // Only update money fields if user is admin
            if (Auth::user()) {
                // if (auth()->user()->role === 'admin') {
                $updateData['total_cash'] = (float) $this->cashAmount;
                $updateData['total_momo'] = (float) $this->momoAmount;
                $updateData['total_hubtel'] = (float) $this->hubtelAmount;
                $updateData['total_money'] = $this->cashAmount + $this->momoAmount + $this->hubtelAmount;
            }

            if ($mostSoldProductId) {
                $updateData['product_id'] = $mostSoldProductId;
            }

            $summary->update($updateData);

            // Log the edit activity
            ActivityLogs::create([
                'user_id' => Auth::id(),
                'action_type' => 'daily_sales_edit',
                'description' => 'Daily Sales record edited for '.$summary->created_at->format('Y-m-d'),
                'entity_type' => 'inventory',
                'entity_id' => $this->editingRecordId,
                'metadata' => json_encode([
                    // 'user_role' => auth()->user()->role,

                    'updated_fields' => array_keys($updateData),
                    'total_revenue' => $totalRevenue,
                    'items_sold' => $totalItemsSold,
                ]),
            ]);
        });

        $this->closeTakeInventoryModal();
        session()->flash('success', 'Sales record updated successfully!');
    }

    public function closeTakeInventoryModal()
    {
        $this->showTakeInventoryModal = false;
        $this->isEditing = false;
        $this->editingRecordId = null;
        $this->editingOriginalRecord = null;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->currentStep = 1;
        // $this->cashAmount = '';
        // $this->momoAmount = '';
        // $this->hubtelAmount = '';
        $this->productStocks = [];
    }

    public function resetAllFormData()
    {
        $this->currentStep = 1;
        $this->cashAmount = '';
        $this->momoAmount = '';
        $this->hubtelAmount = '';
        $this->productStocks = [];
    }

    public function loadProductStocks()
    {
        // $products = Product::with(['stocks', 'category'])
        //     ->where('is_active', true)
        //     ->whereHas('stocks', function ($query) {
        //         $query->where('total_units', '>', 0);
        //     })
        //     ->get();

        $products = Stock::with('product')
            ->where('total_units', '>', 0)
            ->get();

        Log::info('Products with available stock loaded for inventory', ['count' => $products->count()]);

        foreach ($products as $product) {
            $this->productStocks[$product->product->id] = [
                'closing_boxes' => '',
                'closing_units' => '',
                'damaged_units' => '',
                'credit_units' => '',
                'product' => $product,
            ];
        }
    }

    public function nextStep()
    {
        if ($this->currentStep < 5) {
            $this->currentStep++;
        }
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

        Log::debug("Calculating total units for product ID: $productId", [
            'stock' => $stock,
        ]);

        // If stock is not set or missing, return 0
        if (! $stock) {
            return 0;
        }

        $product = $stock['product'];
        $boxes = (int) ($stock['closing_boxes'] ?? 0);
        $units = (int) ($stock['closing_units'] ?? 0);
        $unitsPerBox = $product->units_per_box ?? 1;

        Log::debug("Product ID: $productId, Boxes: $boxes, Units: $units, Units per Box: $unitsPerBox");

        $totalClosingUnits = ($boxes * $unitsPerBox) + $units;

        return $totalClosingUnits;
    }

    public function calculateExpectedRevenue($productId)
    {
        $stock = $this->productStocks[$productId] ?? null;
        if (! $stock) {
            return 0;
        }

        Log::info('Stock details', $stock);

        $product = $stock['product'];
        Log::debug('product Details'.$product);
        // $currentStock = Stock::select('total_units')->where('product_id', $productId)->first();

        $currentStock = $product->total_units;

        if (! $currentStock) {
            return 0;
        }

        if (! isset($this->stocksCache[$productId])) {
            $this->stocksCache[$productId] = Stock::where('product_id', $productId)->first();
        }
        $currentStock = $this->stocksCache[$productId];

        Log::debug('Current Stock: '.$currentStock);

        // Calculate units sold
        // $openingStock = $currentStock->total_units;

        $openingStock = optional($this->stocksCache[$productId])->total_units ?? 0;

        // Log::info("Calculating expected revenue for product ID: $productId", [
        //     'opening_stock' => $openingStock,
        //     'current_stock' => $currentStock,
        // ]);

        $closingStock = $this->calculateTotalUnits($productId);
        $damagedUnits = (float) ($stock['damaged_units'] ?? 0);
        $creditUnits = (float) ($stock['credit_units'] ?? 0);
        $unitsSold = max(0, $openingStock - $closingStock - $damagedUnits - $creditUnits);

        Log::debug("Units sold calculation for product ID: $productId", [
            'closing_stock' => $closingStock,
            'damaged_units' => $damagedUnits,
            'credit_units' => $creditUnits,
            'units_sold' => $unitsSold,
        ]);

        // Calculate revenue for this product
        $sellingPrice = $product->selling_price ?? 0;
        $productRevenue = $unitsSold * $sellingPrice;

        $creditRevenue = $creditUnits * $sellingPrice;

        return $productRevenue + $creditRevenue;
    }

    public function submitInventory()
    {

        $this->validate([
            'cashAmount' => 'required|numeric|min:0',
            'momoAmount' => 'required|numeric|min:0',
            'hubtelAmount' => 'required|numeric|min:0',
            'foodTotal' => 'required|numeric|min:0',
        ]);

        Log::info('Submitting inventory', [
            'cash_amount' => $this->cashAmount,
            'momo_amount' => $this->momoAmount,
            'hubtel_amount' => $this->hubtelAmount,
            'product_stocks' => $this->productStocks,
        ]);

        DB::transaction(function () {
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

            foreach ($this->productStocks as $productId => $stockData) {
                if (! filled($stockData['closing_boxes']) && ! filled($stockData['closing_units'])) {
                    continue;
                }

                // $product = Product::find($productId);
                // $stock = Stock::where('product_id', $productId)->first();
                $product = $products->get($productId);
                $stock = $stocks->get($productId);

                if (! $stock || ! $product) {
                    continue;
                }

                $closingBoxes = (float) ($stockData['closing_boxes'] ?? 0);
                $closingUnits = (float) ($stockData['closing_units'] ?? 0);
                $damagedUnits = (float) ($stockData['damaged_units'] ?? 0);
                $creditUnits = (float) ($stockData['credit_units'] ?? 0);
                // $totalLosses = $damagedUnits + $creditUnits;

                // $totalClosingUnits = $this->calculateTotalUnits($productId);

                // Get opening stock (current available units)
                $openingStock = $stock->total_units;
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
                $totalSoldUnits = $unitsSold + $creditUnits;
                $unitProfit = $totalSoldUnits * (($product->selling_price ?? 0) - ($stock->cost_price ?? 0));

                // Track totals for summary
                // $totalRevenue += $productRevenue;
                // $totalItemsSold += $unitsSold;
                // $totalProfit += $unitProfit;
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
                // DailySales::create([
                //     'product_id' => $productId,
                //     'stock_id' => $stock->id,
                //     'opening_stock' => $openingStock,
                //     'closing_stock' => $closingUnits,
                //     'opening_boxes' => $openingBoxes,
                //     'closing_boxes' => $closingBoxes,
                //     'damaged_units' => $damagedUnits,
                //     'credit_units' => $creditUnits,
                //     'credit_amount' => $creditAmount,
                //     'loss_amount' => $lossAmount,
                //     'total_amount' => $cashRevenue,
                //     'unit_profit' => $unitProfit,
                // ]);

                // inside foreach (instead of DailySales::create([...]))
                $salesRows[] = [
                    'product_id' => $productId,
                    'stock_id' => $stock->id,
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

                $stockUpdates[$stock->id] = $closingUnits;

                // after the loop
                // if (! empty($salesRows)) {
                //     DB::table('daily_sales')->insert($salesRows);
                // }

                Log::debug('checked new sales for product: '.$productId);
                // Update stock with new closing values
                // $stock->update([
                //     'total_units' => $closingUnits,
                // ]);
            }

            if (! empty($salesRows)) {
                DB::table('daily_sales')->insert($salesRows);
                Log::debug('Submitted new Sales', ['count' => count($salesRows)]);
            }

            if (! empty($stockUpdates)) {
                foreach ($stockUpdates as $stockId => $totalUnits) {
                    DB::table('stocks')
                        ->where('id', $stockId)
                        ->update([
                            'total_units' => $totalUnits,
                            'updated_at' => now(),
                        ]);
                }
            }
            // Create daily sales summary only if we have sales data
            if ($mostSoldProductId && $totalRevenue > 0) {
                DailySalesSummary::create([
                    'total_revenue' => $totalRevenue,
                    'total_money' => $this->cashAmount + $this->momoAmount + $this->hubtelAmount,
                    'items_sold' => $totalItemsSold,
                    'total_profit' => $totalProfit,
                    'total_cash' => (float) $this->cashAmount,
                    'total_momo' => (float) $this->momoAmount,
                    'total_hubtel' => (float) $this->hubtelAmount,
                    'food_total' => (float) $this->foodTotal,
                    'total_damaged' => $totalDamaged,
                    'total_credit_units' => $totalCredit,
                    'total_credit_amount' => $totalCreditAmount,
                    'total_loss_amount' => $totalLossAmount,
                    'product_id' => $mostSoldProductId, // Most sold product
                ]);

                ActivityLogs::create([
                    'user_id' => Auth::id(),
                    'action_type' => 'daily_sales',
                    'description' => 'Daily Sales recorded for '.now()->format('Y-m-d'),
                    'entity_type' => 'inventory',
                    'entity_id' => 'inventory',
                    'metadata' => json_encode([
                        'total_revenue' => $totalRevenue,
                        'items_sold' => $totalItemsSold,
                        'total_profit' => $totalProfit,
                        'total_cash' => (float) $this->cashAmount,
                        'total_momo' => (float) $this->momoAmount,
                        'total_hubtel' => (float) $this->hubtelAmount,
                        'total_money' => $this->cashAmount + $this->momoAmount + $this->hubtelAmount,
                        'total_damaged' => $totalDamaged,
                        'total_credit_units' => $totalCredit,
                        'total_credit_amount' => $totalCreditAmount,
                        'most_sold_product_id' => $mostSoldProductId,
                    ]),
                ]);
            }
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

        Log::info('Fetching daily sales records');

        return DailySalesSummary::select(
            DB::raw('DATE(created_at) as date'),
            'total_cash',
            'total_momo',
            'total_hubtel',
            'total_revenue',
            'food_total',
            'total_money',
            'items_sold as total_products',
            'id as first_id'
        )
            ->orderBy('created_at', 'desc')
            ->get();

        // Log::info('Fetched '. $dailySalesRecords->count() .' daily sales records');
    }

    public function getDailySalesRecord($recordId)
    {
        // Get the summary record
        $summary = DailySalesSummary::find($recordId);
        Log::debug("Fetching daily sales record for ID: $recordId");
        if (! $summary) {
            return null;
        }

        // Get all individual sales for the same date
        // $dailySales = DailySales::with(['stock'])
        //     ->whereDate('created_at', $summary->created_at->format('Y-m-d'))
        //     ->get();
        $dailySales = DailySales::with(['stock', 'product.category'])
        ->whereDate('created_at', $summary->created_at->format('Y-m-d'))
        ->get();

        Log::debug('Found '.$dailySales->count()." daily sales for summary ID: $recordId");

        return [
            'id' => $recordId,
            'date' => $summary->created_at->format('Y-m-d'),
            'total_cash' => $summary->total_cash,
            'total_momo' => $summary->total_momo,
            'total_hubtel' => $summary->total_hubtel,
            'total_revenue' => $summary->total_revenue,
            'total_money' => $summary->total_money,
            'food_total' => $summary->food_total,
            'total_profit' => $summary->total_profit,
            'total_loss_amount' => $summary->total_loss_amount,
            'total_credit_amount' => $summary->total_credit_amount,
            'total_credit_units' => $summary->total_credit_units,
            'total_damaged' => $summary->total_damaged,
            'products' => $dailySales->map(function ($sale) {
                $unitsSold = $sale->opening_stock - $sale->closing_stock - ($sale->damaged_units ?? 0) - ($sale->credit_units ?? 0);
                $boxesSold = $sale->opening_boxes - $sale->closing_boxes;
                // $product = Product::with('category')->find($sale->product_id);

                $productName = $sale->product->name ?? 'Error';
                $productCategory = $sale->product->category->name ?? 'Error';

                return [
                    // 'product_name' => $product->name ?? 'N/A',
                    'product_name' => $productName,
                    'category' => $productCategory,
                    'opening_stock' => $sale->opening_stock,
                    'closing_stock' => $sale->closing_stock,
                    'opening_boxes' => $sale->opening_boxes,
                    'closing_boxes' => $sale->closing_boxes,
                    'damaged_units' => $sale->damaged_units ?? 0,
                    'credit_units' => $sale->credit_units ?? 0,
                    'credit_amount' => $sale->credit_amount ?? 0,
                    'loss_amount' => $sale->loss_amount ?? 0,
                    'units_sold' => $unitsSold,
                    'boxes_sold' => $boxesSold,
                    'revenue' => $sale->total_amount + ($sale->credit_amount ?? 0), // Include credit in total revenue display
                ];
            }),
        ];
    }

    public function render()
    {

        // dd('rendering');
        $dailySalesRecords = $this->getDailySalesRecords();
        Log::info('Rendering inventory');

        $products = Product::with(['stocks'])
            ->where('is_active', true)
            ->whereHas('stocks', function ($query) {
                $query->where('total_units', '>', 0);
            })
            ->get();

        Log::info('Fetched products');

        return view('livewire.pages.inventory', [
            'dailySalesRecords' => $dailySalesRecords,
            'products' => $products,
        ]);
    }
}

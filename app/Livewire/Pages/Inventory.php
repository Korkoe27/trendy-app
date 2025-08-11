<?php

namespace App\Livewire\Pages;

use App\Models\{Product,Stock,DailySales,DailySalesSummary};
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Inventory extends Component
{
    public $showTakeInventoryModal = false;
    public $showDetailsModal = false;
    public $selectedRecord = null;
    public $currentStep = 1;
    public $selectedDate;
    
    // Form data
    public $cashAmount = '';
    public $momoAmount = '';
    public $hubtelAmount = '';
    public $productStocks = [];
    
    public function mount()
    {
        $this->selectedDate = now()->format('Y-m-d');
    }
    
    public function openTakeInventoryModal()
    {
        $this->showTakeInventoryModal = true;
        $this->resetForm();
        $this->loadProductStocks();
    }
    
    public function closeTakeInventoryModal()
    {
        $this->showTakeInventoryModal = false;
        $this->resetForm();
    }
    
    public function resetForm()
    {
        $this->currentStep = 1;
        $this->cashAmount = '';
        $this->momoAmount = '';
        $this->hubtelAmount = '';
        $this->productStocks = [];
    }
    
    public function loadProductStocks()
    {
        $products = Product::with(['stocks', 'category'])->where('is_active', true)->get();
        
        Log::info('Products loaded for inventory', ['count' => $products->count()]);

        foreach ($products as $product) {
            $this->productStocks[$product->id] = [
                'closing_boxes' => '',
                'closing_units' => '',
                'product' => $product
            ];
        }
    }
    
    public function nextStep()
    {
        if ($this->currentStep < 4) {
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

        Log::info("Calculating total units for product ID: $productId", [
            'stock' => $stock
        ]);
        
        // If stock is not set or missing, return 0
        if (!$stock) return 0;
        
        $product = $stock['product'];
        $boxes = (int) ($stock['closing_boxes'] ?? 0);
        $units = (int) ($stock['closing_units'] ?? 0);
        $unitsPerBox = $product->units_per_box ?? 1;

        Log::info("Product ID: $productId, Boxes: $boxes, Units: $units, Units per Box: $unitsPerBox");
        
        $totalClosingUnits = ($boxes * $unitsPerBox) + $units;
        return $totalClosingUnits;
    }

    public function calculateExpectedRevenue($productId)
    {
        $stock = $this->productStocks[$productId] ?? null;
        if (!$stock) return 0;
        
        $product = $stock['product'];
        $currentStock = Stock::where('product_id', $productId)->first();
        
        if (!$currentStock) return 0;
        
        // Calculate units sold
        $openingStock = $currentStock->total_units;

        Log::info("Calculating expected revenue for product ID: $productId", [
            'opening_stock' => $openingStock,
            'current_stock' => $currentStock
        ]);
        $closingStock = $this->calculateTotalUnits($productId);
        $unitsSold = max(0, $openingStock - $closingStock); // Ensure non-negative
        
        // Calculate revenue for this product
        $sellingPrice = $product->selling_price ?? 0;
        $productRevenue = $unitsSold * $sellingPrice;
        
        Log::info("Revenue calculation for product ID: $productId", [
            'opening_stock' => $openingStock,
            'closing_stock' => $closingStock,
            'units_sold' => $unitsSold,
            'selling_price' => $sellingPrice,
            'product_revenue' => $productRevenue
        ]);
        
        return $productRevenue;
    }
    
    public function submitInventory()
    {
        $this->validate([
            'cashAmount' => 'required|numeric|min:0',
            'momoAmount' => 'required|numeric|min:0',
            'hubtelAmount' => 'required|numeric|min:0',
        ]);
        
        DB::transaction(function () {
            $totalRevenue = 0;
            $totalItemsSold = 0;
            $mostSoldProductId = null;
            $maxUnitsSold = 0;
            
            // Process each product's inventory
            foreach ($this->productStocks as $productId => $stockData) {
                if (empty($stockData['closing_boxes']) && empty($stockData['closing_units'])) {
                    continue;
                }
                
                $product = Product::find($productId);
                $stock = Stock::where('product_id', $productId)->first();
                
                if (!$stock) continue;
                
                $closingBoxes = (int) ($stockData['closing_boxes'] ?? 0);
                $closingUnits = (int) ($stockData['closing_units'] ?? 0);
                $totalClosingUnits = $this->calculateTotalUnits($productId);
                
                // Get opening stock (current available units)
                $openingStock = $stock->total_units;
                $openingBoxes = $openingStock / $stock->product->units_per_box ?? 0;
                
                // Calculate units sold and revenue
                $unitsSold = max(0, $openingStock - $totalClosingUnits);
                $productRevenue = $unitsSold * ($product->selling_price ?? 0);
                
                // Track totals for summary
                $totalRevenue += $productRevenue;
                $totalItemsSold += $unitsSold;
                
                // Track most sold product
                if ($unitsSold > $maxUnitsSold) {
                    $maxUnitsSold = $unitsSold;
                    $mostSoldProductId = $productId;
                }
                
                // Create individual daily sales record
                DailySales::create([
                    'product_id' => $productId,
                    'stock_id' => $stock->id,
                    'opening_stock' => $openingStock,
                    'closing_stock' => $totalClosingUnits,
                    'opening_boxes' => $openingBoxes,
                    'closing_boxes' => $closingBoxes,
                    'total_amount' => $productRevenue,
                ]);
                
                // Update stock with new closing values
                $stock->update([
                    'total_units' => $totalClosingUnits,
                    // 'available_boxes' => $closingBoxes
                ]);
            }

            
            
            // Create daily sales summary
            if ($mostSoldProductId) {
                DailySalesSummary::create([
                    'total_revenue' => $totalRevenue,
                    'items_sold' => $totalItemsSold,
                    'total_cash' => (float) $this->cashAmount,
                    'total_momo' => (float) $this->momoAmount,
                    'total_hubtel' => (float) $this->hubtelAmount,
                    'product_id' => $mostSoldProductId, // Most sold product
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
    }
    
    public function getDailySalesRecords()
    {
        return DB::table('daily_sales_summaries')
            ->select(
                DB::raw('DATE(created_at) as date'),
                'total_cash',
                'total_momo',
                'total_hubtel',
                'total_revenue',
                'items_sold as total_products',
                'id as first_id'
            )
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    public function getDailySalesRecord($recordId)
    {
        // Get the summary record
        $summary = DailySalesSummary::find($recordId);
        
        if (!$summary) {
            return null;
        }
        
        // Get all individual sales for the same date
        $dailySales = DailySales::with(['products.category', 'stock'])
            ->whereDate('created_at', $summary->created_at->format('Y-m-d'))
            ->get();
        
        Log::info($dailySales);
        return [
            'id' => $recordId,
            'date' => $summary->created_at->format('Y-m-d'),
            'total_cash' => $summary->total_cash,
            'total_momo' => $summary->total_momo,
            'total_hubtel' => $summary->total_hubtel,
            'total_revenue' => $summary->total_revenue,
            'products' => $dailySales->map(function($sale) {
                $unitsSold = $sale->opening_stock - $sale->closing_stock;
                $boxesSold = $sale->opening_boxes - $sale->closing_boxes;
                $product = Product::find($sale->product_id);
                
                return [
                    'product_name' => $product->name ?? 'N/A',
                    'category' => $product->category->name ?? 'N/A',
                    'opening_stock' => $sale->opening_stock,
                    'closing_stock' => $sale->closing_stock,
                    'opening_boxes' => $sale->opening_boxes,
                    'closing_boxes' => $sale->closing_boxes,
                    'units_sold' => $unitsSold,
                    'boxes_sold' => $boxesSold,
                    'revenue' => $sale->total_amount
                ];
            })
        ];
    }
    
    public function render()
    {
        $dailySalesRecords = $this->getDailySalesRecords();
        $products = Product::with(['stocks', 'category'])->where('is_active', true)->get();
        
        return view('livewire.pages.inventory', [
            'dailySalesRecords' => $dailySalesRecords,
            'products' => $products
        ]);
    }
}
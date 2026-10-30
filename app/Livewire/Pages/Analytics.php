<?php

namespace App\Livewire\Pages;

use App\Models\{DailySales, DailySalesSummary, Product, Stock, Categories};
use App\Exports\AnalyticsMultiSheetExport;
use App\Services\DebtLedger;
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class Analytics extends Component
{

    // Add these properties at the top of your Analytics class
public $isLoading = true;
public $metricsLoaded = false;
public $chartsLoaded = false;
    public $timeframe = '30d';
    public $startDate;
    public $endDate;
    public $customDateRange = false;
    public $selectedMonth = ''; // For specific month selection (format: Y-m)

    // Revenue Metrics
    public $drinksTotal = 0;
    public $totalCashRevenue = 0;
    public $totalMomoRevenue = 0;
    public $totalHubtelRevenue = 0;
    public $averageDailyRevenue = 0;
    public $revenueGrowth = 0;

    // Sales Metrics
    public $totalItemsSold = 0;
    public $itemsSoldGrowth = 0;

    // Expense Metrics
    public $totalExpenses = 0;
    public $expensesGrowth = 0;

    // Profit Metrics
    public $totalProfit = 0;
    public $averageProfitMargin = 0;
    public $profitGrowth = 0;

    public $snooker = 0;
    public $grossProfitMargin = 0;

    // Loss Metrics - IMPROVED
    public $accumulatedLosses = 0; // NEW: Collection discrepancies, before debt payments
    public $debtRecovered = 0;     // part of the shortfall since paid back through Debt Payments
    public $netLosses = 0;         // accumulatedLosses + debtRecovered
    public $totalOnTheHouse = 0; // NEW: Sum of on_the_house

    // Inventory Metrics - IMPROVED
    public $totalInventoryValue = 0;
    public $lowStockProducts = 0;

    public $averageDailySnookerSales = 0;
    public $outOfStockProducts = 0;
    public $inventoryTurnoverRate = 0;
    public $averageInventoryValue = 0; // NEW: For better turnover calculation

    // Food Sales Metrics - NEW
    public $totalFoodSales = 0;
    public $averageDailyFoodSales = 0;
    public $foodSalesGrowth = 0;

    // Product Performance
    public $topSellingProducts = [];
    public $leastSellingProducts = [];
    public $mostProfitableProducts = [];
    public $leastPerformingProducts  = [];


    public $totalSnooker = 0;

    // Category Performance
    public $categoryPerformance = [];
    public $topCategory = null;

    // Daily breakdown for charts
    public $dailySalesData = [];
    public $dailyProfitData = [];
    public $dailyFoodSalesData = []; // NEW
    public $dailyLossesData = []; // NEW
    public $paymentMethodDistribution = [];

    public function mount()
    {
        $this->setDateRange();
        $this->loadMetrics();
    }

    // Add these methods to your Analytics class

// public function loadMetrics()
// {
//     $this->isLoading = true;

//     $this->calculateRevenueMetrics();
//     $this->calculateSnookerMetrics();
//     $this->calculateSalesMetrics();
//     $this->calculateExpenseMetrics();
//     $this->calculateProfitMetrics();
//     $this->calculateLossMetrics();
//     $this->calculateFoodSalesMetrics();
//     $this->calculateInventoryMetrics();
//     $this->calculateGrowthRates();

//     $this->metricsLoaded = true;
//     $this->isLoading = false;
// }

// At the top of loadMetrics() - ensure fresh date range
public function loadMetrics()
{
    $this->isLoading = true;

    // Force refresh dates in case of any Livewire binding quirks
    if ($this->timeframe === 'custom' || $this->timeframe === 'specific_month') {
        // dates already set by user
    } else {
        $this->setDateRange();
    }

    $this->calculateRevenueMetrics();
    $this->calculateSnookerMetrics();
    $this->calculateSalesMetrics();
    $this->calculateExpenseMetrics();
    $this->calculateProfitMetrics();
    $this->calculateLossMetrics();
    $this->calculateFoodSalesMetrics();
    $this->calculateInventoryMetrics();
    $this->calculateGrowthRates();

    $this->metricsLoaded = true;
    $this->isLoading = false;
}

public function loadCharts()
{
    $this->calculateProductPerformance();
    $this->calculateCategoryPerformance();
    $this->calculateDailyData();
    $this->calculatePaymentMethodDistribution();
    
    $this->chartsLoaded = true;
    $this->dispatch('chartsLoaded');
}

// Modify updatedTimeframe


// Add reset method
private function resetLoadedData()
{
    $this->metricsLoaded = false;
    $this->chartsLoaded = false;
}

    // public function updatedTimeframe()
    // {
    //     if ($this->timeframe !== 'custom' && $this->timeframe !== 'specific_month') {
    //         $this->customDateRange = false;
    //         $this->selectedMonth = '';
    //         $this->setDateRange();
    //         $this->calculateAllMetrics();
    //     } elseif ($this->timeframe === 'custom') {
    //         $this->customDateRange = true;
    //         $this->selectedMonth = '';
    //     } elseif ($this->timeframe === 'specific_month') {
    //         $this->customDateRange = false;
    //         // Don't calculate yet, wait for month selection
    //     }
    // }


    public function updatedTimeframe()
{
    if ($this->timeframe !== 'custom' && $this->timeframe !== 'specific_month') {
        $this->customDateRange = false;
        $this->selectedMonth = '';
        $this->setDateRange();
        $this->resetLoadedData();
        $this->loadMetrics();
    } elseif ($this->timeframe === 'custom') {
        $this->customDateRange = true;
        $this->selectedMonth = '';
    }
}
public function updatedSelectedMonth()
{
    if ($this->selectedMonth) {
        $this->setDateRangeFromMonth();
        $this->resetLoadedData();
        $this->loadMetrics(); // Use loadMetrics instead
    }
}

    public function updatedStartDate()
    {
        if ($this->customDateRange && $this->startDate && $this->endDate) {
            $this->resetLoadedData();
            $this->loadMetrics();
        }
    }

    public function updatedEndDate()
    {
        if ($this->customDateRange && $this->startDate && $this->endDate) {
            $this->resetLoadedData();
            $this->loadMetrics();
        }
    }

    private function setDateRange()
    {
        $now = Carbon::now();

        switch ($this->timeframe) {
            case '7d':
                $this->startDate = $now->copy()->subDays(6)->format('Y-m-d');
                $this->endDate = $now->format('Y-m-d');
                break;
            case '30d':
                $this->startDate = $now->copy()->subDays(29)->format('Y-m-d');
                $this->endDate = $now->format('Y-m-d');
                break;
            case '90d':
                $this->startDate = $now->copy()->subDays(89)->format('Y-m-d');
                $this->endDate = $now->format('Y-m-d');
                break;
            case 'month':
                $this->startDate = $now->copy()->startOfMonth()->format('Y-m-d');
                $this->endDate = $now->format('Y-m-d');
                break;
            case 'year':
                $this->startDate = $now->copy()->startOfYear()->format('Y-m-d');
                $this->endDate = $now->format('Y-m-d');
                break;
        }
    }

    private function setDateRangeFromMonth()
    {
        if (!$this->selectedMonth) {
            return;
        }

        try {
            $date = Carbon::createFromFormat('Y-m', $this->selectedMonth);
            $this->startDate = $date->copy()->startOfMonth()->format('Y-m-d');
            $this->endDate = $date->copy()->endOfMonth()->format('Y-m-d');
        } catch (\Exception $e) {
            Log::error('Invalid month format', ['month' => $this->selectedMonth]);
        }
    }

    public function calculateAllMetrics()
    {
        try {
            $this->calculateRevenueMetrics();
            $this->calculateSalesMetrics();
            $this->calculateExpenseMetrics();
            $this->calculateProfitMetrics();
            $this->calculateLossMetrics();
            $this->calculateFoodSalesMetrics(); // NEW
            $this->calculateSnookerMetrics();
            $this->calculateInventoryMetrics();
            $this->calculateProductPerformance();
            $this->calculateCategoryPerformance();
            $this->calculateDailyData();
            $this->calculatePaymentMethodDistribution();
            $this->calculateGrowthRates();

            Log::info('Analytics metrics calculated successfully', [
                'timeframe' => $this->timeframe,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
            ]);
        } catch (\Exception $e) {
            Log::error('Error calculating analytics metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function calculateRevenueMetrics()
{
    $summaries = $this->getCurrentPeriodSummaries();

    // Use summaries as primary source for consistency
    $this->totalCashRevenue   = $summaries->sum('total_cash');
    $this->totalMomoRevenue   = $summaries->sum('total_momo');
    $this->totalHubtelRevenue = $summaries->sum('total_hubtel');

    $this->drinksTotal = $summaries->sum('drinks_total');

    $daysInPeriod = max(1, Carbon::parse($this->startDate)->diffInDays($this->endDate) + 1);
    $this->averageDailyRevenue = $this->drinksTotal / $daysInPeriod;

    Log::info('Analytics: Revenue calculated (using summaries)', [
        'drinks_total' => $this->drinksTotal,
        'cash' => $this->totalCashRevenue,
        'momo' => $this->totalMomoRevenue,
        'hubtel' => $this->totalHubtelRevenue,
        'avg_daily' => $this->averageDailyRevenue,
        'days' => $daysInPeriod,
    ]);
}

// private function calculateRevenueMetrics()
// {
//     $salesData = DB::table('daily_sales')
//         ->whereBetween('sales_date', [$this->startDate, $this->endDate])
//         ->selectRaw('
//             SUM(total_amount) as cash_revenue,
//             SUM(COALESCE(credit_amount, 0)) as credit_revenue,
//             SUM(total_amount) as drinks_total
//         ')
//         ->first();

//     $this->drinksTotal        = (float) ($salesData->drinks_total ?? 0);
//     $this->totalCreditRevenue = (float) ($salesData->credit_revenue ?? 0);

//     $summaries = $this->getCurrentPeriodSummaries();
//     $this->totalCashRevenue   = $summaries->sum('total_cash');
//     $this->totalMomoRevenue   = $summaries->sum('total_momo');
//     $this->totalHubtelRevenue = $summaries->sum('total_hubtel');

//     $daysInPeriod = max(1, \Carbon\Carbon::parse($this->startDate)->diffInDays($this->endDate) + 1);
//     $this->averageDailyRevenue = $this->drinksTotal / $daysInPeriod;

//     Log::info('Analytics: Revenue calculated', [
//         'drinks_total' => $this->drinksTotal,
//         'cash' => $this->totalCashRevenue,
//         'momo' => $this->totalMomoRevenue,
//         'hubtel' => $this->totalHubtelRevenue,
//         'credit' => $this->totalCreditRevenue,
//         'avg_daily' => $this->averageDailyRevenue,
//         'days' => $daysInPeriod,
//     ]);
// }
private function calculateSalesMetrics()
{
    $result = DB::table('daily_sales')
        ->whereBetween('sales_date', [$this->startDate, $this->endDate])
        ->selectRaw('
            SUM(opening_stock - closing_stock) as total_units_sold
        ')
        ->first();

    $this->totalItemsSold = (int) ($result->total_units_sold ?? 0);

    Log::info('Analytics: Sales calculated', [
        'total_units_sold' => $this->totalItemsSold,
    ]);
}

private function calculateExpenseMetrics()
{
    $this->totalExpenses = DB::table('stocks')
        ->whereBetween('restock_date', [$this->startDate, $this->endDate])
        ->where(function ($q) {
            $q->whereNull('notes')
              ->orWhere('notes', 'NOT LIKE', 'End of day inventory%');
        })
        ->sum('total_cost');

    Log::info('Analytics: Expenses calculated', [
        'total_expenses' => $this->totalExpenses,
    ]);
}

private function calculateProfitMetrics()
{
    $result = DB::table('daily_sales')
        ->whereBetween('sales_date', [$this->startDate, $this->endDate])
        ->selectRaw('SUM(unit_profit) as total_profit')
        ->first();


    Log::info('Analytics: Profit raw data', [
        'total_profit_raw' => $result->total_profit,
    ]);

    $this->totalProfit = (float) ($result->total_profit ?? 0);

    $this->grossProfitMargin = $this->drinksTotal > 0
        ? ($this->totalProfit / $this->drinksTotal) * 100
        : 0;



    // The price per unit is what was charged that day (total_amount / units), so VIP price days count.
    $marginData = DB::table('daily_sales as ds')
        ->join('stocks as s', 'ds.stock_id', '=', 's.id')
        ->whereBetween('ds.sales_date', [$this->startDate, $this->endDate])
        ->whereRaw('(ds.opening_stock - ds.closing_stock) > 0')
        ->where('ds.total_amount', '>', 0)
        ->where('s.cost_price', '>', 0)
        ->selectRaw('
            SUM(
                (1 - (s.cost_price * (ds.opening_stock - ds.closing_stock)) / ds.total_amount) * 100
                * (ds.opening_stock - ds.closing_stock)
            ) as weighted_margin,
            SUM(ds.opening_stock - ds.closing_stock) as total_units
        ')
        ->first();

        Log::info('Analytics: Margin data', [
            'weighted_margin' => $marginData->weighted_margin ?? 0,
            'total_units' => $marginData->total_units ?? 0,
        ]);

    $this->averageProfitMargin = ($marginData && $marginData->total_units > 0)
        ? (float) $marginData->weighted_margin / (float) $marginData->total_units
        : 0;

    Log::info('Analytics: Profit calculated', [
        'total_profit' => $this->totalProfit,
        'gross_margin_pct' => round($this->grossProfitMargin, 2),
        'avg_profit_margin_pct' => round($this->averageProfitMargin, 2),
    ]);
}

private function calculateLossMetrics()
{
    $summaries = $this->getCurrentPeriodSummaries();

    $this->totalOnTheHouse = $summaries->sum('on_the_house');

    // Accumulated collection discrepancies
    $this->accumulatedLosses = 0;
    foreach ($summaries as $summary) {
        $collected = ($summary->total_cash ?? 0) 
                   + ($summary->total_momo ?? 0) 
                   + ($summary->total_hubtel ?? 0);

        $expected = ($summary->drinks_total ?? 0) 
                  + ($summary->food_total ?? 0) 
                  - ($summary->on_the_house ?? 0);

        $this->accumulatedLosses += ($collected - $expected);
    }

    // Debt payments recorded against these days' shortfalls bring the loss back down.
    $this->debtRecovered = (new DebtLedger)->recoveredBetween(Carbon::parse($this->startDate), Carbon::parse($this->endDate));
    $this->netLosses = $this->accumulatedLosses + $this->debtRecovered;

    Log::info('Analytics: Loss calculated', [
        'accumulated_losses' => $this->accumulatedLosses,
        'debt_recovered' => $this->debtRecovered,
        'net_losses' => $this->netLosses,
        'on_the_house' => $this->totalOnTheHouse,
    ]);
}

// private function calculateLossMetrics()
// {
//     $lossData = DB::table('daily_sales')
//         ->join('products as p', 'daily_sales.product_id', '=', 'p.id')
//         ->whereBetween('daily_sales.sales_date', [$this->startDate, $this->endDate])
//         ->selectRaw('
//             SUM(COALESCE(daily_sales.damaged_units, 0)) as total_damaged_units,
//             SUM(COALESCE(daily_sales.loss_amount, 0)) as total_damaged_value,
//             SUM(COALESCE(daily_sales.credit_units, 0)) as total_credit_units
//         ')
//         ->first();

//     $this->totalDamagedUnits = (float) ($lossData->total_damaged_units ?? 0);
//     $this->totalDamagedValue = (float) ($lossData->total_damaged_value ?? 0);
//     $this->totalCreditUnits  = (float) ($lossData->total_credit_units ?? 0);
//     $this->totalLossAmount   = $this->totalDamagedValue;

//     $summaries = $this->getCurrentPeriodSummaries();
//     $this->totalOnTheHouse = $summaries->sum('on_the_house');

//     $this->accumulatedLosses = 0;
//     foreach ($summaries as $summary) {
//         $collected = ($summary->total_cash    ?? 0)
//                 + ($summary->total_momo    ?? 0)
//                 + ($summary->total_hubtel  ?? 0);

//         $expected  = ($summary->drinks_total  ?? 0)
//                 + ($summary->food_total    ?? 0)
//                 - ($summary->on_the_house  ?? 0);

//         $this->accumulatedLosses += ($collected - $expected);
//     }

//     $totalUnitsHandled = $this->totalItemsSold + $this->totalDamagedUnits;
//     $this->damageRate  = $totalUnitsHandled > 0
//         ? ($this->totalDamagedUnits / $totalUnitsHandled) * 100
//         : 0;

//     Log::info('Analytics: Loss calculated', [
//         'damaged_units' => $this->totalDamagedUnits,
//         'damaged_value' => $this->totalDamagedValue,
//         'credit_units' => $this->totalCreditUnits,
//         'on_the_house' => $this->totalOnTheHouse,
//         'accumulated_losses' => $this->accumulatedLosses,
//         'damage_rate_pct' => round($this->damageRate, 2),
//     ]);
// }

private function calculateFoodSalesMetrics()
{
    $summaries = $this->getCurrentPeriodSummaries();

    $this->totalFoodSales = $summaries->sum('food_total');

    $daysInPeriod = max(1, \Carbon\Carbon::parse($this->startDate)->diffInDays($this->endDate) + 1);
    $this->averageDailyFoodSales = $this->totalFoodSales / $daysInPeriod;

    Log::info('Analytics: Food sales calculated', [
        'total_food_sales' => $this->totalFoodSales,
        'avg_daily_food' => round($this->averageDailyFoodSales, 2),
    ]);
}

private function calculateSnookerMetrics()
{
    $summaries = $this->getCurrentPeriodSummaries();

    $this->totalSnooker = $summaries->sum('snooker');

    $daysInPeriod = max(1, \Carbon\Carbon::parse($this->startDate)->diffInDays($this->endDate) + 1);
    $this->averageDailySnookerSales = $this->totalSnooker / $daysInPeriod;

    Log::info('Analytics: Snooker calculated', [
        'total_snooker' => $this->totalSnooker,
        'avg_daily_snooker' => round($this->averageDailySnookerSales, 2),
    ]);
}

private function calculateInventoryMetrics()
{
    $latestStocks = DB::table('stocks as s1')
        ->whereRaw('s1.id = (SELECT MAX(s2.id) FROM stocks s2 WHERE s2.product_id = s1.product_id)')
        ->where('s1.total_units', '>', 0)
        ->select('s1.*')
        ->get();

    $this->totalInventoryValue = $latestStocks->sum(fn($s) => $s->total_units * ($s->cost_price ?? 0));

    $this->lowStockProducts = DB::table('products as p')
        ->join('stocks as s', function ($join) {
            $join->on('s.product_id', '=', 'p.id')
                 ->whereRaw('s.id = (SELECT MAX(s2.id) FROM stocks s2 WHERE s2.product_id = p.id)');
        })
        ->where('p.is_active', true)
        ->where('s.total_units', '>', 0)
        ->whereNotNull('p.stock_limit')
        ->where('p.stock_limit', '>', 0)
        ->whereRaw('s.total_units <= p.stock_limit * 0.2')
        ->count();

    $this->outOfStockProducts = DB::table('products as p')
        ->where('p.is_active', true)
        ->whereNotExists(function ($q) {
            $q->select(DB::raw(1))
              ->from('stocks')
              ->whereColumn('stocks.product_id', 'p.id')
              ->where('stocks.total_units', '>', 0);
        })
        ->count();

    $cogs = $this->drinksTotal - $this->totalProfit;
    $startInventory = $this->getInventoryValueAtDate($this->startDate);
    $this->averageInventoryValue = ($startInventory + $this->totalInventoryValue) / 2;
    $this->inventoryTurnoverRate = $this->averageInventoryValue > 0
        ? $cogs / $this->averageInventoryValue
        : 0;

    Log::info('Analytics: Inventory calculated', [
        'inventory_value' => $this->totalInventoryValue,
        'low_stock' => $this->lowStockProducts,
        'out_of_stock' => $this->outOfStockProducts,
        'turnover_rate' => round($this->inventoryTurnoverRate, 2),
        'avg_inventory_value' => round($this->averageInventoryValue, 2),
    ]);
}

    private function getInventoryValueAtDate($date)
    {
        // Get stock snapshot at specific date
        $stocks = Stock::where('created_at', '<=', $date)
            ->whereIn('id', function ($query) use ($date) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('stocks')
                    ->where('created_at', '<=', $date)
                    ->groupBy('product_id');
            })
            ->get();

        return $stocks->sum(function ($stock) {
            return $stock->total_units * ($stock->cost_price ?? 0);
        });
    }

    private function calculateProductPerformance()
    {
        // Top selling products
        $topSelling = DailySales::whereBetween('sales_date', [$this->startDate, $this->endDate])
            ->select(
                'product_id',
                DB::raw('SUM(opening_stock - closing_stock) as units_sold'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('SUM(unit_profit) as profit')
            )
            ->groupBy('product_id')
            ->orderBy('units_sold', 'desc')
            ->limit(10)
            ->with('product.category')
            ->get()
            ->map(function ($sale) {
                return [
                    'product_id' => $sale->product_id,
                    'product_name' => $sale->product->name ?? 'Unknown',
                    'category' => $sale->product->category->name ?? 'N/A',
                    'units_sold' => $sale->units_sold,
                    'revenue' => $sale->revenue,
                    'profit' => $sale->profit,
                ];
            });

        $this->topSellingProducts = $topSelling->toArray();

        // Least selling products
        $leastSelling = DailySales::whereBetween('sales_date', [$this->startDate, $this->endDate])
            ->select(
                'product_id',
                DB::raw('SUM(opening_stock - closing_stock) as units_sold'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->groupBy('product_id')
            ->orderBy('units_sold', 'asc')
            ->limit(10)
            ->with('product.category')
            ->get()
            ->map(function ($sale) {
                return [
                    'product_id' => $sale->product_id,
                    'product_name' => $sale->product->name ?? 'Unknown',
                    'category' => $sale->product->category->name ?? 'N/A',
                    'units_sold' => $sale->units_sold,
                    'revenue' => $sale->revenue,
                ];
            });

        $this->leastSellingProducts = $leastSelling->toArray();

        // Most profitable products
        $mostProfitable = DailySales::whereBetween('sales_date', [$this->startDate, $this->endDate])
            ->select(
                'product_id',
                DB::raw('SUM(unit_profit) as total_profit'),
                DB::raw('SUM(opening_stock - closing_stock) as units_sold')
            )
            ->groupBy('product_id')
            ->orderBy('total_profit', 'desc')
            ->limit(10)
            ->with('product')
            ->get()
            ->map(function ($sale) {
                return [
                    'product_id' => $sale->product_id,
                    'product_name' => $sale->product->name ?? 'Unknown',
                    'total_profit' => $sale->total_profit,
                    'units_sold' => $sale->units_sold,
                ];
            });

        $this->mostProfitableProducts = $mostProfitable->toArray();

        // Products with highest losses
$leastPerforming = DailySales::whereBetween('sales_date', [$this->startDate, $this->endDate])
    ->select(
        'product_id',
        DB::raw('SUM(total_amount) as revenue'),
        DB::raw('SUM(unit_profit) as profit'),
        DB::raw('SUM(opening_stock - closing_stock) as units_sold')
    )
    ->groupBy('product_id')
    ->havingRaw('SUM(opening_stock - closing_stock) > 0') // Only products that sold
    ->orderBy('revenue', 'asc') // Lowest revenue first
    ->limit(10)
    ->with('product.category')
    ->get()
    ->map(function ($sale) {
        $profitMargin = $sale->revenue > 0 ? ($sale->profit / $sale->revenue) * 100 : 0;
        return [
            'product_id' => $sale->product_id,
            'product_name' => $sale->product->name ?? 'Unknown',
            'category' => $sale->product->category->name ?? 'N/A',
            'units_sold' => $sale->units_sold,
            'revenue' => $sale->revenue,
            'profit' => $sale->profit,
            'profit_margin' => $profitMargin,
        ];
    });

$this->leastPerformingProducts = $leastPerforming->toArray();

    $top3 = array_slice($topSelling->toArray(), 0, 3);
    $bottom3 = array_slice($leastSelling->toArray(), 0, 3);
    Log::info('Analytics: Product performance calculated', [
        'top_products' => collect($top3)->pluck('product_name')->implode(', '),
        'top_units_sold' => collect($top3)->sum('units_sold'),
        'bottom_products' => collect($bottom3)->pluck('product_name')->implode(', '),
        'bottom_units_sold' => collect($bottom3)->sum('units_sold'),
    ]);
    }

    private function calculateCategoryPerformance()
    {
        $categoryData = DailySales::whereBetween('sales_date', [$this->startDate, $this->endDate])
            ->with('product.category')
            ->get()
            ->groupBy(function ($sale) {
                return $sale->product->category->name ?? 'Uncategorized';
            })
            ->map(function ($sales, $categoryName) {
                $revenue = $sales->sum('total_amount');

                $unitsSold = $sales->sum(function ($sale) {
                    return $sale->opening_stock - $sale->closing_stock;
                });

                $profit = $sales->sum('unit_profit');

                return [
                    'category_name' => $categoryName,
                    'revenue' => $revenue,
                    'units_sold' => $unitsSold,
                    'profit' => $profit,
                    'profit_margin' => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        $this->categoryPerformance = $categoryData->toArray();
        $this->topCategory = $categoryData->first();

    $topCat = $this->topCategory;
    Log::info('Analytics: Category performance calculated', [
        'categories_count' => count($categoryData),
        'top_category' => $topCat['category_name'] ?? 'N/A',
        'top_revenue' => $topCat['revenue'] ?? 0,
        'top_units_sold' => $topCat['units_sold'] ?? 0,
    ]);
    }

    private function calculateDailyData()
{
    $dailyData = DailySalesSummary::whereBetween('sales_date', [$this->startDate, $this->endDate])
        ->select(
            'sales_date',
            DB::raw('SUM(drinks_total) as revenue'),
            DB::raw('SUM(snooker) as snooker'),
            DB::raw('SUM(total_profit) as profit'),
            DB::raw('SUM(items_sold) as items'),
            DB::raw('SUM(total_cash + total_momo + total_hubtel) as total_collected'),
            DB::raw('SUM(drinks_total + food_total + snooker) as expected_total'),
            DB::raw('SUM(food_total) as food_sales'),
            DB::raw('SUM(on_the_house) as on_the_house')
        )
        ->groupBy('sales_date')
        ->orderBy('sales_date')
        ->get();

    $this->dailyProfitData = $dailyData->map(function ($day) {
        return [
            'date' => Carbon::parse($day->sales_date)->format('M d'),
            'profit' => round($day->profit ?? 0, 2),
            'profit_margin' => ($day->revenue ?? 0) > 0 
                ? round(($day->profit / $day->revenue) * 100, 2) 
                : 0,
        ];
    })->toArray();

    $this->dailyFoodSalesData = $dailyData->map(function ($day) {
        return [
            'date' => Carbon::parse($day->sales_date)->format('M d'),
            'full_date' => $day->sales_date,
            'amount' => round($day->food_sales ?? 0, 2),
        ];
    })->toArray();

    // Collection discrepancies (signed difference)
    $this->dailyLossesData = $dailyData->map(function ($day) {
        $collected = $day->total_collected ?? 0;
        $expected  = $day->expected_total ?? 0;
        $onHouse   = $day->on_the_house ?? 0;

        $difference = $collected - $expected + $onHouse;

        return [
            'date' => Carbon::parse($day->sales_date)->format('M d'),
            'full_date' => $day->sales_date,
            'loss' => round($difference, 2),
            'on_the_house' => round($onHouse, 2),
        ];
    })->toArray();

    Log::info('Analytics: Daily data calculated', [
        'days_count' => $dailyData->count(),
        'date_range' => $this->startDate . ' → ' . $this->endDate,
    ]);
}

//     private function calculateDailyData()
//     {
//         $dailyData = DailySalesSummary::whereBetween('sales_date', [$this->startDate, $this->endDate])
//             ->select(
//                 'sales_date',
//                 DB::raw('SUM(drinks_total) as revenue'),
//                 DB::raw('SUM(snooker) as snooker'),
//                 DB::raw('SUM(total_profit) as profit'),
//                 DB::raw('SUM(items_sold) as items'),
//                 DB::raw('SUM(total_damaged) as damaged'),
//                 DB::raw('SUM(total_money) as money_collected'),
//                 DB::raw('SUM(food_total) as food_sales'),
//                 DB::raw('SUM(on_the_house) as on_the_house'),
//                 DB::raw('SUM(total_cash + total_momo + total_hubtel) as total_collected'),
//                 DB::raw('SUM(drinks_total + food_total + snooker) as expected_total'),
//                 // DB::raw('SUM(drinks_total + food_total) as expected_total')
//             )
//             ->groupBy('sales_date')
//             ->orderBy('sales_date')
//             ->get();

// $this->dailyLossesData = $dailyData->map(function ($day) {
//     $difference = $day->total_collected - $day->expected_total + $day->on_the_house;
    
//     // Only show as loss if negative
//     $lossAmount = $difference < 0 ? abs($difference) : 0;
    
//     return [
//         'date' => Carbon::parse($day->sales_date)->format('M d'),
//         'full_date' => $day->sales_date,
//         'loss' => round($lossAmount, 2), // Always positive or zero
//         'on_the_house' => round($day->on_the_house, 2),
//     ];
// })->toArray();

//         $this->dailyProfitData = $dailyData->map(function ($day) {
//             return [
//                 'date' => Carbon::parse($day->sales_date)->format('M d'),
//                 'profit' => round($day->profit, 2),
//                 'profit_margin' => $day->revenue > 0 ? round(($day->profit / $day->revenue) * 100, 2) : 0,
//             ];
//         })->toArray();

//         // NEW: Food sales data
//         $this->dailyFoodSalesData = $dailyData->map(function ($day) {
//             return [
//                 'date' => Carbon::parse($day->sales_date)->format('M d'),
//                 'full_date' => $day->sales_date,
//                 'amount' => round($day->food_sales, 2),
//             ];
//         })->toArray();

//         // NEW: Daily losses data (collection discrepancies)
//         $this->dailyLossesData = $dailyData->map(function ($day) {
//             $difference = $day->total_collected - $day->expected_total + $day->on_the_house;
//             return [
//                 'date' => Carbon::parse($day->sales_date)->format('M d'),
//                 'full_date' => $day->sales_date,
//                 'loss' => round($difference, 2),
//                 'on_the_house' => round($day->on_the_house, 2),
//             ];
//         })->toArray();

//     Log::info('Analytics: Daily data calculated', [
//         'days_count' => $dailyData->count(),
//         'date_range' => $this->startDate . ' to ' . $this->endDate,
//     ]);
//     }

    private function calculatePaymentMethodDistribution()
    {
        $summaries = $this->getCurrentPeriodSummaries();

        $totalCash = $summaries->sum('total_cash');
        $totalMomo = $summaries->sum('total_momo');
        $totalHubtel = $summaries->sum('total_hubtel');
        $total = $totalCash + $totalMomo + $totalHubtel;

        $this->paymentMethodDistribution = [
            [
                'method' => 'Cash',
                'amount' => $totalCash,
                'percentage' => $total > 0 ? round(($totalCash / $total) * 100, 1) : 0,
            ],
            [
                'method' => 'Mobile Money',
                'amount' => $totalMomo,
                'percentage' => $total > 0 ? round(($totalMomo / $total) * 100, 1) : 0,
            ],
            [
                'method' => 'Hubtel',
                'amount' => $totalHubtel,
                'percentage' => $total > 0 ? round(($totalHubtel / $total) * 100, 1) : 0,
            ],
        ];

        Log::info('Analytics: Payment distribution calculated', [
            'cash' => $totalCash,
            'momo' => $totalMomo,
            'hubtel' => $totalHubtel,
            'total' => $total,
        ]);
    }

private function calculateGrowthRates()
{
    $currentDays       = \Carbon\Carbon::parse($this->startDate)->diffInDays($this->endDate) + 1;
    $previousStartDate = \Carbon\Carbon::parse($this->startDate)->subDays($currentDays)->format('Y-m-d');
    $previousEndDate   = \Carbon\Carbon::parse($this->startDate)->subDay()->format('Y-m-d');

    $prevSummaries = DB::table('daily_sales_summaries')
        ->whereBetween('sales_date', [$previousStartDate, $previousEndDate])
        ->selectRaw('
            SUM(food_total) as food_total,
            SUM(snooker) as snooker,
            SUM(total_profit) as total_profit
        ')
        ->first();

    // Previous drinks total from daily_sales
    $prevDrinksRow = DB::table('daily_sales')
        ->whereBetween('sales_date', [$previousStartDate, $previousEndDate])
        ->selectRaw('SUM(total_amount) as drinks_total')
        ->first();

    $prevItemsRow = DB::table('daily_sales') 
        ->whereBetween('sales_date', [$previousStartDate, $previousEndDate])
        ->selectRaw('SUM(opening_stock - closing_stock) as units_sold')
        ->first();

    $previousRevenue   = (float) ($prevDrinksRow->drinks_total ?? 0);
    $previousItemsSold = (float) ($prevItemsRow->units_sold ?? 0);
    $previousProfit    = (float) ($prevSummaries->total_profit ?? 0);
    $previousFoodSales = (float) ($prevSummaries->food_total ?? 0);

    $previousExpenses = DB::table('stocks')
        ->whereBetween('restock_date', [$previousStartDate, $previousEndDate])
        ->where(function ($q) {
            $q->whereNull('notes')
              ->orWhere('notes', 'NOT LIKE', 'End of day inventory%');
        })
        ->sum('total_cost');

    $this->revenueGrowth   = $this->calculateGrowthPercentage($this->drinksTotal,    $previousRevenue);
    $this->itemsSoldGrowth = $this->calculateGrowthPercentage($this->totalItemsSold, $previousItemsSold);
    $this->profitGrowth    = $this->calculateGrowthPercentage($this->totalProfit,    $previousProfit);
    $this->expensesGrowth  = $this->calculateGrowthPercentage($this->totalExpenses,  $previousExpenses);
    $this->foodSalesGrowth = $this->calculateGrowthPercentage($this->totalFoodSales, $previousFoodSales);

    Log::info('Analytics: Growth rates calculated', [
        'revenue_growth_pct' => round($this->revenueGrowth, 2),
        'items_sold_growth_pct' => round($this->itemsSoldGrowth, 2),
        'profit_growth_pct' => round($this->profitGrowth, 2),
        'expenses_growth_pct' => round($this->expensesGrowth, 2),
        'food_sales_growth_pct' => round($this->foodSalesGrowth, 2),
        'previous_revenue' => $previousRevenue,
        'previous_items_sold' => $previousItemsSold,
        'previous_profit' => $previousProfit,
        'previous_expenses' => $previousExpenses,
        'previous_food_sales' => $previousFoodSales,
    ]);
}


    private function calculateGrowthPercentage($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function getCurrentPeriodSummaries()
    {
        return DailySalesSummary::whereBetween('sales_date', [$this->startDate, $this->endDate])->get();
    }


    // Helper method to get available months for dropdown
    public function getAvailableMonths()
    {
        $months = DailySalesSummary::selectRaw("TO_CHAR(sales_date, 'YYYY-MM') as month")
            ->distinct()
            ->orderBy('month', 'desc')
            ->pluck('month')
            ->toArray();

        return $months;
    }

    public function exportCsv()
    {
        Log::info('Exporting analytics to Excel', [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ]);
        
        return Excel::download(
            new AnalyticsMultiSheetExport($this->startDate, $this->endDate),
            'analytics_export_' . now()->format('Y-m-d_His') . '.xlsx'
        );
    }

    public function render()
    {
        return view('livewire.pages.analytics', [
            'categories' => Categories::all(),
            'availableMonths' => $this->getAvailableMonths(),
        ]);
    }
}
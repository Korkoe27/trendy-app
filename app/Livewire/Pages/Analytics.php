<?php

namespace App\Livewire\Pages;

use App\Models\{DailySales, DailySalesSummary, Product, Stock, Categories};
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
    public $totalRevenue = 0;
    public $totalCashRevenue = 0;
    public $totalMomoRevenue = 0;
    public $totalHubtelRevenue = 0;
    public $totalCreditRevenue = 0;
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
    public $grossProfitMargin = 0;

    // Loss Metrics - IMPROVED
    public $totalDamagedUnits = 0;
    public $totalDamagedValue = 0;
    public $totalCreditUnits = 0;
    public $totalLossAmount = 0;
    public $damageRate = 0;
    public $accumulatedLosses = 0; // NEW: Collection discrepancies
    public $totalOnTheHouse = 0; // NEW: Sum of on_the_house

    // Inventory Metrics - IMPROVED
    public $totalInventoryValue = 0;
    public $lowStockProducts = 0;
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

public function loadMetrics()
{
    $this->isLoading = true;
    
    $this->calculateRevenueMetrics();
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

        $this->totalRevenue = $summaries->sum('total_revenue');
        $this->totalCashRevenue = $summaries->sum('total_cash');
        $this->totalMomoRevenue = $summaries->sum('total_momo');
        $this->totalHubtelRevenue = $summaries->sum('total_hubtel');
        $this->totalCreditRevenue = $summaries->sum('total_credit_amount');

        $daysInPeriod = max(1, Carbon::parse($this->startDate)->diffInDays($this->endDate) + 1);
        $this->averageDailyRevenue = $this->totalRevenue / $daysInPeriod;
    }

    private function calculateSalesMetrics()
    {
        $summaries = $this->getCurrentPeriodSummaries();
        $this->totalItemsSold = $summaries->sum('items_sold');
    }

    private function calculateExpenseMetrics()
    {
        $this->totalExpenses = Stock::whereBetween('restock_date', [$this->startDate, $this->endDate])
            ->sum('total_cost');
    }

    private function calculateProfitMetrics()
    {
        $summaries = $this->getCurrentPeriodSummaries();
        $this->totalProfit = $summaries->sum('total_profit');
        
        $this->grossProfitMargin = $this->totalRevenue > 0 
            ? ($this->totalProfit / $this->totalRevenue) * 100 
            : 0;

        // Calculate average profit margin using actual sales data
        $salesWithMargin = DailySales::whereBetween('sales_date', [$this->startDate, $this->endDate])
            ->with(['stock', 'product'])
            ->get();

        $totalMargin = 0;
        $count = 0;

        foreach ($salesWithMargin as $sale) {
            if ($sale->stock && $sale->product && $sale->product->selling_price > 0) {
                $unitsSold = max(0, $sale->opening_stock - $sale->closing_stock - 
                    ($sale->damaged_units ?? 0) - ($sale->credit_units ?? 0));
                
                if ($unitsSold > 0 && $sale->stock->cost_price > 0) {
                    $margin = (($sale->product->selling_price - $sale->stock->cost_price) / 
                        $sale->product->selling_price) * 100;
                    $totalMargin += $margin;
                    $count++;
                }
            }
        }

        $this->averageProfitMargin = $count > 0 ? $totalMargin / $count : 0;
    }

private function calculateLossMetrics()
{
    $summaries = $this->getCurrentPeriodSummaries();

    $this->totalDamagedUnits = $summaries->sum('total_damaged');
    $this->totalDamagedValue = $summaries->sum('total_loss_amount');
    $this->totalCreditUnits = $summaries->sum('total_credit_units');
    $this->totalLossAmount = $this->totalDamagedValue;

    // Calculate accumulated losses (collection discrepancies) - CORRECTED
    $this->accumulatedLosses = 0;
    $this->totalOnTheHouse = $summaries->sum('on_the_house'); // Simple sum

    foreach ($summaries as $summary) {
        $collected = ($summary->total_cash ?? 0) + 
                    ($summary->total_momo ?? 0) + 
                    ($summary->total_hubtel ?? 0);
        
        $expectedVal = ($summary->total_revenue ?? 0) + ($summary->food_total ?? 0);
        $onTheHouse = $summary->on_the_house ?? 0;
        
        $difference = $collected - $expectedVal + $onTheHouse;
        
        // Only accumulate if it's a loss (negative difference)
        if ($difference < 0) {
            $this->accumulatedLosses += abs($difference); // Store as positive for display
        }
    }

    // Calculate damage rate
    $totalUnitsHandled = $this->totalItemsSold + $this->totalDamagedUnits;
    $this->damageRate = $totalUnitsHandled > 0 
        ? ($this->totalDamagedUnits / $totalUnitsHandled) * 100 
        : 0;
}

    private function calculateFoodSalesMetrics()
    {
        $summaries = $this->getCurrentPeriodSummaries();
        
        $this->totalFoodSales = $summaries->sum('food_total');

        Log::info('Total Food Sales', ['total_food_sales' => $this->totalFoodSales]);
        
        $daysInPeriod = max(1, Carbon::parse($this->startDate)->diffInDays($this->endDate) + 1);
        $this->averageDailyFoodSales = $this->totalFoodSales / $daysInPeriod;
    }

    private function calculateInventoryMetrics()
    {
        // Get latest stock for each product using subquery
        $latestStocks = Stock::select('stocks.*')
            ->whereIn('id', function ($query) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('stocks')
                    ->groupBy('product_id');
            })
            ->where('total_units', '>', 0)
            ->with('product')
            ->get();

        // Current inventory value using cost_price
        $this->totalInventoryValue = $latestStocks->sum(function ($stock) {
            return $stock->total_units * ($stock->cost_price ?? 0);
        });

        // Low stock products
        $this->lowStockProducts = Product::where('is_active', true)
            ->whereHas('stocks', function ($query) {
                $query->where('total_units', '>', 0);
            })
            ->get()
            ->filter(function ($product) use ($latestStocks) {
                $stock = $latestStocks->firstWhere('product_id', $product->id);
                if ($stock && $product->stock_limit && $product->stock_limit > 0) {
                    return $stock->total_units <= ($product->stock_limit * 0.2);
                }
                return false;
            })
            ->count();

        // Out of stock products
        $this->outOfStockProducts = Product::where('is_active', true)
            ->whereDoesntHave('stocks', function ($query) {
                $query->where('total_units', '>', 0);
            })
            ->count();

        // Improved inventory turnover calculation
        // COGS = Revenue - Profit
        $cogs = $this->totalRevenue - $this->totalProfit;
        
        // Calculate average inventory value during period
        $startInventory = $this->getInventoryValueAtDate($this->startDate);
        $endInventory = $this->totalInventoryValue;
        $this->averageInventoryValue = ($startInventory + $endInventory) / 2;
        
        // Inventory Turnover = COGS / Average Inventory
        $this->inventoryTurnoverRate = $this->averageInventoryValue > 0 
            ? $cogs / $this->averageInventoryValue 
            : 0;
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
                DB::raw('SUM(opening_stock - closing_stock - COALESCE(damaged_units, 0) - COALESCE(credit_units, 0)) as units_sold'),
                DB::raw('SUM(total_amount + COALESCE(credit_amount, 0)) as revenue'),
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
                DB::raw('SUM(opening_stock - closing_stock - COALESCE(damaged_units, 0) - COALESCE(credit_units, 0)) as units_sold'),
                DB::raw('SUM(total_amount + COALESCE(credit_amount, 0)) as revenue')
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
                DB::raw('SUM(opening_stock - closing_stock - COALESCE(damaged_units, 0) - COALESCE(credit_units, 0)) as units_sold')
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
        DB::raw('SUM(total_amount + COALESCE(credit_amount, 0)) as revenue'),
        DB::raw('SUM(unit_profit) as profit'),
        DB::raw('SUM(COALESCE(damaged_units, 0)) as damaged_units'),
        DB::raw('SUM(opening_stock - closing_stock - COALESCE(damaged_units, 0) - COALESCE(credit_units, 0)) as units_sold')
    )
    ->groupBy('product_id')
    ->havingRaw('SUM(opening_stock - closing_stock - COALESCE(damaged_units, 0) - COALESCE(credit_units, 0)) > 0') // Only products that sold
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
            'damaged_units' => $sale->damaged_units,
        ];
    });

$this->leastPerformingProducts = $leastPerforming->toArray();
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
                $revenue = $sales->sum(function ($sale) {
                    return $sale->total_amount + ($sale->credit_amount ?? 0);
                });
                
                $unitsSold = $sales->sum(function ($sale) {
                    return $sale->opening_stock - $sale->closing_stock - 
                           ($sale->damaged_units ?? 0) - ($sale->credit_units ?? 0);
                });

                $profit = $sales->sum('unit_profit');
                $damaged = $sales->sum('damaged_units');

                return [
                    'category_name' => $categoryName,
                    'revenue' => $revenue,
                    'units_sold' => $unitsSold,
                    'profit' => $profit,
                    'damaged_units' => $damaged,
                    'profit_margin' => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        $this->categoryPerformance = $categoryData->toArray();
        $this->topCategory = $categoryData->first();
    }

    private function calculateDailyData()
    {
        $dailyData = DailySalesSummary::whereBetween('sales_date', [$this->startDate, $this->endDate])
            ->select(
                'sales_date',
                DB::raw('SUM(total_revenue) as revenue'),
                DB::raw('SUM(total_profit) as profit'),
                DB::raw('SUM(items_sold) as items'),
                DB::raw('SUM(total_damaged) as damaged'),
                DB::raw('SUM(total_money) as money_collected'),
                DB::raw('SUM(food_total) as food_sales'),
                DB::raw('SUM(on_the_house) as on_the_house'),
                DB::raw('SUM(total_cash + total_momo + total_hubtel) as total_collected'),
                DB::raw('SUM(total_revenue + food_total) as expected_total')
            )
            ->groupBy('sales_date')
            ->orderBy('sales_date')
            ->get();

$this->dailyLossesData = $dailyData->map(function ($day) {
    $difference = $day->total_collected - $day->expected_total + $day->on_the_house;
    
    // Only show as loss if negative
    $lossAmount = $difference < 0 ? abs($difference) : 0;
    
    return [
        'date' => Carbon::parse($day->sales_date)->format('M d'),
        'full_date' => $day->sales_date,
        'loss' => round($lossAmount, 2), // Always positive or zero
        'on_the_house' => round($day->on_the_house, 2),
    ];
})->toArray();

        $this->dailyProfitData = $dailyData->map(function ($day) {
            return [
                'date' => Carbon::parse($day->sales_date)->format('M d'),
                'profit' => round($day->profit, 2),
                'profit_margin' => $day->revenue > 0 ? round(($day->profit / $day->revenue) * 100, 2) : 0,
            ];
        })->toArray();

        // NEW: Food sales data
        $this->dailyFoodSalesData = $dailyData->map(function ($day) {
            return [
                'date' => Carbon::parse($day->sales_date)->format('M d'),
                'full_date' => $day->sales_date,
                'amount' => round($day->food_sales, 2),
            ];
        })->toArray();

        // NEW: Daily losses data (collection discrepancies)
        $this->dailyLossesData = $dailyData->map(function ($day) {
            $difference = $day->total_collected - $day->expected_total + $day->on_the_house;
            return [
                'date' => Carbon::parse($day->sales_date)->format('M d'),
                'full_date' => $day->sales_date,
                'loss' => round($difference, 2),
                'on_the_house' => round($day->on_the_house, 2),
            ];
        })->toArray();
    }

    private function calculatePaymentMethodDistribution()
    {
        $summaries = $this->getCurrentPeriodSummaries();

        $totalCash = $summaries->sum('total_cash');
        $totalMomo = $summaries->sum('total_momo');
        $totalHubtel = $summaries->sum('total_hubtel');
        $totalCredit = $summaries->sum('total_credit_amount');
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
            [
                'method' => 'Credit',
                'amount' => $totalCredit,
                'percentage' => $this->totalRevenue > 0 ? round(($totalCredit / $this->totalRevenue) * 100, 1) : 0,
                'is_credit' => true,
            ],
        ];
    }

    private function calculateGrowthRates()
    {
        $currentDays = Carbon::parse($this->startDate)->diffInDays($this->endDate) + 1;
        $previousStartDate = Carbon::parse($this->startDate)->subDays($currentDays)->format('Y-m-d');
        $previousEndDate = Carbon::parse($this->startDate)->subDay()->format('Y-m-d');

        $previousSummaries = DailySalesSummary::whereBetween('sales_date', [$previousStartDate, $previousEndDate])->get();

        $previousRevenue = $previousSummaries->sum('total_revenue');
        $previousItemsSold = $previousSummaries->sum('items_sold');
        $previousProfit = $previousSummaries->sum('total_profit');
        $previousFoodSales = $previousSummaries->sum('food_total');
        
        $previousExpenses = Stock::whereBetween('restock_date', [$previousStartDate, $previousEndDate])
            ->sum('total_cost');

        $this->revenueGrowth = $this->calculateGrowthPercentage($this->totalRevenue, $previousRevenue);
        $this->itemsSoldGrowth = $this->calculateGrowthPercentage($this->totalItemsSold, $previousItemsSold);
        $this->profitGrowth = $this->calculateGrowthPercentage($this->totalProfit, $previousProfit);
        $this->expensesGrowth = $this->calculateGrowthPercentage($this->totalExpenses, $previousExpenses);
        $this->foodSalesGrowth = $this->calculateGrowthPercentage($this->totalFoodSales, $previousFoodSales);
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

    public function render()
    {
        return view('livewire.pages.analytics', [
            'categories' => Categories::all(),
            'availableMonths' => $this->getAvailableMonths(),
        ]);
    }
}
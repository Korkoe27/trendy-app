<?php

namespace App\Livewire\Pages;

use App\Models\{DailySales,DailySalesSummary,Product,Stock,Categories};
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Analytics extends Component
{
    public $timeframe = '30d'; // Default timeframe
    public $startDate;
    public $endDate;
    public $customDateRange = false;

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

    // Loss Metrics
    public $totalDamagedUnits = 0;
    public $totalDamagedValue = 0;
    public $totalCreditUnits = 0;
    public $totalLossAmount = 0;
    public $damageRate = 0;

    // Inventory Metrics
    public $totalInventoryValue = 0;
    public $lowStockProducts = 0;
    public $outOfStockProducts = 0;
    public $inventoryTurnoverRate = 0;

    // Product Performance
    public $topSellingProducts = [];
    public $leastSellingProducts = [];
    public $mostProfitableProducts = [];
    public $highestLossProducts = [];

    // Category Performance
    public $categoryPerformance = [];
    public $topCategory = null;

    // Daily breakdown for charts
    public $dailySalesData = [];
    public $dailyProfitData = [];
    public $paymentMethodDistribution = [];

    public function mount()
    {
        $this->setDateRange();
        $this->calculateAllMetrics();
    }

    public function updatedTimeframe()
    {
        if ($this->timeframe !== 'custom') {
            $this->customDateRange = false;
            $this->setDateRange();
            $this->calculateAllMetrics();
        } else {
            $this->customDateRange = true;
        }
    }

    public function updatedStartDate()
    {
        if ($this->customDateRange && $this->startDate && $this->endDate) {
            $this->calculateAllMetrics();
        }
    }

    public function updatedEndDate()
    {
        if ($this->customDateRange && $this->startDate && $this->endDate) {
            $this->calculateAllMetrics();
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

    public function calculateAllMetrics()
    {
        try {
            $this->calculateRevenueMetrics();
            $this->calculateSalesMetrics();
            $this->calculateExpenseMetrics();
            $this->calculateProfitMetrics();
            $this->calculateLossMetrics();
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
        // Calculate total expenses from stock purchases during the period
        $this->totalExpenses = Stock::whereBetween('restock_date', [$this->startDate, $this->endDate])
            ->sum('total_cost');
    }

    private function calculateProfitMetrics()
    {
        $summaries = $this->getCurrentPeriodSummaries();

        $this->totalProfit = $summaries->sum('total_profit');
        
        // Calculate gross profit margin percentage
        $this->grossProfitMargin = $this->totalRevenue > 0 
            ? ($this->totalProfit / $this->totalRevenue) * 100 
            : 0;

        // Calculate average profit margin across all products
        $salesWithCost = DailySales::whereBetween('sales_date', [$this->startDate, $this->endDate])
            ->with(['stock', 'product'])
            ->get();

        $totalMargin = 0;
        $count = 0;

        foreach ($salesWithCost as $sale) {
            if ($sale->stock && $sale->product) {
                $unitsSold = $sale->opening_stock - $sale->closing_stock - ($sale->damaged_units ?? 0) - ($sale->credit_units ?? 0);
                if ($unitsSold > 0) {
                    $margin = (($sale->product->selling_price - $sale->stock->cost_price) / $sale->product->selling_price) * 100;
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

        // Calculate damage rate as percentage of items sold
        $this->damageRate = $this->totalItemsSold > 0 
            ? ($this->totalDamagedUnits / ($this->totalItemsSold + $this->totalDamagedUnits)) * 100 
            : 0;
    }

    private function calculateInventoryMetrics()
    {
        // Current inventory value
        $stocks = Stock::with('product')->where('total_units', '>', 0)->get();
        
        $this->totalInventoryValue = $stocks->sum(function ($stock) {
            return $stock->total_units * $stock->cost_price;
        });

        // Low stock products (below 20% of their limit)
        $this->lowStockProducts = Product::whereHas('stocks', function ($query) {
            $query->where('total_units', '>', 0);
        })
        ->get()
        ->filter(function ($product) {
            $stock = $product->stocks->first();
            if ($stock && $product->stock_limit) {
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

        // Inventory turnover rate (COGS / Average Inventory Value)
        $cogs = $this->totalRevenue - $this->totalProfit; // Cost of goods sold
        $this->inventoryTurnoverRate = $this->totalInventoryValue > 0 
            ? $cogs / $this->totalInventoryValue 
            : 0;
    }

    private function calculateProductPerformance()
    {
        // Top selling products by units
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

        // Least selling products (active products with sales)
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

        // Products with highest losses (damaged + credit)
        $highestLoss = DailySales::whereBetween('sales_date', [$this->startDate, $this->endDate])
            ->select(
                'product_id',
                DB::raw('SUM(COALESCE(damaged_units, 0)) as damaged_units'),
                DB::raw('SUM(COALESCE(loss_amount, 0)) as loss_value'),
                DB::raw('SUM(COALESCE(credit_units, 0)) as credit_units'),
                DB::raw('SUM(COALESCE(credit_amount, 0)) as credit_value')
            )
            ->groupBy('product_id')
            ->havingRaw('SUM(COALESCE(damaged_units, 0) + COALESCE(credit_units, 0)) > 0')
            ->orderByRaw('SUM(COALESCE(loss_amount, 0)) desc')
            ->limit(10)
            ->with('product')
            ->get()
            ->map(function ($sale) {
                return [
                    'product_id' => $sale->product_id,
                    'product_name' => $sale->product->name ?? 'Unknown',
                    'damaged_units' => $sale->damaged_units,
                    'loss_value' => $sale->loss_value,
                    'credit_units' => $sale->credit_units,
                    'credit_value' => $sale->credit_value,
                    'total_loss' => $sale->loss_value,
                ];
            });

        $this->highestLossProducts = $highestLoss->toArray();
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
                DB::raw('SUM(total_money) as money_collected')
            )
            ->groupBy('sales_date')
            ->orderBy('sales_date')
            ->get();

        $this->dailySalesData = $dailyData->map(function ($day) {
            return [
                'date' => Carbon::parse($day->sales_date)->format('M d'),
                'full_date' => $day->sales_date,
                'revenue' => round($day->revenue, 2),
                'profit' => round($day->profit, 2),
                'items' => $day->items,
                'damaged' => $day->damaged,
                'money_collected' => round($day->money_collected, 2),
            ];
        })->toArray();

        $this->dailyProfitData = $dailyData->map(function ($day) {
            return [
                'date' => Carbon::parse($day->sales_date)->format('M d'),
                'profit' => round($day->profit, 2),
                'profit_margin' => $day->revenue > 0 ? round(($day->profit / $day->revenue) * 100, 2) : 0,
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
        // Get previous period data for comparison
        $currentDays = Carbon::parse($this->startDate)->diffInDays($this->endDate) + 1;
        $previousStartDate = Carbon::parse($this->startDate)->subDays($currentDays)->format('Y-m-d');
        $previousEndDate = Carbon::parse($this->startDate)->subDay()->format('Y-m-d');

        $previousSummaries = DailySalesSummary::whereBetween('sales_date', [$previousStartDate, $previousEndDate])->get();

        $previousRevenue = $previousSummaries->sum('total_revenue');
        $previousItemsSold = $previousSummaries->sum('items_sold');
        $previousProfit = $previousSummaries->sum('total_profit');
        
        // Calculate previous period expenses
        $previousExpenses = Stock::whereBetween('restock_date', [$previousStartDate, $previousEndDate])
            ->sum('total_cost');

        $this->revenueGrowth = $this->calculateGrowthPercentage($this->totalRevenue, $previousRevenue);
        $this->itemsSoldGrowth = $this->calculateGrowthPercentage($this->totalItemsSold, $previousItemsSold);
        $this->profitGrowth = $this->calculateGrowthPercentage($this->totalProfit, $previousProfit);
        $this->expensesGrowth = $this->calculateGrowthPercentage($this->totalExpenses, $previousExpenses);
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

    public function render()
    {
        return view('livewire.pages.analytics', [
            'categories' => Categories::all(),
        ]);
    }
}
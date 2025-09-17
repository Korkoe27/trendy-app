<?php

namespace App\Livewire;

use App\Models\DailySalesSummary;
use App\Models\Product;
use App\Models\Categories;
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\{WithPagination, WithoutUrlPagination};

class Analytics extends Component
{

    use WithPagination, WithoutUrlPagination;
    public $timeframe = '7d';
    public $totalRevenue = 0;
    public $totalItemsSold = 0;
    public $averageOrder = 0;
    public $revenueGrowth = 0;
    public $itemsGrowth = 0;
    public $orderGrowth = 0;
    
    public function mount()
    {
        $this->calculateMetrics();
    }
    
    public function updatedTimeframe()
    {
        $this->calculateMetrics();
    }
    
    public function calculateMetrics()
    {
        $days = $this->getDaysFromTimeframe();
        $currentPeriod = $this->getCurrentPeriodData($days);
        $previousPeriod = $this->getPreviousPeriodData($days);
        
        // Calculate current metrics
        $this->totalRevenue = $currentPeriod['revenue'];
        $this->totalItemsSold = $currentPeriod['items'];
        $this->averageOrder = $currentPeriod['items'] > 0 ? $currentPeriod['revenue'] / $currentPeriod['items'] : 0;
        
        // Calculate growth percentages
        $this->revenueGrowth = $this->calculateGrowth($currentPeriod['revenue'], $previousPeriod['revenue']);
        $this->itemsGrowth = $this->calculateGrowth($currentPeriod['items'], $previousPeriod['items']);
        $this->orderGrowth = $this->calculateGrowth($this->averageOrder, $previousPeriod['items'] > 0 ? $previousPeriod['revenue'] / $previousPeriod['items'] : 0);
    }
    
    private function getDaysFromTimeframe()
    {
        return match($this->timeframe) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 7
        };
    }
    
    private function getCurrentPeriodData($days)
    {
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        
        return DailySalesSummary::where('created_at', '>=', $startDate)
            ->selectRaw('SUM(total_revenue) as revenue, SUM(items_sold) as items')
            ->first()
            ->toArray();
    }
    
    private function getPreviousPeriodData($days)
    {
        $endDate = Carbon::now()->subDays($days)->endOfDay();
        $startDate = Carbon::now()->subDays($days * 2 - 1)->startOfDay();
        
        return DailySalesSummary::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('SUM(total_revenue) as revenue, SUM(items_sold) as items')
            ->first()
            ->toArray();
    }
    
    private function calculateGrowth($current, $previous)
    {
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return round((($current - $previous) / $previous) * 100, 1);
    }
    
    public function getDailySalesData()
    {
        $days = $this->getDaysFromTimeframe();
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        
        $salesData = DailySalesSummary::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_revenue) as sales'),
                DB::raw('SUM(items_sold) as items')
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'day' => Carbon::parse($item->date)->format('D'),
                    'sales' => round($item->sales, 2),
                    'items' => $item->items
                ];
            });
        
        // Fill in missing days with zero values
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayData = $salesData->firstWhere('day', $date->format('D'));
            
            $result[] = [
                'day' => $date->format('D'),
                'sales' => $dayData ? $dayData['sales'] : 0,
                'items' => $dayData ? $dayData['items'] : 0
            ];
        }
        
        return collect($result);
    }
    
    public function getTopProducts()
    {
        $days = $this->getDaysFromTimeframe();
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        
        return DailySalesSummary::with('product')
            ->select(
                'product_id',
                DB::raw('SUM(items_sold) as total_sales'),
                DB::raw('SUM(total_revenue) as total_revenue')
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy('product_id')
            ->orderBy('total_revenue', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                // Calculate growth (simplified - you might want to compare with previous period)
                $growth = rand(-5, 20) / 10; // Placeholder - implement actual growth calculation
                
                return [
                    'name' => $item->product->name ?? 'Unknown Product',
                    'sales' => $item->total_sales,
                    'revenue' => round($item->total_revenue, 2),
                    'growth' => $growth
                ];
            });
    }
    
    public function getCategoryData()
    {
        $days = $this->getDaysFromTimeframe();
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        
        $categoryData = DailySalesSummary::with(['product.category'])
            ->select(
                DB::raw('SUM(total_revenue) as total_revenue'),
                'product_id'
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy('product_id')
            ->get()
            ->groupBy(function ($item) {
                return $item->product->category->name ?? 'Uncategorized';
            })
            ->map(function ($items, $categoryName) {
                return [
                    'name' => $categoryName,
                    'sales' => $items->sum('total_revenue'),
                    'color' => $this->getCategoryColor($categoryName)
                ];
            });
        
        $totalSales = $categoryData->sum('sales');
        
        return $categoryData->map(function ($category) use ($totalSales) {
            $percentage = $totalSales > 0 ? round(($category['sales'] / $totalSales) * 100, 1) : 0;
            
            return [
                'name' => $category['name'],
                'sales' => round($category['sales'], 2),
                'percentage' => $percentage,
                'color' => $category['color']
            ];
        })->sortByDesc('sales')->take(3);
    }
    
    private function getCategoryColor($categoryName)
    {
        $colors = [
            'Beverages' => 'bg-blue-500',
            'Food' => 'bg-green-500',
            'Spirits' => 'bg-purple-500',
            'Snacks' => 'bg-yellow-500',
            'Games' => 'bg-red-500'
        ];
        
        return $colors[$categoryName] ?? 'bg-gray-500';
    }
    
    public function render()
    {
        $salesData = $this->getDailySalesData();
        $topProducts = $this->getTopProducts();
        $categoryData = $this->getCategoryData();
        $maxSales = $salesData->max('sales') ?: 1;
        
        return view('livewire.analytics', compact(
            'salesData',
            'topProducts', 
            'categoryData',
            'maxSales'
        ));
    }
}
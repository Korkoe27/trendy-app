<?php

namespace App\Livewire\Pages;

use App\Models\{ActivityLogs, Order, Product,DailySales, DailySalesSummary, Stock};
use App\Services\PriceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Dashboard extends Component
{
    public $currentDate;
    public $hasTodayData = false;
    public $displayDate;

    public function mount()
    {
        $this->currentDate = Carbon::today();
        $this->checkTodayData();
    }

    public function checkTodayData()
    {
        // Check if today's sales data exists
        $this->hasTodayData = DailySales::whereDate('created_at', $this->currentDate)->exists();
        
        if (!$this->hasTodayData) {
            // Use yesterday's data if today's doesn't exist
            $this->displayDate = $this->currentDate->copy()->subDay();
        } else {
            $this->displayDate = $this->currentDate;
        }
    }


    public function getLowStockProductsProperty()
{
    // Only drinks have stock; games and food never raise a low-stock alert.
    return Product::stocked()->where(function ($query) {
            $query->whereDoesntHave('stocks')
                  ->orWhereHas('stocks', function ($q) {
                      $q->whereColumn('stocks.total_units', '<=', 'products.stock_limit');
                  });
        })
        ->with(['currentStock', 'category'])
        ->get()
        ->filter(function ($product) {
            // Extra safety: ensure "good stock" is removed at collection level
            $current = $product->currentStock?->total_units ?? 0;
            $limit   = $product->stock_limit ?? null;

            return $limit === null || $current <= $limit;
        })
        ->map(function ($product) {
            $current = $product->currentStock?->total_units ?? 0;
            $limit = $product->stock_limit ?? null;
            $percentage = $limit > 0 ? min(100, round(($current / $limit) * 100)) : 0;

            if ($limit !== null && $current <= 0) {
                $statusText   = 'No Stock';
                $statusColor  = 'text-red-600 bg-red-100';
                $statusBorder = 'border-red-200';
                $statusBg     = 'bg-red-50';
            } elseif ($limit && $current <= $limit) {
                $statusText   = 'Low Stock';
                $statusColor  = 'text-yellow-600 bg-yellow-100';
                $statusBorder = 'border-yellow-200';
                $statusBg     = 'bg-yellow-50';
            } else {
                // This should no longer appear
                return null;
            }

            $product['percentage']   = $percentage;
            $product['statusText']   = $statusText;
            $product['statusColor']  = $statusColor;
            $product['statusBorder'] = $statusBorder;
            $product['statusBg']     = $statusBg;

            return $product;
        })
        ->filter(); // removes nulls (good stock)
}

//     public function getLowStockProductsProperty()
// {
//     return Product::where(function ($query) {
//             $query->whereHas('stocks', function ($q) {
//                 $q->whereRaw('stocks.total_units <= products.stock_limit');
//             })
//             ->orWhere(function ($q) {
//                 $q->whereDoesntHave('stocks')
//                 ->whereNotNull('stock_limit');
//             });
//         })
//         ->whereNotNull('stock_limit')
//         ->with(['currentStock', 'category'])
//         ->get()
//         ->map(function ($product) {
//             $current = $product->currentStock?->total_units ?? 0;
//             $limit = $product->stock_limit ?? null;
//             $percentage = $limit > 0 ? min(100, round(($current / $limit) * 100)) : 0;

//             if ($limit !== null && $current <= 0) {
//                 $statusText   = 'No Stock';
//                 $statusColor  = 'text-red-600 bg-red-100';
//                 $statusBorder = 'border-red-200';
//                 $statusBg     = 'bg-red-50';
//             } elseif ($limit && $current <= $limit) {
//                 $statusText   = 'Low Stock';
//                 $statusColor  = 'text-yellow-600 bg-yellow-100';
//                 $statusBorder = 'border-yellow-200';
//                 $statusBg     = 'bg-yellow-50';
//             } 
//             else {
//                 $statusText   = 'Good Stock';
//                 $statusColor  = 'text-green-600 bg-green-100';
//                 $statusBorder = 'border-green-200';
//                 $statusBg     = 'bg-green-50';
//             }
//             $product['percentage']   = $percentage;
// $product['statusText']   = $statusText;
// $product['statusColor']  = $statusColor;
// $product['statusBorder'] = $statusBorder;
// $product['statusBg']     = $statusBg;

// Log::info("Product: {$product->name}, Current: {$current}, Limit: {$limit}, Status: {$statusText}");

//             return $product;
//         });
// }

    /** Open order tabs that are still unpaid: the "pending debt" waiters and managers chase. */
    public function getUnpaidTabsProperty()
    {
        if (! Auth::user()?->hasPermission('view', 'order_debts')) {
            return collect();
        }

        return Order::unpaid()->visibleTo(Auth::user())->with('items')->orderBy('created_at')->get();
    }

    public function getTotalUnpaidTabsAmountProperty()
    {
        return (float) $this->unpaidTabs->sum('total');
    }

    public function getTodayRevenueProperty()
    {
        $revenue = DailySalesSummary::whereDate('created_at', $this->displayDate)
            ->sum(DB::raw('total_cash + total_momo + total_hubtel'));
        
        return number_format($revenue, 2);
    }

    public function getItemsSoldProperty()
    {
        return DailySales::whereDate('created_at', $this->displayDate)
            ->sum(DB::raw('opening_stock - closing_stock'));
    }

    public function getActiveProductsProperty()
    {
        return Product::where('is_active', true)->count();
    }

// public function getLowStockProductsProperty()
// {
//     return Product::where(function ($query) {
//             $query->whereHas('stocks', function ($q) {
//                 $q->whereRaw('stocks.total_units <= products.stock_limit');
//             })
//             ->orWhereDoesntHave('stocks');
//         })
//         ->with(['currentStock', 'category'])
//         ->get()
//         ->map(function ($product) {
//             $current = $product->currentStock?->total_units ?? 0;
//             $limit = $product->stock_limit ?? 1;
//             $percentage = $limit > 0 ? min(100, round(($current / $limit) * 100)) : 0;
//             $product['percentage'] = $percentage;
//             return $product;
//         });
// }


    public function getRecentSalesProperty()
    {
        return DailySales::whereDate('created_at', $this->displayDate)
            ->with(['product.category'])
            ->orderBy('updated_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($sale) {
                $unitsSold = $sale->opening_stock - $sale->closing_stock;
                $revenue = $sale->total_cash + $sale->total_momo + $sale->total_hubtel;
                $product = Product::find($sale->product_id);
                return [
                    'product' => $product->name ?? 'N/A',
                    'category' => $sale->products->category->name ?? 'N/A',
                    'quantity' => $unitsSold,
                    'revenue' => number_format($revenue, 2),
                    'time' => $sale->updated_at->diffForHumans(),
                ];
            });
    }

    public function getBestSellingProductsProperty()
{
    return $this->bestSellingProducts();
}

    public function bestSellingProducts()
    {
        $prices = app(PriceService::class);

        return DailySales::whereDate('created_at', $this->displayDate)
            ->with(['product'])
            ->select('product_id', DB::raw('SUM(opening_stock - closing_stock) as total_units_sold'))
            ->groupBy('product_id')
            ->orderByDesc('total_units_sold')
            ->take(5)
            ->get()
            ->map(function ($sale) use ($prices) {
                return [
                    'product' => $sale->product->name ?? 'N/A',
                    'category' => $sale->product->category->name ?? 'N/A',
                    'units_sold' => $sale->total_units_sold,
                    'revenue' => number_format(
                        ($sale->total_units_sold * $prices->priceOn((int) $sale->product_id, $sale->product->selling_price ?? 0, $this->displayDate)),
                        2
                    ),
                    // 'time' => Carbon::parse($sale->created_at)->diffForHumans()
                ];
            });
    }

public function getLowStockItemsProperty()
{
    return $this->lowStockProducts;
}


    public function goToInventory()
    {
        return redirect()->route('inventory'); // Adjust route name as needed
    }

    public function render()
    {
        return view('livewire.pages.dashboard',[
            'lowStockItems' => $this->lowStockItems,
        ]);
    }
}
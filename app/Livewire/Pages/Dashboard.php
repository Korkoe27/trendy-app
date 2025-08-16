<?php

namespace App\Livewire\Pages;

use App\Models\{Product,DailySales, DailySalesSummary, Stock};
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

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

    public function getLowStockProductsProperty()
    {
        return Product::whereHas('stocks', function ($query) {
            $query->whereRaw('stocks.total_units < products.stock_limit')
                ->orWhere('stocks.total_units', '=', 0);
        })->with(['stocks', 'category'])->get();
    }

    public function getRecentSalesProperty()
    {
        return DailySales::whereDate('created_at', $this->displayDate)
            ->with(['products.category'])
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

    public function getLowStockItemsProperty()
    {
        return $this->lowStockProducts->map(function ($product) {
            $stock = $product->stocks->first();
            return [
                'name' => $product->name,
                'current' => (int) $stock->total_units,
                'minimum' => $product->stock_limit,
                'category' => $product->category->name ?? 'N/A',
                'percentage' => $product->stock_limit > 0 ? 
                    min(100, ($stock->total_units / $product->stock_limit) * 100) : 0
            ];
        });
    }

    public function goToInventory()
    {
        return redirect()->route('inventory'); // Adjust route name as needed
    }

    public function render()
    {
        return view('livewire.pages.dashboard');
    }
}
<?php

namespace App\Livewire\Pages;

use App\Models\{ActivityLogs, CreditSales, Product,DailySales, DailySalesSummary, Stock};
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
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

    public function getUnpaidCreditsProperty()
{
    return CreditSales::with(['product'])
        ->unpaid()
        ->orderBy('credit_date', 'desc')
        ->get();
}

public function getTotalUnpaidCreditAmountProperty()
{
    return CreditSales::unpaid()->sum('credit_amount');
}

public function markCreditAsPaid($creditId)
{
    DB::transaction(function () use ($creditId) {
        $credit = CreditSales::find($creditId);
        
        if (!$credit) {
            session()->flash('error', 'Credit not found.');
            return;
        }
        
        $credit->update([
            'status' => 'paid',
            'amount_paid' => $credit->credit_amount,
            'payment_date' => now(),
            'metadata' => json_encode([
                'paid_by' => Auth::id(),
                'payment_method' => 'manual_marking',
                'original_amount' => $credit->credit_amount,
            ]),
        ]);

        // Update the daily sales summary profit
        $summary = DailySalesSummary::where('sales_date', $credit->credit_date)->first();
        if ($summary) {
            $summary->increment('total_profit', $credit->credit_amount);
            $summary->decrement('total_credit_amount', $credit->credit_amount);
        }

        ActivityLogs::create([
            'user_id' => Auth::id(),
            'action_type' => 'credit_payment',
            'description' => "Credit payment received from {$credit->customer_name}",
            'entity_type' => 'credit_sale',
            'entity_id' => $credit->id,
            'metadata' => json_encode([
                'amount_paid' => $credit->credit_amount,
                'customer_phone' => $credit->customer_phone,
                'customer_name' => $credit->customer_name,
                'product_id' => $credit->product_id,
            ]),
        ]);
    });

    session()->flash('success', 'Credit marked as paid successfully!');
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
                ->orWhere('stocks.total_units', '=', 0)
                ->orWhereNull('stocks.total_units');
        })
        ->orWhereDoesntHave('stocks') // ✅ Include products with no stock records
        ->with(['stocks', 'category'])
        ->get();
}



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
        return DailySales::whereDate('created_at', $this->displayDate)
            ->with(['product'])
            ->select('product_id', DB::raw('SUM(opening_stock - closing_stock) as total_units_sold'))
            ->groupBy('product_id')
            ->orderByDesc('total_units_sold')
            ->take(5)
            ->get()
            ->map(function ($sale) {
                return [
                    'product' => $sale->product->name ?? 'N/A',
                    'category' => $sale->product->category->name ?? 'N/A',
                    'units_sold' => $sale->total_units_sold,
                    'revenue' => number_format(
                        ($sale->total_units_sold * ($sale->product->selling_price ?? 0)),
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
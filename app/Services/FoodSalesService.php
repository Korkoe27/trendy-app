<?php

namespace App\Services;

use App\Models\FoodOrder;
use App\Models\FoodSales;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\Carbon;

/**
 * Food revenue for a business day, read from the food orders (cancelled ones never count). The
 * end-of-day stock count shows it in the food section and saves a summary row in food_sales.
 */
class FoodSalesService
{
    /**
     * @return array{orders: int, items: int, revenue: float, cancelled: int, unfinished: int, breakdown: list<array{name: string, quantity: int, amount: float}>}
     */
    public function forDate(Carbon|string $date): array
    {
        $date = Carbon::parse($date)->toDateString();

        $orders = FoodOrder::whereDate('business_date', $date)->get(['id', 'status', 'total', 'items_count']);
        $counted = $orders->where('status', '!=', FoodOrder::STATUS_CANCELLED);

        $breakdown = OrderItem::whereIn('food_order_id', $counted->pluck('id'))
            ->selectRaw('product_name, SUM(quantity) as quantity, SUM(line_total) as amount')
            ->groupBy('product_name')
            ->orderByRaw('SUM(line_total) DESC')
            ->get()
            ->map(fn ($row) => ['name' => $row->product_name, 'quantity' => (int) $row->quantity, 'amount' => round((float) $row->amount, 2)])
            ->all();

        return [
            'orders' => $counted->count(),
            'items' => (int) $counted->sum('items_count'),
            'revenue' => round((float) $counted->sum('total'), 2),
            'cancelled' => $orders->where('status', FoodOrder::STATUS_CANCELLED)->count(),
            // Food the kitchen has not finished, or the waiter has not picked up yet.
            'unfinished' => $orders->whereIn('status', [FoodOrder::STATUS_PENDING, FoodOrder::STATUS_READY])->count(),
            'breakdown' => $breakdown,
        ];
    }

    public function recordedOn(Carbon|string $date): bool
    {
        return FoodSales::whereDate('sales_date', Carbon::parse($date)->toDateString())->exists();
    }

    /** Save (or refresh) the day's summary. Called when the stock count for the day is submitted or corrected. */
    public function record(Carbon|string $date, ?User $by = null): FoodSales
    {
        $date = Carbon::parse($date)->toDateString();
        $day = $this->forDate($date);

        return FoodSales::updateOrCreate(['sales_date' => $date], [
            'orders_count' => $day['orders'],
            'items_count' => $day['items'],
            'revenue' => $day['revenue'],
            'cancelled_count' => $day['cancelled'],
            'breakdown' => $day['breakdown'],
            'recorded_by' => $by?->id,
            'recorded_at' => now(),
        ]);
    }
}

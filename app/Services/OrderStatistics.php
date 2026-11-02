<?php

namespace App\Services;

use App\Models\Order;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Staff and customer performance for the Analytics page, read from orders. A day is the pub's
 * business day (it runs until pricing.day_ends_at), so a range of dates covers
 * `start at the closing hour` up to `end + 1 day at the closing hour`.
 *
 * Cancelled orders never count towards sales; they are only counted on their own. Money is the
 * order total (items at the price they were charged); outstanding is what is still unpaid.
 */
class OrderStatistics
{
    /** @return array{0: Carbon, 1: Carbon} [from (inclusive), to (exclusive)] */
    public function window(string $startDate, string $endDate): array
    {
        [$hour, $minute] = array_map('intval', explode(':', (string) config('pricing.day_ends_at', '03:00')) + [0, 0]);

        return [
            Carbon::parse($startDate)->startOfDay()->setTime($hour, $minute),
            Carbon::parse($endDate)->startOfDay()->addDay()->setTime($hour, $minute),
        ];
    }

    private function orders(Carbon $from, Carbon $to)
    {
        return Order::query()->where('orders.created_at', '>=', $from)->where('orders.created_at', '<', $to);
    }

    /**
     * Everyone who took orders (the waiters), best sales first, and the bar (who prepared them).
     *
     * @return array{waiters: list<array<string, mixed>>, bar: list<array<string, mixed>>, totals: array<string, float|int>}
     */
    public function staff(Carbon $from, Carbon $to): array
    {
        $rows = $this->orders($from, $to)
            ->selectRaw("orders.taken_by, MAX(orders.taken_by_name) as name,
                SUM(CASE WHEN orders.status <> 'cancelled' THEN 1 ELSE 0 END) as orders,
                SUM(CASE WHEN orders.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                COALESCE(SUM(CASE WHEN orders.status <> 'cancelled' THEN orders.total ELSE 0 END), 0) as revenue,
                COALESCE(SUM(CASE WHEN orders.status <> 'cancelled' AND orders.payment_status = 'unpaid' THEN orders.total ELSE 0 END), 0) as outstanding")
            ->groupBy('orders.taken_by')
            ->get();

        $items = $this->orders($from, $to)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', '<>', Order::STATUS_CANCELLED)
            ->selectRaw('orders.taken_by, COALESCE(SUM(order_items.quantity), 0) as items')
            ->groupBy('orders.taken_by')
            ->pluck('items', 'taken_by');

        // Minutes from "ready" to "served" for each waiter's orders.
        $serveMinutes = $this->orders($from, $to)
            ->whereNotNull('orders.ready_at')->whereNotNull('orders.served_at')
            ->get(['orders.taken_by', 'orders.ready_at', 'orders.served_at'])
            ->groupBy('taken_by')
            ->map(fn ($group) => $group->avg(fn ($o) => max(0, $o->ready_at->diffInSeconds($o->served_at, true)) / 60));

        $totalRevenue = (float) $rows->sum('revenue');

        $waiters = $rows->map(function ($row) use ($items, $serveMinutes, $totalRevenue) {
            $orders = (int) $row->orders;
            $revenue = round((float) $row->revenue, 2);
            $minutes = $serveMinutes->get($row->taken_by);

            return [
                'user_id' => $row->taken_by,
                'name' => $row->name ?: 'Unknown',
                'orders' => $orders,
                'items' => (int) ($items->get($row->taken_by) ?? 0),
                'revenue' => $revenue,
                'average_order' => $orders > 0 ? round($revenue / $orders, 2) : 0.0,
                'share' => $totalRevenue > 0 ? round($revenue / $totalRevenue * 100, 1) : 0.0,
                'cancelled' => (int) $row->cancelled,
                'outstanding' => round((float) $row->outstanding, 2),
                'serve_minutes' => $minutes === null ? null : round($minutes, 1),
            ];
        })->sortByDesc('revenue')->values()->all();

        // The bar: each item's wait from being added to being marked ready, credited to whoever marked the order ready.
        $prep = $this->orders($from, $to)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->whereNotNull('orders.ready_by')->whereNotNull('order_items.ready_at')
            ->leftJoin('users', 'users.id', '=', 'orders.ready_by')
            ->get(['orders.ready_by', 'users.name as bartender', 'order_items.created_at as added_at', 'order_items.ready_at as item_ready_at', 'orders.id as order_id'])
            ->groupBy('ready_by');

        $bar = $prep->map(function ($group, $userId) {
            $minutes = $group->avg(fn ($r) => max(0, Carbon::parse($r->added_at)->diffInSeconds(Carbon::parse($r->item_ready_at), true)) / 60);

            return [
                'user_id' => (int) $userId,
                'name' => $group->first()->bartender ?: 'Unknown',
                'orders' => $group->pluck('order_id')->unique()->count(),
                'prep_minutes' => round($minutes, 1),
            ];
        })->sortByDesc('orders')->values()->all();

        return [
            'waiters' => $waiters,
            'bar' => $bar,
            'totals' => [
                'orders' => (int) $rows->sum('orders'),
                'revenue' => round($totalRevenue, 2),
                'cancelled' => (int) $rows->sum('cancelled'),
                'outstanding' => round((float) $rows->sum('outstanding'), 2),
            ],
        ];
    }

    /**
     * One row per customer phone: how often they came and what they spent.
     *
     * @return array{summary: array<string, float|int>, top_spenders: list<array<string, mixed>>, most_frequent: list<array<string, mixed>>}
     */
    public function customers(Carbon $from, Carbon $to, int $limit = 10): array
    {
        $rows = $this->orders($from, $to)
            ->where('orders.status', '<>', Order::STATUS_CANCELLED)
            ->selectRaw("orders.customer_phone,
                COUNT(*) as orders,
                COALESCE(SUM(orders.total), 0) as spent,
                COALESCE(SUM(CASE WHEN orders.payment_status = 'unpaid' THEN orders.total ELSE 0 END), 0) as outstanding,
                MAX(orders.created_at) as last_visit")
            ->groupBy('orders.customer_phone')
            ->get();

        $items = $this->orders($from, $to)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', '<>', Order::STATUS_CANCELLED)
            ->selectRaw('orders.customer_phone, COALESCE(SUM(order_items.quantity), 0) as items')
            ->groupBy('orders.customer_phone')
            ->pluck('items', 'customer_phone');

        // The name on their latest order (the same number can be typed with different names).
        $names = $this->orders($from, $to)
            ->where('orders.status', '<>', Order::STATUS_CANCELLED)
            ->orderBy('orders.id')
            ->pluck('customer_name', 'customer_phone');

        $customers = $rows->map(fn ($row) => [
            'phone' => Phone::local($row->customer_phone) ?? $row->customer_phone,
            'name' => $names->get($row->customer_phone) ?? 'Unknown',
            'orders' => (int) $row->orders,
            'items' => (int) ($items->get($row->customer_phone) ?? 0),
            'spent' => round((float) $row->spent, 2),
            'average_order' => $row->orders > 0 ? round((float) $row->spent / $row->orders, 2) : 0.0,
            'outstanding' => round((float) $row->outstanding, 2),
            'last_visit' => Carbon::parse($row->last_visit)->toDateString(),
        ]);

        $count = $customers->count();
        $repeat = $customers->where('orders', '>=', 2)->count();

        return [
            'summary' => [
                'customers' => $count,
                'repeat' => $repeat,
                'repeat_rate' => $count > 0 ? round($repeat / $count * 100, 1) : 0.0,
                'average_spend' => $count > 0 ? round($customers->sum('spent') / $count, 2) : 0.0,
                'outstanding' => round((float) $customers->sum('outstanding'), 2),
            ],
            'top_spenders' => $customers->sortByDesc('spent')->take($limit)->values()->all(),
            'most_frequent' => $customers->sortBy([['orders', 'desc'], ['spent', 'desc']])->take($limit)->values()->all(),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\FoodOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

/**
 * The food part of every order is saved as a food order the moment food is ordered, so the kitchen
 * dashboard and the daily food figures read from it. Food orders follow the order: pending until the
 * chef marks the food ready, ready until the waiter picks it up (serves the order), cancelled with
 * the order.
 *
 * Everything here runs inside OrderService's order lock; nothing else should change food orders.
 */
class FoodOrderService
{
    /**
     * Bring the order's food orders in step with its kitchen items: new food goes onto the order's
     * pending food order (or starts one), and every food order's total, count and status are redone.
     */
    public function sync(Order $order): void
    {
        $unattached = $order->items()
            ->where('station', OrderItem::STATION_KITCHEN)
            ->whereNull('food_order_id')
            ->get();

        if ($unattached->isNotEmpty()) {
            $food = $order->foodOrders()->where('status', FoodOrder::STATUS_PENDING)->orderByDesc('id')->first() ?? $this->start($order);

            OrderItem::whereIn('id', $unattached->pluck('id'))->update(['food_order_id' => $food->id]);
        }

        foreach ($order->foodOrders()->whereIn('status', [FoodOrder::STATUS_PENDING, FoodOrder::STATUS_READY])->get() as $food) {
            $items = $food->items()->get();

            if ($items->isEmpty()) {
                $food->delete(); // everything on it was removed before the kitchen prepared it

                continue;
            }

            $allReady = $items->every(fn (OrderItem $item) => $item->isLocked());

            $food->forceFill([
                'total' => round((float) $items->sum('line_total'), 2),
                'items_count' => (int) $items->sum('quantity'),
            ]);

            if ($allReady && $food->status === FoodOrder::STATUS_PENDING) {
                $food->status = FoodOrder::STATUS_READY;
                $food->ready_at = now();
                $food->ready_by = $items->sortByDesc('ready_at')->first()->ready_by;
            }

            $food->save();
        }
    }

    /** The waiter has picked the order up: its ready food is now picked up. */
    public function markPickedUp(Order $order, User $by): void
    {
        $order->foodOrders()->where('status', FoodOrder::STATUS_READY)->update([
            'status' => FoodOrder::STATUS_PICKED_UP,
            'picked_up_at' => now(),
            'picked_up_by' => $by->id,
        ]);
    }

    /** The order was cancelled: so is its food, whatever stage it was at. */
    public function cancelFor(Order $order): void
    {
        $order->foodOrders()->where('status', '<>', FoodOrder::STATUS_CANCELLED)->update([
            'status' => FoodOrder::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    private function start(Order $order): FoodOrder
    {
        $food = FoodOrder::create([
            'order_id' => $order->id,
            'business_date' => app(PriceService::class)->businessDate()->toDateString(),
            'customer_name' => $order->customer_name,
            'taken_by' => $order->taken_by,
            'taken_by_name' => $order->taken_by_name,
            'status' => FoodOrder::STATUS_PENDING,
        ]);

        $food->update(['code' => sprintf('FO-%04d', $food->id)]);

        return $food;
    }
}

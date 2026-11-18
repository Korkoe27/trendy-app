<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class OrderReceiptController extends Controller
{
    /**
     * A printable receipt for a paid order. Only someone who can see the order (its waiter, or a
     * manager/admin) may open it, and only once the tab has been settled.
     */
    public function show(Order $order)
    {
        abort_unless($order->canBeSeenBy(Auth::user()), 403);
        abort_unless($order->isPaid(), 404);

        $order->loadMissing('items');

        return view('receipts.order', ['order' => $order, 'isBill' => false]);
    }

    /**
     * A pre-payment bill for an open tab, so the customer can check the items and total before
     * settling. Paid orders use the receipt instead.
     */
    public function bill(Order $order)
    {
        abort_unless($order->canBeSeenBy(Auth::user()), 403);
        abort_unless(! $order->isPaid() && $order->status !== Order::STATUS_CANCELLED, 404);

        $order->loadMissing('items');

        return view('receipts.order', ['order' => $order, 'isBill' => true]);
    }
}

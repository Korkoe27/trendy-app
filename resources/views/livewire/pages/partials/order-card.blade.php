{{-- One open order on the board. Expects $order (array from OrderService::board()) and the permission flags from the Orders component. --}}
@php
    $status = $order['status'];
    // Adding items, editing and closing belong to admins, managers and the waiter who took the order.
    $manage = $canSettleAny || ($canOrder && ($order['taken_by'] ?? null) === $userId);
@endphp

<div wire:key="order-{{ $order['id'] }}-{{ $status }}" class="bg-white border border-gray-200 rounded-lg px-3 py-2.5 shadow-sm">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold text-gray-500">
                {{ $order['code'] }}
                @if ($order['is_new_round'])
                    <span class="ml-1 px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-800 text-[10px] font-bold uppercase">New items</span>
                @endif
                @if ($order['payment_pending'])
                    <span class="ml-1 px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[10px] font-bold" title="Awaiting mobile money approval">⏳ MoMo</span>
                @endif
            </p>
            <h3 class="text-sm font-bold text-gray-900 truncate">{{ $order['customer_name'] }}</h3>
        </div>
        <div class="text-right shrink-0">
            <p class="text-[10px] text-gray-500 leading-none">{{ $status === 'served' ? 'Total due' : 'Total' }}</p>
            <p class="text-base font-extrabold leading-tight {{ $status === 'served' ? 'text-red-600' : 'text-gray-900' }}">GH₵ {{ number_format($order['total'], 2) }}</p>
        </div>
    </div>

    <div class="mt-2 flex flex-wrap items-center justify-end gap-1.5">
        @if ($canCancel)
            <button wire:click="openCancelModal({{ $order['id'] }})" class="mr-auto text-xs text-gray-400 hover:text-red-600">Cancel</button>
        @endif

        <button wire:click="openViewOrder({{ $order['id'] }})" class="px-2.5 py-1.5 text-xs font-semibold text-gray-700 border border-gray-300 rounded-md bg-white hover:bg-gray-50">View</button>

        @if ($status === 'pending')
            @if ($manage)
                <button wire:click="openAddItems({{ $order['id'] }})" class="px-2.5 py-1.5 text-xs font-semibold border border-gray-300 rounded-md bg-gray-50 hover:bg-gray-100">Edit</button>
            @endif
            @if ($canReady)
                <button wire:click="markReady({{ $order['id'] }})" wire:loading.attr="disabled" class="px-2.5 py-1.5 text-xs font-semibold text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-50">Order Ready ✓</button>
            @endif
        @elseif ($status === 'ready')
            @if ($canServe)
                <button wire:click="serve({{ $order['id'] }})" wire:loading.attr="disabled" class="px-2.5 py-1.5 text-xs font-semibold text-white bg-gray-900 rounded-md hover:bg-black disabled:opacity-50">Serve to Table</button>
            @endif
            @if ($manage)
                <button wire:click="openCloseModal({{ $order['id'] }})" class="px-2.5 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-md hover:bg-red-700">Close Tab</button>
            @endif
        @else
            @if ($manage)
                <button wire:click="openAddItems({{ $order['id'] }})" class="px-2.5 py-1.5 text-xs font-semibold border border-gray-300 rounded-md bg-white hover:bg-gray-50">+ Add Items</button>
                <button wire:click="openCloseModal({{ $order['id'] }})" class="px-2.5 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-md hover:bg-red-700">Close Tab</button>
            @endif
        @endif
    </div>
</div>

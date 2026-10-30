{{-- One open order on the board. Expects $order (array from OrderService::board()) and the permission flags from the Orders component. --}}
@php
    $status = $order['status'];
    $badge = [
        'pending' => ['bg-yellow-100 text-yellow-800 border-yellow-300', 'Pending'],
        'ready' => ['bg-green-100 text-green-800 border-green-300', 'Ready'],
        'served' => ['bg-indigo-100 text-indigo-800 border-indigo-300', 'Ongoing tab'],
    ][$status];
    $ago = \Carbon\Carbon::parse($order['created_at'])->diffForHumans(null, true, true);
    // Adding items, editing and closing belong to admins, managers and the waiter who took the order.
    $manage = $canSettleAny || ($canOrder && ($order['taken_by'] ?? null) === $userId);
@endphp

<div wire:key="order-{{ $order['id'] }}-{{ $status }}" class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
    <div class="flex items-start justify-between gap-2">
        <span class="text-sm font-semibold text-gray-500">{{ $order['code'] }}</span>
        <div class="flex items-center gap-1.5">
            @if ($order['is_new_round'])
                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-red-600 text-white text-[11px] font-bold uppercase tracking-wide animate-pulse" data-testid="new-items-badge">New</span>
            @endif
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full border text-xs font-semibold {{ $badge[0] }}">{{ $badge[1] }}</span>
        </div>
    </div>

    <h3 class="mt-1 text-lg font-bold text-gray-900">{{ $order['customer_name'] }}</h3>
    @if ($order['payment_pending'])
        <p class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full">⏳ Awaiting mobile money approval</p>
    @endif
    <p class="text-sm text-gray-500">
        <a href="tel:{{ $order['customer_phone'] }}" class="font-semibold text-blue-700 hover:underline">{{ \App\Support\Phone::local($order['customer_phone']) ?? $order['customer_phone'] }}</a>
        • Waiter: {{ $order['taken_by_name'] }} • {{ $ago }} ago
    </p>

    <ul class="mt-3 pt-3 border-t border-gray-100 space-y-1">
        @foreach ($order['items'] as $item)
            @php($isNew = $order['is_new_round'] && $item['status'] === 'pending')
            <li class="flex items-center justify-between gap-2 text-sm font-medium text-gray-800 {{ $isNew ? 'bg-red-50 -mx-2 px-2 py-1 rounded-md' : '' }}">
                <span>{{ $item['quantity'] }}x {{ $item['name'] }}</span>
                @if ($isNew)
                    <span class="px-2 py-0.5 rounded-full bg-red-600 text-white text-[10px] font-bold uppercase tracking-wide" data-testid="new-item-tag">New</span>
                @endif
            </li>
        @endforeach
    </ul>

    <div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs text-gray-500">{{ $status === 'served' ? 'Total due' : 'Total' }} ({{ count($order['items']) }} {{ \Illuminate\Support\Str::plural('item', count($order['items'])) }})</p>
            <p class="text-xl font-extrabold {{ $status === 'served' ? 'text-red-600' : 'text-gray-900' }}">GH₵ {{ number_format($order['total'], 2) }}</p>
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            @if ($status === 'pending')
                @if ($manage)
                    <button wire:click="openAddItems({{ $order['id'] }})" class="px-3 py-2 text-sm font-semibold border border-gray-300 rounded-lg bg-gray-50 hover:bg-gray-100">Edit</button>
                @endif
                @if ($canReady)
                    <button wire:click="markReady({{ $order['id'] }})" wire:loading.attr="disabled" class="px-3 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50">Order Ready ✓</button>
                @endif
            @elseif ($status === 'ready')
                @if ($canServe)
                    <button wire:click="serve({{ $order['id'] }})" wire:loading.attr="disabled" class="px-3 py-2 text-sm font-semibold text-white bg-gray-900 rounded-lg hover:bg-black disabled:opacity-50">Serve to Table</button>
                @endif
                @if ($manage)
                    <button wire:click="openCloseModal({{ $order['id'] }})" class="px-3 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700">Close Tab</button>
                @endif
            @else
                @if ($manage)
                    <button wire:click="openAddItems({{ $order['id'] }})" class="px-3 py-2 text-sm font-semibold border border-gray-300 rounded-lg bg-white hover:bg-gray-50">+ Add Items</button>
                @endif
                @if ($manage)
                    <button wire:click="openCloseModal({{ $order['id'] }})" class="px-3 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700">Close Tab</button>
                @endif
            @endif
        </div>
    </div>

    @if ($canCancel)
        <div class="mt-2 text-right">
            <button wire:click="openCancelModal({{ $order['id'] }})" class="text-xs text-gray-400 hover:text-red-600">Cancel order</button>
        </div>
    @endif
</div>

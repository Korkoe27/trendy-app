{{-- Live order board. Re-renders every 4 seconds unless a modal is open. --}}
{{-- pt-14 on phones keeps the page clear of the fixed menu button --}}
<div class="space-y-6 pt-14 lg:pt-0" @unless ($modalOpen) wire:poll.4s.visible @endunless>
    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('message') }}</span>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Header -->
    <div class="bg-white rounded-lg border border-gray-200 p-4 sm:p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold text-gray-900">Orders</h2>
                    <span class="px-2 py-0.5 text-xs font-semibold text-blue-700 bg-blue-50 rounded-full">{{ explode(' ', Auth::user()->name)[0] }} (You)</span>
                </div>
                <p class="text-base text-gray-600 mt-1">Take orders, track what is being prepared and settle open tabs</p>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 lg:items-center">
                <input type="text" wire:model.live.debounce.300ms="searchTerm" placeholder="Search customer, phone or order code"
                    class="w-full sm:w-72 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />

                @if ($canOrder)
                    <button wire:click="openTakeOrder"
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-semibold flex items-center justify-center space-x-2 whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>TAKE ORDER</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Board: tabs on small screens, three columns on large -->
    @php
        $columns = [
            'pending' => ['Pending preparation', 'bg-yellow-50 border-yellow-200 text-yellow-800'],
            'ready' => ['Ready for pickup', 'bg-green-50 border-green-200 text-green-800'],
            'served' => ['Ongoing tabs / unpaid', 'bg-indigo-50 border-indigo-200 text-indigo-800'],
        ];
    @endphp

    <div class="lg:hidden grid grid-cols-3 gap-1 bg-gray-200 p-1 rounded-lg">
        @foreach ($columns as $key => [$label, $style])
            <button wire:click="$set('activeColumn', '{{ $key }}')"
                class="py-2 text-sm font-semibold rounded-md {{ $activeColumn === $key ? 'bg-white shadow text-gray-900' : 'text-gray-600' }}">
                {{ \Illuminate\Support\Str::before($label, ' ') }} ({{ count($board[$key]) }})
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        @foreach ($columns as $key => [$label, $style])
            <div class="space-y-3 {{ $activeColumn === $key ? '' : 'hidden lg:block lg:space-y-3' }}">
                <div class="hidden lg:block px-4 py-3 border rounded-lg font-bold text-sm uppercase {{ $style }}">
                    {{ $label }} ({{ count($board[$key]) }})
                </div>

                @forelse ($board[$key] as $order)
                    @include('livewire.pages.partials.order-card', ['order' => $order])
                @empty
                    <div class="bg-white border border-dashed border-gray-300 rounded-xl p-6 text-center text-sm text-gray-500">
                        @if ($searchTerm !== '') No matching orders. @else Nothing here right now. @endif
                    </div>
                @endforelse
            </div>
        @endforeach
    </div>

    <!-- Completed orders -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="p-4 sm:p-6 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Completed orders</h3>
                <p class="text-sm text-gray-500">Paid and cancelled orders</p>
            </div>
            <span class="px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">{{ $history->total() }} total</span>
        </div>

        <!-- Table (md and up) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        @foreach (['Order', 'Customer', 'Waiter', 'Items', 'Total (GH₵)', 'Payment', 'Time', 'Status', ''] as $heading)
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($history as $done)
                        <tr wire:key="done-{{ $done->id }}" wire:click="openViewOrder({{ $done->id }})" class="hover:bg-gray-50 cursor-pointer">
                            <td class="px-6 py-4 text-sm font-bold text-gray-900 whitespace-nowrap">{{ $done->code }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">{{ $done->customer_name }}</div>
                                <div class="text-xs text-gray-500">{{ \App\Support\Phone::mask($done->customer_phone) }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap">{{ $done->taken_by_name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $done->items->sum('quantity') }} items
                                <span class="text-gray-400">({{ $done->items->take(2)->map(fn ($i) => $i->quantity.' '.$i->product_name)->join(', ') }}{{ $done->items->count() > 2 ? '…' : '' }})</span>
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-gray-900 whitespace-nowrap">{{ number_format((float) $done->total, 2) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($done->payment_method)
                                    <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $done->payment_method === 'momo' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700' }}">{{ $done->payment_method === 'momo' ? 'MoMo' : 'Cash' }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">{{ ($done->closed_at ?? $done->cancelled_at ?? $done->updated_at)->format('M j, H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($done->status === 'closed')
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">✓ Paid</span>
                                @else
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700" title="{{ $done->cancel_reason }}">Cancelled</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <button type="button" wire:click.stop="openViewOrder({{ $done->id }})" class="px-3 py-1.5 text-sm font-semibold text-blue-700 border border-blue-200 rounded-lg hover:bg-blue-50">View</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-6 py-10 text-center text-sm text-gray-500">No completed orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Cards (small screens) -->
        <div class="md:hidden divide-y divide-gray-100">
            @forelse ($history as $done)
                <div wire:key="done-m-{{ $done->id }}" wire:click="openViewOrder({{ $done->id }})" class="p-4 flex items-start justify-between gap-3 cursor-pointer active:bg-gray-50">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $done->customer_name }}</p>
                        <p class="text-xs text-gray-500">{{ $done->code }} • {{ $done->items->sum('quantity') }} items • {{ $done->payment_method ? ucfirst($done->payment_method === 'momo' ? 'MoMo' : 'Cash') : '—' }}</p>
                        <p class="text-xs text-gray-400">{{ ($done->closed_at ?? $done->cancelled_at ?? $done->updated_at)->format('M j, H:i') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-gray-900">GH₵ {{ number_format((float) $done->total, 2) }}</p>
                        <span class="text-xs font-semibold {{ $done->status === 'closed' ? 'text-green-700' : 'text-red-700' }}">{{ $done->status === 'closed' ? '✓ Paid' : 'Cancelled' }}</span>
                        <p class="text-xs font-semibold text-blue-700 mt-1">View ›</p>
                    </div>
                </div>
            @empty
                <p class="p-6 text-center text-sm text-gray-500">No completed orders yet.</p>
            @endforelse
        </div>

        @if ($history->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">{{ $history->links() }}</div>
        @endif
    </div>

    <!-- Take order / add items modal -->
    @if ($showOrderModal)
        <div class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 sm:p-4">
            <div class="bg-white rounded-t-2xl sm:rounded-lg w-full max-w-5xl max-h-[95vh] sm:max-h-[90vh] flex flex-col">
                <div class="p-4 sm:p-6 border-b flex items-start justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900">
                            @if ($editingOrder) Add items to {{ $editingOrder->code }} @else Take New Order @endif
                        </h3>
                        <p class="text-sm text-gray-500">
                            @if ($editingOrder) New items go to the bartender. Items already prepared are locked. @else Enter the customer's details, then add drinks or food to the cart @endif
                        </p>
                    </div>
                    <button wire:click="closeOrderModal" class="text-gray-400 hover:text-gray-600" aria-label="Close">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="overflow-y-auto flex-1 p-4 sm:p-6 space-y-5">
                    <!-- Customer -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Customer name <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="customerName" @if ($editingOrder) readonly @endif placeholder="Any name for the customer"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 {{ $editingOrder ? 'bg-gray-100 text-gray-600' : '' }}" />
                            @error('customerName') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Phone number (payment &amp; tab ID) <span class="text-red-500">*</span></label>
                            <input type="tel" inputmode="tel" wire:model.live.debounce.500ms="customerPhone" @if ($editingOrder) readonly @endif placeholder="024 123 4567"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 {{ $editingOrder ? 'bg-gray-100 text-gray-600' : '' }}" />
                            @error('customerPhone') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @if ($existingTab && $existingTabBlocked)
                        <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg px-4 py-3">
                            This customer already has an open tab with another waiter, so you cannot add to it here.
                            Ask a manager or an admin to take the order.
                        </div>
                    @elseif ($existingTab)
                        <div class="bg-indigo-50 border border-indigo-200 text-indigo-800 text-sm rounded-lg px-4 py-3">
                            <span class="font-semibold">{{ $existingTab->customer_name }}</span> already has an open tab
                            <span class="font-semibold">{{ $existingTab->code }}</span> (GH₵ {{ number_format((float) $existingTab->total, 2) }}).
                            These items will be added to it, so they pay once.
                        </div>
                    @endif

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Product picker -->
                        <div class="lg:col-span-2 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <button wire:click="$set('categoryFilter', 'all')" class="px-4 py-1.5 text-sm font-semibold rounded-full {{ $categoryFilter === 'all' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">All</button>
                                @foreach ($categories as $category)
                                    <button wire:click="$set('categoryFilter', '{{ $category->id }}')" class="px-4 py-1.5 text-sm font-semibold rounded-full capitalize {{ (string) $categoryFilter === (string) $category->id ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ $category->name }}</button>
                                @endforeach
                                <input type="text" wire:model.live.debounce.250ms="productSearch" placeholder="Search items"
                                    class="ml-auto w-full sm:w-48 px-3 py-1.5 text-sm border border-gray-300 rounded-lg" />
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-80 overflow-y-auto pr-1">
                                @forelse ($products as $product)
                                    @php $inCart = $cart[$product->id] ?? 0; @endphp
                                    <button type="button" wire:key="p-{{ $product->id }}" wire:click="addToCart({{ $product->id }})"
                                        class="relative uppercase text-left p-3 border-2 rounded-lg transition {{ $inCart ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-gray-300 bg-white' }}">
                                        @if ($inCart)
                                            <span class="absolute top-2 right-2 w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">{{ $inCart }}</span>
                                        @endif
                                        <p class="font-bold text-gray-900 pr-6 leading-tight">{{ $product->name }}</p>
                                        <p class="text-xs text-gray-500 capitalize">{{ $product->category?->name }}</p>
                                        @php $quote = $quotes[$product->id] ?? null; @endphp
                                        @if ($quote && $quote['event'])
                                            <p class="mt-1 font-bold {{ $quote['increase'] ? 'text-amber-700' : 'text-green-700' }}">
                                                GH₵ {{ number_format($quote['price'], 2) }}
                                                <span class="text-xs font-normal text-gray-400 line-through normal-case">{{ number_format($quote['base'], 2) }}</span>
                                            </p>
                                            <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $quote['increase'] ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">VIP {{ $quote['label'] }}</span>
                                        @else
                                            <p class="mt-1 font-bold text-blue-700">GH₵ {{ number_format((float) $product->selling_price, 2) }}</p>
                                        @endif
                                    </button>
                                @empty
                                    <p class="col-span-full text-sm text-gray-500 py-6 text-center">No items match.</p>
                                @endforelse
                            </div>
                        </div>

                        <!-- Cart -->
                        <div class="lg:border-l lg:pl-6 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-gray-900 uppercase text-sm">{{ $editingOrder ? 'Order' : 'Order cart' }}</h4>
                                <span class="text-xs font-semibold text-blue-700 bg-blue-50 rounded-full px-2 py-0.5">{{ array_sum($cart) }} new {{ \Illuminate\Support\Str::plural('item', array_sum($cart)) }}</span>
                            </div>

                            @if ($editingOrder)
                                <div class="space-y-2">
                                    @foreach ($editingOrder->items as $item)
                                        <div wire:key="existing-{{ $item->id }}" class="flex items-center justify-between gap-2 p-2 rounded-lg bg-gray-50 border border-gray-200">
                                            <div class="text-sm">
                                                <p class="font-semibold text-gray-800">{{ $item->product_name }}</p>
                                                <p class="text-xs text-gray-500">GH₵ {{ number_format((float) $item->line_total, 2) }} (@ {{ number_format((float) $item->unit_price, 2) }})</p>
                                            </div>
                                            @if ($item->isLocked())
                                                <span class="px-2 py-1 text-xs font-semibold text-gray-500 bg-gray-200 rounded">{{ $item->quantity }}x Locked</span>
                                            @else
                                                <div class="flex items-center gap-1 bg-white border rounded-lg">
                                                    <button type="button" wire:click="changeItemQuantity({{ $item->id }}, {{ $item->quantity - 1 }})" class="px-2 py-1 text-gray-600 hover:bg-gray-100 rounded-l-lg">−</button>
                                                    <span class="px-2 text-sm font-bold">{{ $item->quantity }}</span>
                                                    <button type="button" wire:click="changeItemQuantity({{ $item->id }}, {{ $item->quantity + 1 }})" class="px-2 py-1 text-blue-600 hover:bg-gray-100 rounded-r-lg">+</button>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                <p class="text-xs uppercase font-semibold text-gray-500 pt-1">Adding now</p>
                            @endif

                            <div class="space-y-2">
                                @forelse ($cartLines as $line)
                                    <div wire:key="cart-{{ $line['id'] }}" class="flex items-center justify-between gap-2">
                                        <div class="text-sm">
                                            <p class="font-semibold text-gray-900">{{ $line['name'] }}</p>
                                            <p class="text-xs text-blue-700 font-semibold">GH₵ {{ number_format($line['total'], 2) }} <span class="text-gray-400">(@ {{ number_format($line['price'], 2) }})</span>@if ($line['event']) <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $line['increase'] ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">VIP {{ $line['label'] }}</span>@endif</p>
                                        </div>
                                        <div class="flex items-center gap-1 bg-gray-100 rounded-lg">
                                            <button type="button" wire:click="decrementCart({{ $line['id'] }})" class="px-2.5 py-1 text-gray-600 hover:bg-gray-200 rounded-l-lg">−</button>
                                            <span class="px-2 text-sm font-bold">{{ $line['quantity'] }}</span>
                                            <button type="button" wire:click="addToCart({{ $line['id'] }})" class="px-2.5 py-1 text-blue-600 hover:bg-gray-200 rounded-r-lg">+</button>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-400 py-4 text-center">Tap an item to add it.</p>
                                @endforelse
                            </div>

                            <div class="pt-3 border-t space-y-1 text-sm">
                                @if ($editingOrder)
                                    <div class="flex justify-between text-gray-600"><span>Current total</span><span>GH₵ {{ number_format((float) $editingOrder->total, 2) }}</span></div>
                                    <div class="flex justify-between text-gray-600"><span>Adding</span><span>GH₵ {{ number_format($cartTotal, 2) }}</span></div>
                                @endif
                                <div class="flex justify-between items-center pt-1">
                                    <span class="font-bold text-gray-900 uppercase">Total due</span>
                                    <span class="text-2xl font-extrabold text-blue-700">GH₵ {{ number_format(($editingOrder ? (float) $editingOrder->total : 0) + $cartTotal, 2) }}</span>
                                </div>
                            </div>

                            @error('cart') <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-3 py-2">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-6 border-t flex items-center justify-end gap-3">
                    <button wire:click="closeOrderModal" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button wire:click="saveOrder" wire:loading.attr="disabled" @disabled(empty($cart) || $existingTabBlocked)
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold disabled:opacity-40 disabled:cursor-not-allowed">
                        {{ $editingOrder ? 'ADD TO ORDER' : ($existingTab ? 'ADD TO TAB' : 'PLACE ORDER') }} (GH₵ {{ number_format($cartTotal, 0) }})
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Close tab modal -->
    @if ($showCloseModal && $closingOrder)
        <div class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 sm:p-4">
            <div class="bg-white rounded-t-2xl sm:rounded-lg w-full max-w-lg max-h-[95vh] overflow-y-auto"
                @if ($activePayment) wire:poll.5s="checkPayment" @endif>
                <div class="p-4 sm:p-6 border-b flex items-start justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900">Close Order &amp; Settle Tab</h3>
                        <p class="text-sm text-gray-500">{{ $closingOrder->code }} • Customer: {{ $closingOrder->customer_name }}</p>
                    </div>
                    <button wire:click="closeCloseModal" class="text-gray-400 hover:text-gray-600" aria-label="Close">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-4 sm:p-6 space-y-4">
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-red-700 uppercase">Total unpaid tab due</p>
                            <p class="text-3xl font-extrabold text-red-600">GH₵ {{ number_format((float) $closingOrder->total, 2) }}</p>
                        </div>
                        <p class="text-sm font-semibold text-red-700">{{ $closingOrder->items->sum('quantity') }} items ordered</p>
                    </div>

                    @if ($closingOrder->status === 'pending')
                        <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 text-sm rounded-lg px-3 py-2">
                            The bartender has not marked this order ready yet. Close the tab once it is ready.
                        </div>
                    @endif

                    @if ($activePayment)
                        {{-- A mobile-money prompt is out: wait for the customer --}}
                        <div class="border-2 border-blue-300 bg-blue-50 rounded-xl p-4 space-y-3">
                            <div class="flex items-center gap-3">
                                <svg class="animate-spin w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                <div>
                                    <p class="font-bold text-blue-900">Waiting for the customer to approve</p>
                                    <p class="text-sm text-blue-800">A prompt for GH₵ {{ number_format((float) $activePayment->amount, 2) }} was sent to {{ \App\Support\Phone::local($activePayment->phone) }}. This closes automatically once it is paid.</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button wire:click="checkPayment" class="px-4 py-2 text-sm font-semibold border border-blue-300 bg-white rounded-lg hover:bg-blue-100">Check status</button>
                                <button wire:click="cancelPayment" wire:confirm="Stop waiting for this payment? If the customer approves it later, the money will still be recorded." class="px-4 py-2 text-sm font-semibold text-red-700 border border-red-200 bg-white rounded-lg hover:bg-red-50">Cancel attempt</button>
                            </div>
                        </div>
                    @else
                        <p class="text-xs font-bold text-gray-600 uppercase">Select settlement method</p>

                        <!-- Mobile money: greyed out until HUBTEL_PAYMENTS_ENABLED is on and configured -->
                        <div class="relative border-2 rounded-xl p-4 {{ $momoEnabled && $paymentMethod === 'momo' ? 'border-blue-600 bg-blue-50' : 'border-gray-200' }} {{ $momoEnabled ? 'cursor-pointer' : 'opacity-60 select-none' }}"
                            @if ($momoEnabled) wire:click="$set('paymentMethod', 'momo')" @else aria-disabled="true" @endif>
                            <div class="{{ $momoEnabled ? '' : 'blur-[1.5px]' }}">
                                <p class="font-bold text-gray-900">Pay via Mobile Money (phone prompt)</p>
                                <p class="text-sm text-gray-500">Sends a payment prompt directly to the customer's phone</p>

                                @if ($momoEnabled && $paymentMethod === 'momo')
                                    <label class="block text-xs font-semibold text-gray-600 uppercase mt-3 mb-1">Number to charge</label>
                                    <input type="tel" inputmode="tel" wire:model="momoPhone" wire:click.stop
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500" placeholder="024 123 4567" />
                                @else
                                    <p class="mt-2 text-sm font-semibold text-gray-700">{{ \App\Support\Phone::local($closingOrder->customer_phone) }}</p>
                                @endif
                            </div>
                            @unless ($momoEnabled)
                                <span class="absolute top-3 right-3 px-2 py-0.5 text-xs font-bold text-gray-600 bg-gray-200 rounded-full">{{ $momoSwitchedOn ? 'Not set up' : 'Coming soon' }}</span>
                            @endunless
                        </div>

                        <!-- Cash: managers and admins only -->
                        @unless ($canTakeCash)
                            <div class="flex items-center justify-between gap-3 border-2 border-gray-200 rounded-xl p-4 opacity-60 select-none" aria-disabled="true">
                                <div>
                                    <p class="font-bold text-gray-900">Cash settlement</p>
                                    <p class="text-sm text-gray-500">Only a manager or an admin can close a tab with cash</p>
                                </div>
                                <span class="text-xs font-bold text-gray-600 bg-gray-200 rounded-full px-2 py-0.5">Managers only</span>
                            </div>
                        @else
                        <label class="flex items-center justify-between gap-3 border-2 rounded-xl p-4 cursor-pointer {{ $paymentMethod === 'cash' ? 'border-blue-600 bg-blue-50' : 'border-gray-200' }}">
                            <div class="flex items-center gap-3">
                                <input type="radio" wire:model.live="paymentMethod" value="cash" class="text-blue-600" />
                                <div>
                                    <p class="font-bold text-gray-900">Cash settlement</p>
                                    <p class="text-sm text-gray-500">Collect cash now and mark the tab as settled &amp; closed</p>
                                </div>
                            </div>
                            <span class="text-xs font-bold text-green-700">Instant clear</span>
                        </label>
                        @endunless
                    @endif

                    @error('paymentMethod') <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-3 py-2">{{ $message }}</div> @enderror
                </div>

                <div class="p-4 sm:p-6 border-t flex items-center justify-between gap-3">
                    <button wire:click="closeCloseModal" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">{{ $activePayment ? 'Close window' : 'Cancel' }}</button>

                    @unless ($activePayment)
                        @if ($momoEnabled && $paymentMethod === 'momo')
                            <button wire:click="startMobileMoney" wire:loading.attr="disabled" @disabled($closingOrder->status === 'pending')
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-bold disabled:opacity-40 disabled:cursor-not-allowed">
                                SEND PAYMENT PROMPT (GH₵ {{ number_format((float) $closingOrder->total, 0) }})
                            </button>
                        @elseif (! $canTakeCash)
                            <p class="text-sm text-gray-500 text-right max-w-xs">Mobile money is not available yet. Ask a manager or an admin to close this tab with cash.</p>
                        @else
                            <button wire:click="confirmClose" wire:loading.attr="disabled" @disabled($closingOrder->status === 'pending')
                                class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-bold disabled:opacity-40 disabled:cursor-not-allowed">
                                COLLECT CASH &amp; CLOSE (GH₵ {{ number_format((float) $closingOrder->total, 0) }})
                            </button>
                        @endif
                    @endunless
                </div>
            </div>
        </div>
    @endif

    <!-- View order modal (read-only) -->
    @if ($showViewModal && $viewingOrder)
        @php
            $paid = $viewingOrder->status === 'closed';
            $events = array_filter([
                ['Placed', $viewingOrder->created_at, $viewingOrder->taken_by_name],
                ['Marked ready', $viewingOrder->ready_at, null],
                ['Served', $viewingOrder->served_at, null],
                ['Paid', $viewingOrder->paid_at, $viewingOrder->closed_by_name],
                ['Cancelled', $viewingOrder->cancelled_at, $viewingCancelledBy],
            ], fn ($e) => $e[1]);
        @endphp
        <div class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 sm:p-4" wire:click.self="closeViewModal">
            <div class="bg-white rounded-t-2xl sm:rounded-lg w-full max-w-lg max-h-[95vh] overflow-y-auto">
                <div class="p-4 sm:p-6 border-b flex items-start justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900">{{ $viewingOrder->code }}</h3>
                        <p class="text-sm text-gray-500">{{ $viewingOrder->customer_name }} • {{ \App\Support\Phone::mask($viewingOrder->customer_phone) }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $paid ? 'bg-green-100 text-green-700' : ($viewingOrder->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-800') }}">
                            {{ $paid ? '✓ Paid' : ucfirst($viewingOrder->status) }}
                        </span>
                        <button wire:click="closeViewModal" class="text-gray-400 hover:text-gray-600" aria-label="Close">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-6 space-y-5">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Items</p>
                        <ul class="divide-y divide-gray-100 border border-gray-200 rounded-lg">
                            @foreach ($viewingOrder->items as $item)
                                <li wire:key="view-item-{{ $item->id }}" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $item->quantity }}x {{ $item->product_name }}</p>
                                        <p class="text-xs text-gray-500">@ GH₵ {{ number_format((float) $item->unit_price, 2) }}</p>
                                    </div>
                                    <span class="font-semibold text-gray-900">GH₵ {{ number_format((float) $item->line_total, 2) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="flex justify-between items-center pt-3">
                            <span class="font-bold text-gray-900 uppercase text-sm">Total</span>
                            <span class="text-2xl font-extrabold {{ $viewingOrder->status === 'cancelled' ? 'text-gray-400 line-through' : 'text-blue-700' }}">GH₵ {{ number_format((float) $viewingOrder->total, 2) }}</span>
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-xs font-semibold text-gray-500 uppercase">Payment</dt><dd class="text-gray-900">{{ $viewingOrder->payment_method ? ($viewingOrder->payment_method === 'momo' ? 'Mobile money' : 'Cash') : '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold text-gray-500 uppercase">Waiter</dt><dd class="text-gray-900">{{ $viewingOrder->taken_by_name }}</dd></div>
                    </dl>

                    @if ($viewingOrder->notes)
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Notes</p>
                            <p class="text-sm text-gray-800 whitespace-pre-line">{{ $viewingOrder->notes }}</p>
                        </div>
                    @endif

                    @if ($viewingOrder->status === 'cancelled' && $viewingOrder->cancel_reason)
                        <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-3 py-2">
                            <span class="font-semibold">Cancelled:</span> {{ $viewingOrder->cancel_reason }}
                        </div>
                    @endif

                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Timeline</p>
                        <ol class="space-y-1 text-sm text-gray-700">
                            @foreach ($events as [$label, $when, $who])
                                <li class="flex justify-between gap-3">
                                    <span>{{ $label }}@if ($who) <span class="text-gray-400">by {{ $who }}</span>@endif</span>
                                    <span class="text-gray-500 whitespace-nowrap">{{ $when->format('M j, H:i') }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>

                <div class="p-4 sm:p-6 border-t flex justify-end">
                    <button wire:click="closeViewModal" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Close</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Cancel modal -->
    @if ($showCancelModal && $cancellingOrder)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg w-full max-w-md p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Cancel {{ $cancellingOrder->code }}?</h3>
                <p class="text-sm text-gray-600">{{ $cancellingOrder->customer_name }} • GH₵ {{ number_format((float) $cancellingOrder->total, 2) }}. A cancelled order cannot be reopened.</p>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason <span class="text-red-500">*</span></label>
                    <textarea wire:model="cancelReason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500" placeholder="e.g. Customer left before it was served"></textarea>
                    @error('cancelReason') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <button wire:click="closeCancelModal" class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Keep order</button>
                    <button wire:click="confirmCancel" class="px-5 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold">Cancel order</button>
                </div>
            </div>
        </div>
    @endif
</div>

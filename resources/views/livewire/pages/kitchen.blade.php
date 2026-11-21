{{-- The kitchen: food orders to prepare, the foodstuff list and the chef's shopping lists. Refreshes every 4 seconds unless a window is open. --}}
<div class="space-y-6 pt-14 lg:pt-0" @unless ($modalOpen) wire:poll.4s.visible @endunless
    x-data x-on:open-whatsapp.window="window.open($event.detail.url, '_blank')">
    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded" role="alert">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg border border-gray-200 p-4 sm:p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Kitchen</h2>
                <p class="text-base text-gray-600 mt-1">Food orders from the waiters appear here. Mark them ready when they are cooked; the waiter picks them up.</p>
                <p class="text-sm text-gray-500 mt-1">Session of {{ \Carbon\Carbon::parse($date)->format('D j M') }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <button wire:click="openFoodstuff" data-testid="open-foodstuff"
                    class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-gray-800 font-semibold hover:bg-gray-50">Foodstuff list</button>
                @if ($canCreate)
                    <button wire:click="openList" data-testid="new-shopping-list"
                        class="px-4 py-2 bg-orange-600 text-white rounded-lg font-semibold hover:bg-orange-700">New shopping list</button>
                @endif
            </div>
        </div>
    </div>

    {{-- Food orders --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        <div class="space-y-3">
            <div class="px-4 py-3 border rounded-lg font-bold text-sm uppercase bg-yellow-50 border-yellow-200 text-yellow-800">To cook ({{ $pending->count() }})</div>
            @forelse ($pending as $food)
                <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm" wire:key="food-pending-{{ $food->id }}" data-testid="food-order-pending">
                    <div class="flex justify-between gap-2">
                        <span class="text-sm font-semibold text-gray-500">{{ $food->code }} · {{ $food->order?->code }}</span>
                        <span class="text-xs text-gray-500">{{ $food->created_at->diffForHumans(null, true, true) }} ago</span>
                    </div>
                    <p class="mt-1 text-lg font-bold text-gray-900">{{ $food->customer_name }}</p>
                    <p class="text-sm text-gray-500">Waiter: {{ $food->taken_by_name }}</p>
                    <ul class="mt-3 pt-3 border-t border-gray-100 space-y-1">
                        @foreach ($food->items as $item)
                            <li class="text-sm font-medium text-gray-800">{{ $item->quantity }}x {{ $item->product_name }}</li>
                        @endforeach
                    </ul>
                    @if ($food->order?->notes)
                        <p class="mt-2 text-xs text-gray-600 bg-gray-50 rounded px-2 py-1">{{ $food->order->notes }}</p>
                    @endif
                    @if ($canReady)
                        <button wire:click="markReady({{ $food->id }})" wire:loading.attr="disabled"
                            class="mt-3 w-full px-3 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50">Food Ready ✓</button>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-500 px-1">Nothing to cook.</p>
            @endforelse
        </div>

        <div class="space-y-3">
            <div class="px-4 py-3 border rounded-lg font-bold text-sm uppercase bg-green-50 border-green-200 text-green-800">Ready for pickup ({{ $ready->count() }})</div>
            @forelse ($ready as $food)
                <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm" wire:key="food-ready-{{ $food->id }}" data-testid="food-order-ready">
                    <span class="text-sm font-semibold text-gray-500">{{ $food->code }} · {{ $food->order?->code }}</span>
                    <p class="mt-1 text-lg font-bold text-gray-900">{{ $food->customer_name }}</p>
                    <p class="text-sm text-gray-500">Waiter: {{ $food->taken_by_name }} · ready {{ $food->ready_at?->diffForHumans(null, true, true) }} ago</p>
                    <p class="mt-2 text-sm text-gray-700">{{ $food->items->map(fn ($i) => $i->quantity.'x '.$i->product_name)->implode(', ') }}</p>
                    <p class="mt-2 text-xs font-semibold text-green-700">Waiting for the waiter to pick it up</p>
                </div>
            @empty
                <p class="text-sm text-gray-500 px-1">Nothing waiting for pickup.</p>
            @endforelse
        </div>

        <div class="space-y-3">
            <div class="px-4 py-3 border rounded-lg font-bold text-sm uppercase bg-gray-50 border-gray-200 text-gray-700">Picked up tonight ({{ $pickedUp->count() }})</div>
            @forelse ($pickedUp as $food)
                <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-sm opacity-80" wire:key="food-done-{{ $food->id }}">
                    <p class="font-semibold text-gray-900">{{ $food->customer_name }} <span class="text-xs text-gray-400">{{ $food->code }}</span></p>
                    <p class="text-sm text-gray-600">{{ $food->items->map(fn ($i) => $i->quantity.'x '.$i->product_name)->implode(', ') }}</p>
                    <p class="text-xs text-gray-400">GH₵ {{ number_format((float) $food->total, 2) }} · picked up {{ $food->picked_up_at?->format('H:i') }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500 px-1">Nothing picked up yet.</p>
            @endforelse
        </div>
    </div>

    {{-- Shopping lists --}}
    <div class="bg-white rounded-lg border border-gray-200 p-4 sm:p-6" data-testid="shopping-lists">
        <h3 class="text-base font-semibold text-gray-900">Shopping lists</h3>
        <p class="text-sm text-gray-500 mt-1">The chef makes a list before going to the market. When the items are bought, the accountant or an admin marks it as bought and it is recorded on the spending dashboard.</p>

        <div class="mt-4 space-y-3">
            @forelse ($openLists as $list)
                <div class="border border-orange-200 bg-orange-50 rounded-lg p-4" wire:key="list-{{ $list->id }}" data-testid="shopping-list">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $list->code }} <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">To buy</span></p>
                            <p class="text-xs text-gray-500">{{ $list->created_by_name }} · {{ $list->created_at->format('D j M, H:i') }}{{ $list->supplier ? ' · '.$list->supplier : '' }}</p>
                        </div>
                        <p class="text-lg font-bold text-gray-900">GH₵ {{ number_format((float) $list->total_cost, 2) }}</p>
                    </div>
                    <ul class="mt-2 text-sm text-gray-700 divide-y divide-orange-100">
                        @foreach ($list->items as $item)
                            <li class="flex justify-between gap-3 py-1">
                                <span>{{ $item->item_name }} @if ($item->quantity !== null)<span class="text-gray-500">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}{{ $item->unit ? ' '.$item->unit : '' }} @ {{ number_format((float) $item->cost, 2) }}</span>@endif</span>
                                <span class="font-semibold">GH₵ {{ number_format((float) $item->line_total, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($list->notes)<p class="mt-2 text-xs text-gray-600">{{ $list->notes }}</p>@endif
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button wire:click="sendWhatsapp({{ $list->id }})" data-testid="send-whatsapp"
                            class="px-3 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700">Send on WhatsApp</button>
                        @if ($canCreate)
                            <button wire:click="editList({{ $list->id }})" data-testid="edit-list"
                                class="px-3 py-2 text-sm font-semibold text-gray-900 border border-gray-300 rounded-lg hover:bg-gray-50">Edit</button>
                        @endif
                        @if ($canBuy)
                            <button wire:click="openBought({{ $list->id }})" data-testid="mark-bought"
                                class="px-3 py-2 text-sm font-semibold text-white bg-gray-900 rounded-lg hover:bg-black">Mark as bought</button>
                        @endif
                        @if ($canCancel)
                            <button wire:click="cancelList({{ $list->id }})" wire:confirm="Cancel this shopping list?"
                                class="px-3 py-2 text-sm font-semibold text-red-700 border border-red-200 rounded-lg hover:bg-red-50">Cancel</button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">No shopping list waiting to be bought.</p>
            @endforelse
        </div>

        @if ($pastLists->isNotEmpty())
            <h4 class="mt-6 mb-2 text-sm font-semibold text-gray-900 uppercase">Recent lists</h4>
            <div class="divide-y divide-gray-100 text-sm">
                @foreach ($pastLists as $list)
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="past-list-{{ $list->id }}">
                        <span><span class="font-semibold text-gray-900">{{ $list->code }}</span>
                            <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold {{ $list->status === 'bought' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ ucfirst($list->status) }}</span>
                            <span class="text-gray-500">{{ $list->status === 'bought' ? 'by '.$list->bought_by_name.' · '.$list->bought_at?->format('D j M') : '' }}</span></span>
                        <span class="font-semibold text-gray-800">GH₵ {{ number_format((float) $list->total_cost, 2) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Foodstuff list --}}
    @if ($showFoodstuff)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4 py-6" role="dialog" aria-modal="true" data-testid="foodstuff-modal">
            <div class="bg-white rounded-lg p-4 sm:p-6 w-full max-w-xl max-h-full flex flex-col">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Foodstuff list</h3>
                        <p class="text-sm text-gray-500">What the kitchen buys regularly. These are the items you choose from when making a shopping list.</p>
                    </div>
                    <button wire:click="closeFoodstuff" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Close</button>
                </div>

                @if ($canCreate)
                    <form wire:submit="addFoodstuff" class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <input type="text" wire:model="foodstuffName" placeholder="e.g. Tomatoes" maxlength="120" aria-label="Item name" class="sm:col-span-3 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-400" />
                        <input type="text" wire:model="foodstuffUnit" placeholder="Unit (kg, bag, piece)" maxlength="30" aria-label="Unit" class="px-3 py-2 border border-gray-300 rounded-lg" />
                        <input type="text" wire:model="foodstuffSupplier" placeholder="Usual supplier (optional)" maxlength="255" aria-label="Supplier" class="px-3 py-2 border border-gray-300 rounded-lg" />
                        <button type="submit" class="px-3 py-2 bg-orange-600 text-white rounded-lg font-semibold hover:bg-orange-700">Add item</button>
                    </form>
                    @error('foodstuffName') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                @endif

                <div class="mt-4 overflow-y-auto divide-y divide-gray-100 border border-gray-200 rounded-lg">
                    @forelse ($foodstuff as $item)
                        <div class="px-3 py-2 text-sm {{ $editingFoodstuffId === $item->id ? 'bg-orange-50' : '' }}" wire:key="foodstuff-{{ $item->id }}">
                            @if ($editingFoodstuffId === $item->id)
                                <div class="space-y-2">
                                    <input type="text" wire:model="editFoodstuffName" maxlength="120" aria-label="Item name"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-400" />
                                    @error('editFoodstuffName') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <input type="text" wire:model="editFoodstuffUnit" maxlength="30" aria-label="Unit" placeholder="Unit (kg, bag, piece)"
                                            class="px-3 py-2 border border-gray-300 rounded-lg" />
                                        <input type="text" wire:model="editFoodstuffSupplier" maxlength="255" aria-label="Supplier" placeholder="Usual supplier (optional)"
                                            class="px-3 py-2 border border-gray-300 rounded-lg" />
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="cancelEditFoodstuff"
                                            class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                                        <button type="button" wire:click="saveFoodstuff" data-testid="save-foodstuff"
                                            class="px-3 py-2 bg-orange-600 text-white rounded-lg font-semibold hover:bg-orange-700">Save</button>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-semibold text-gray-900">{{ $item->name }}</span>
                                    <span class="flex items-center gap-3 text-gray-500">
                                        {{ $item->unit ?: '—' }}{{ $item->supplier ? ' · '.$item->supplier : '' }}
                                        @if ($canCreate)
                                            <button wire:click="editFoodstuff({{ $item->id }})" data-testid="edit-foodstuff"
                                                class="text-xs font-semibold text-orange-700 underline decoration-dotted underline-offset-2 hover:text-orange-900">Edit</button>
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="px-3 py-6 text-center text-sm text-gray-500">No items yet. Add the things the kitchen regularly buys.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    {{-- New shopping list --}}
    @if ($showListModal)
        <div class="fixed inset-0 bg-black/50 flex items-start sm:items-center justify-center z-50 overflow-y-auto px-4 py-6" role="dialog" aria-modal="true" data-testid="list-modal">
            <div class="bg-white rounded-lg p-4 sm:p-6 w-full max-w-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $editingListId ? 'Edit shopping list' : 'New shopping list' }}</h3>
                        <p class="text-sm text-gray-500">{{ $editingListId ? 'Change the items, quantities and prices, then save to re-send the updated list on WhatsApp.' : 'Choose what you are buying and its price. Add a quantity only when the item is priced per unit (the price is then for one); leave it blank to enter one price for the item. When you submit, WhatsApp opens with the list so you can send it.' }}</p>
                    </div>
                    <button wire:click="closeList" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Close</button>
                </div>

                <form wire:submit="submitList" class="mt-4 space-y-3">
                    @php $listTotal = collect($lines)->sum(fn ($l) => is_numeric($l['cost'] ?? null) ? (float) $l['cost'] * (is_numeric($l['quantity'] ?? null) && (float) $l['quantity'] > 0 ? (float) $l['quantity'] : 1) : 0); @endphp
                    <div class="space-y-2">
                        @foreach ($lines as $i => $line)
                            <div class="grid grid-cols-12 gap-2 items-center" wire:key="line-{{ $i }}">
                                <select wire:model="lines.{{ $i }}.foodstuff_id" aria-label="Item" class="col-span-12 sm:col-span-5 px-2 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Choose an item</option>
                                    @foreach ($foodstuff as $item)
                                        <option value="{{ $item->id }}">{{ $item->name }}{{ $item->unit ? ' ('.$item->unit.')' : '' }}</option>
                                    @endforeach
                                </select>
                                <input type="text" inputmode="decimal" wire:model.live.debounce.300ms="lines.{{ $i }}.quantity" aria-label="Quantity (optional)" placeholder="Qty (optional)"
                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '');" class="col-span-4 sm:col-span-2 px-2 py-2 border border-gray-300 rounded-lg text-right" />
                                <input type="text" inputmode="decimal" wire:model.live.debounce.300ms="lines.{{ $i }}.cost" aria-label="Price" placeholder="{{ is_numeric($line['quantity'] ?? null) && (float) $line['quantity'] > 0 ? 'Price of one (GH₵)' : 'Price (GH₵)' }}"
                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '');" class="col-span-5 sm:col-span-3 px-2 py-2 border border-gray-300 rounded-lg text-right" />
                                <button type="button" wire:click="removeLine({{ $i }})" class="col-span-3 sm:col-span-2 px-2 py-2 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50" aria-label="Remove item">Remove</button>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addLine" class="px-3 py-2 text-sm font-semibold border border-gray-300 rounded-lg hover:bg-gray-50">+ Add another item</button>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input type="text" wire:model="listSupplier" placeholder="Market or supplier (optional)" maxlength="255" class="px-3 py-2 border border-gray-300 rounded-lg" />
                        <input type="text" wire:model="listNotes" placeholder="Note (optional)" maxlength="500" class="px-3 py-2 border border-gray-300 rounded-lg" />
                    </div>

                    @error('lines') <div class="text-sm text-red-600" data-testid="list-error">{{ $message }}</div> @enderror

                    <div class="flex items-center justify-between pt-2 border-t">
                        <p class="text-lg font-bold text-gray-900">Total: GH₵ {{ number_format($listTotal, 2) }}</p>
                        <button type="submit" wire:loading.attr="disabled" class="px-5 py-2 bg-orange-600 text-white rounded-lg font-semibold hover:bg-orange-700 disabled:opacity-50">{{ $editingListId ? 'Save & send' : 'Submit' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Mark as bought --}}
    @if ($showBoughtModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4" role="dialog" aria-modal="true" data-testid="bought-modal">
            <div class="bg-white rounded-lg p-6 w-full max-w-md space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Mark as bought</h3>
                <p class="text-sm text-gray-600">This records the shopping as a paid expense on the spending dashboard.</p>
                <div>
                    <label for="boughtMethod" class="block text-xs font-semibold text-gray-600 uppercase mb-1">How was it paid?</label>
                    <select id="boughtMethod" wire:model="paymentMethod" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('paymentMethod') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <button wire:click="closeBought" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Close</button>
                    <button wire:click="markBought" class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-black font-semibold">Mark as bought</button>
                </div>
            </div>
        </div>
    @endif
</div>

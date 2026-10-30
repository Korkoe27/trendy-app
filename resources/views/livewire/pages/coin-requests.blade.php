{{-- Snooker coins: the attendant requests, the bar gives. Refreshes every 4 seconds unless a window is open. --}}
<div class="space-y-6 pt-14 lg:pt-0" @unless ($showVoidModal) wire:poll.4s.visible @endunless>
    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded" role="alert">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg border border-gray-200 p-4 sm:p-6">
        <h2 class="text-xl font-semibold text-gray-900">Coin Requests</h2>
        <p class="text-base text-gray-600 mt-1">Request snooker coins from the bar. Coins come back every day, so nothing is stocked; the end-of-day count checks the snooker money against the coins given.</p>
        <p class="text-sm text-gray-500 mt-1">Session of {{ $date->format('D j M') }}</p>
    </div>

    @if ($games->isEmpty())
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded">
            No game is set up for coins yet. On the Products page, create a product in the "games" category with its coin price and the number of coins per day.
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        {{-- Request panel --}}
        @if ($canRequest && $games->isNotEmpty())
            <div class="bg-white rounded-lg border border-gray-200 p-4 sm:p-6 space-y-4">
                <h3 class="text-base font-semibold text-gray-900">Request coins</h3>

                <div>
                    <label for="coinGame" class="block text-xs font-semibold text-gray-600 uppercase mb-1">Game</label>
                    <select id="coinGame" wire:model.live="productId" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        @foreach ($games as $game)
                            <option value="{{ $game['id'] }}">{{ $game['name'] }}</option>
                        @endforeach
                    </select>
                    @error('productId') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>

                @if ($selected)
                    <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2 text-sm">
                        <p class="font-semibold text-gray-900">Available today: {{ $selected['available'] }} of {{ $selected['daily'] }}</p>
                        <p class="text-gray-600">GH₵ {{ number_format($selected['price'], 2) }} per coin</p>
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Number of coins</label>
                    @if ($maxPerRequest > 1)
                        <div class="inline-flex items-center gap-1 bg-gray-100 rounded-lg">
                            <button type="button" wire:click="decrementQuantity" class="px-4 py-2 text-lg text-gray-600 hover:bg-gray-200 rounded-l-lg" aria-label="One less">−</button>
                            <span class="px-4 text-lg font-bold" data-testid="coin-quantity">{{ $quantity }}</span>
                            <button type="button" wire:click="incrementQuantity" class="px-4 py-2 text-lg text-gray-600 hover:bg-gray-200 rounded-r-lg" aria-label="One more">+</button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Up to {{ $maxPerRequest }} coins per request.</p>
                    @else
                        <p class="text-lg font-bold text-gray-900" data-testid="coin-quantity">1 coin</p>
                        <p class="mt-1 text-xs text-gray-500">One coin per request. For another coin, request again.</p>
                    @endif
                    @if ($selected)
                        <p class="mt-1 text-sm text-gray-600">= GH₵ {{ number_format($selected['price'] * $quantity, 2) }}</p>
                    @endif
                    @error('quantity') <div class="text-red-500 text-sm mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label for="coinNote" class="block text-xs font-semibold text-gray-600 uppercase mb-1">Note (optional)</label>
                    <input id="coinNote" type="text" wire:model="note" maxlength="255" placeholder="e.g. Table 2"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" />
                </div>

                @if ($hasWaiting)
                    <div class="rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 px-3 py-2 text-sm" data-testid="coin-waiting-note">
                        Your coin is waiting for the bar. You can request another once it has been given.
                    </div>
                @endif

                <button wire:click="requestCoins" wire:loading.attr="disabled" wire:target="requestCoins" @disabled($hasWaiting)
                    class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                    REQUEST {{ $quantity }} {{ $quantity === 1 ? 'COIN' : 'COINS' }}
                </button>
            </div>
        @endif

        {{-- Waiting for the bar --}}
        <div class="space-y-3">
            <div class="px-4 py-3 border rounded-lg font-bold text-sm uppercase bg-yellow-50 border-yellow-200 text-yellow-800">Waiting for the bar ({{ $waiting->count() }})</div>
            @forelse ($waiting as $request)
                <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm" wire:key="coin-wait-{{ $request->id }}">
                    <p class="text-lg font-bold text-gray-900">{{ $request->quantity }} × {{ $request->product_name }}</p>
                    <p class="text-sm text-gray-500">{{ $request->requested_by_name }} · {{ $request->requested_at->diffForHumans(null, true, true) }} ago{{ $request->note ? ' · '.$request->note : '' }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($canGive)
                            <button wire:click="give({{ $request->id }})" wire:loading.attr="disabled"
                                class="px-3 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50">Given ✓</button>
                        @endif
                        @if ($canManage || ($canRequest && (int) $request->requested_by === $userId))
                            <button wire:click="cancel({{ $request->id }})" wire:confirm="Cancel this request?"
                                class="px-3 py-2 text-sm font-semibold text-red-700 border border-red-200 rounded-lg hover:bg-red-50">Cancel</button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 px-1">Nothing waiting.</p>
            @endforelse
        </div>

        {{-- Given today --}}
        <div class="space-y-3">
            <div class="px-4 py-3 border rounded-lg font-bold text-sm uppercase bg-green-50 border-green-200 text-green-800">
                Given this session: {{ $totals['coins'] }} {{ $totals['coins'] === 1 ? 'coin' : 'coins' }} · GH₵ {{ number_format($totals['amount'], 2) }}
            </div>
            @forelse ($given as $request)
                <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm {{ $request->status === 'voided' ? 'opacity-60' : '' }}" wire:key="coin-given-{{ $request->id }}">
                    <p class="text-base font-bold text-gray-900 {{ $request->status === 'voided' ? 'line-through' : '' }}">{{ $request->quantity }} × {{ $request->product_name }} · GH₵ {{ number_format((float) $request->total, 2) }}</p>
                    <p class="text-sm text-gray-500">{{ $request->requested_by_name }} · given by {{ $request->given_by_name }} at {{ $request->given_at?->format('H:i') }}</p>
                    @if ($request->status === 'voided')
                        <p class="text-xs text-red-600 mt-1">Voided: {{ $request->void_reason }}</p>
                    @elseif ($canManage)
                        <button wire:click="openVoid({{ $request->id }})" class="mt-2 text-xs text-gray-400 hover:text-red-600">Void</button>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-500 px-1">No coins given yet.</p>
            @endforelse
        </div>
    </div>

    @if ($showVoidModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4" role="dialog" aria-modal="true">
            <div class="bg-white rounded-lg p-6 w-full max-w-md space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Void given coins</h3>
                <p class="text-sm text-gray-600">These coins stop counting towards today's snooker total and return to the pool.</p>
                <div>
                    <label for="voidReason" class="block text-xs font-semibold text-gray-600 uppercase mb-1">Reason</label>
                    <input id="voidReason" type="text" wire:model="voidReason" maxlength="255" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-400" />
                    @error('voidReason') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <button wire:click="closeVoid" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Close</button>
                    <button wire:click="voidGiven" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold">Void coins</button>
                </div>
            </div>
        </div>
    @endif
</div>

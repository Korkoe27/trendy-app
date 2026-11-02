{{-- Snooker coins: the attendant requests, the bar gives. Refreshes every 4 seconds unless a window is open. --}}
<div class="space-y-6 pt-14 lg:pt-0" @unless ($showVoidModal || $viewDate) wire:poll.4s.visible @endunless>
    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded" role="alert">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg border border-gray-200 p-4 sm:p-6">
        <h2 class="text-xl font-semibold text-gray-900">Coin Requests</h2>
        <p class="text-base text-gray-600 mt-1">Request snooker coins from the bar. Coins come back every day, so nothing is stocked; the end-of-day count checks the snooker money against the coins given.</p>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <p class="text-sm text-gray-500">Session of {{ $date->format('D j M') }}</p>
            @if ($availability['open'])
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full border text-xs font-semibold bg-green-100 text-green-800 border-green-300" data-testid="coin-status">Requests open</span>
            @elseif ($availability['reason'] === 'closed')
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full border text-xs font-semibold bg-red-100 text-red-800 border-red-300" data-testid="coin-status">Closed for tonight{{ $availability['closed_by'] ? ' by '.$availability['closed_by'] : '' }}{{ $availability['closed_at'] ? ' at '.$availability['closed_at']->format('H:i') : '' }}</span>
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full border text-xs font-semibold bg-gray-100 text-gray-700 border-gray-300" data-testid="coin-status">Opens at {{ $availability['opens_at']->format('g:i A') }}</span>
            @endif

            @if ($canClose && $availability['open'])
                <button wire:click="closeNight" wire:loading.attr="disabled" data-testid="close-night"
                    wire:confirm="Close coin requests for tonight? {{ $totals['coins'] }} {{ $totals['coins'] === 1 ? 'coin' : 'coins' }} (GH₵ {{ number_format($totals['amount'], 2) }}) given so far.{{ $waiting->count() ? ' '.$waiting->count().' waiting '.($waiting->count() === 1 ? 'request' : 'requests').' will be cancelled.' : '' }}"
                    class="ml-auto px-3 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">Close requests for tonight</button>
            @elseif ($canClose && $availability['reason'] === 'closed')
                <button wire:click="reopenNight" wire:loading.attr="disabled" data-testid="reopen-night" wire:confirm="Open coin requests again for tonight?"
                    class="ml-auto px-3 py-2 text-sm font-semibold border border-gray-300 rounded-lg bg-white hover:bg-gray-50 disabled:opacity-50">Reopen requests</button>
            @endif
        </div>
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
                    <label for="coinCustomer" class="block text-xs font-semibold text-gray-600 uppercase mb-1">Player's name</label>
                    <input id="coinCustomer" type="text" wire:model="customerName" maxlength="100" placeholder="Who is the coin for?" autocomplete="off"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" />
                    @error('customerName') <div class="text-red-500 text-sm mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label for="coinNote" class="block text-xs font-semibold text-gray-600 uppercase mb-1">Note (optional)</label>
                    <input id="coinNote" type="text" wire:model="note" maxlength="255" placeholder="e.g. Table 2"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" />
                </div>

                @unless ($availability['open'])
                    <div class="rounded-lg bg-gray-50 border border-gray-200 text-gray-700 px-3 py-2 text-sm" data-testid="coin-not-open">
                        {{ $availability['reason'] === 'closed' ? 'Coin requests are closed for tonight.' : 'Coin requests open at '.$availability['opens_at']->format('g:i A').'.' }}
                    </div>
                @endunless

                @if ($hasWaiting)
                    <div class="rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 px-3 py-2 text-sm" data-testid="coin-waiting-note">
                        Your coin is waiting for the bar. You can request another once it has been given.
                    </div>
                @endif

                <button wire:click="requestCoins" wire:loading.attr="disabled" wire:target="requestCoins" @disabled($hasWaiting || ! $availability['open'])
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
                    <p class="text-lg font-bold text-gray-900">{{ $request->quantity }} × {{ $request->product_name }}@if ($request->customer_name) <span class="font-semibold text-blue-700">for {{ $request->customer_name }}</span>@endif</p>
                    <p class="text-sm text-gray-500">Requested by {{ $request->requested_by_name }} · {{ $request->requested_at->diffForHumans(null, true, true) }} ago{{ $request->note ? ' · '.$request->note : '' }}</p>
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
                    @if ($request->customer_name)<p class="text-sm font-semibold text-blue-700">{{ $request->customer_name }}</p>@endif
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

    {{-- Day by day (the bar and managers) --}}
    @if ($canHistory && $days)
        <div class="bg-white rounded-lg border border-gray-200 p-4 sm:p-6" data-testid="coin-days">
            <h3 class="text-base font-semibold text-gray-900">Day by day</h3>
            <p class="text-sm text-gray-500 mt-1">Every business day with coin requests. Amounts are what was given, after any voids.</p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-gray-500 border-b">
                            <th class="py-2 pr-3 font-semibold">Day</th>
                            <th class="py-2 pr-3 font-semibold text-right">Coins given</th>
                            <th class="py-2 pr-3 font-semibold text-right">Amount</th>
                            <th class="py-2 pr-3 font-semibold text-right hidden sm:table-cell">Requests</th>
                            <th class="py-2 pr-3 font-semibold hidden md:table-cell">Cancelled / voided</th>
                            <th class="py-2 pr-3 font-semibold hidden sm:table-cell">Status</th>
                            <th class="py-2 text-right font-semibold"><span class="sr-only">View</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($days as $day)
                            <tr wire:key="coin-day-{{ $day['date']->toDateString() }}">
                                <td class="py-2 pr-3 font-semibold text-gray-900 whitespace-nowrap">{{ $day['date']->format('D j M Y') }}@if ($day['tonight']) <span class="ml-1 text-xs font-bold text-blue-700">Tonight</span>@endif</td>
                                <td class="py-2 pr-3 text-right">{{ $day['coins'] }}</td>
                                <td class="py-2 pr-3 text-right font-semibold">GH₵ {{ number_format($day['amount'], 2) }}</td>
                                <td class="py-2 pr-3 text-right hidden sm:table-cell">{{ $day['requests'] }}</td>
                                <td class="py-2 pr-3 hidden md:table-cell text-gray-600">{{ $day['cancelled'] }} / {{ $day['voided'] }}</td>
                                <td class="py-2 pr-3 hidden sm:table-cell">
                                    @if ($day['closed'])
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">Closed{{ $day['closed_by'] ? ' by '.$day['closed_by'] : '' }}</span>
                                    @elseif ($day['tonight'])
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">{{ $availability['open'] ? 'Open' : 'Not open' }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Not closed</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right">
                                    <button wire:click="openDayDetail('{{ $day['date']->toDateString() }}')" data-testid="view-coin-day"
                                        class="px-3 py-1.5 text-xs font-semibold border border-gray-300 rounded-lg bg-gray-50 hover:bg-gray-100">View</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-gray-500">No coin requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $days->links() }}</div>
        </div>
    @endif

    @if ($detail)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4 py-6" role="dialog" aria-modal="true" data-testid="coin-day-modal">
            <div class="bg-white rounded-lg p-4 sm:p-6 w-full max-w-3xl max-h-full overflow-y-auto space-y-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $detail['date']->format('l j F Y') }}</h3>
                        <p class="text-sm text-gray-500">
                            @if ($detail['summary']['closed'])
                                Closed{{ $detail['summary']['closed_by'] ? ' by '.$detail['summary']['closed_by'] : '' }}{{ $detail['summary']['closed_at'] ? ' at '.$detail['summary']['closed_at']->format('H:i') : '' }}
                            @else
                                Not closed by hand
                            @endif
                        </p>
                    </div>
                    <button wire:click="closeDayDetail" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Close</button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="rounded-lg bg-green-50 border border-green-200 p-3"><p class="text-xs text-green-700 uppercase font-semibold">Coins given</p><p class="text-xl font-bold text-green-900">{{ $detail['summary']['coins'] }}</p></div>
                    <div class="rounded-lg bg-green-50 border border-green-200 p-3"><p class="text-xs text-green-700 uppercase font-semibold">Amount</p><p class="text-xl font-bold text-green-900">GH₵ {{ number_format($detail['summary']['amount'], 2) }}</p></div>
                    <div class="rounded-lg bg-gray-50 border border-gray-200 p-3"><p class="text-xs text-gray-600 uppercase font-semibold">Requests</p><p class="text-xl font-bold text-gray-900">{{ $detail['summary']['requests'] }}</p></div>
                    <div class="rounded-lg bg-gray-50 border border-gray-200 p-3"><p class="text-xs text-gray-600 uppercase font-semibold">Cancelled / voided</p><p class="text-xl font-bold text-gray-900">{{ $detail['summary']['cancelled'] }} / {{ $detail['summary']['voided'] }}</p></div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 mb-2">By game</h4>
                        @forelse ($detail['games'] as $row)
                            <div class="flex justify-between text-sm py-1 border-b border-gray-100"><span>{{ $row['name'] }}</span><span class="text-gray-600">{{ $row['coins'] }} {{ $row['coins'] === 1 ? 'coin' : 'coins' }} · GH₵ {{ number_format($row['amount'], 2) }}</span></div>
                        @empty
                            <p class="text-sm text-gray-500">No requests.</p>
                        @endforelse
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 mb-2">By attendant</h4>
                        @forelse ($detail['attendants'] as $row)
                            <div class="flex justify-between text-sm py-1 border-b border-gray-100"><span>{{ $row['name'] }} <span class="text-gray-400">({{ $row['requests'] }} {{ $row['requests'] === 1 ? 'request' : 'requests' }})</span></span><span class="text-gray-600">{{ $row['coins'] }} given · GH₵ {{ number_format($row['amount'], 2) }}</span></div>
                        @empty
                            <p class="text-sm text-gray-500">No requests.</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <h4 class="text-sm font-semibold text-gray-900 mb-2">Every request</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase text-gray-500 border-b">
                                    <th class="py-2 pr-3 font-semibold">Time</th>
                                    <th class="py-2 pr-3 font-semibold">Coins</th>
                                    <th class="py-2 pr-3 font-semibold">Asked by</th>
                                    <th class="py-2 pr-3 font-semibold">Status</th>
                                    <th class="py-2 pr-3 font-semibold hidden sm:table-cell">Given by</th>
                                    <th class="py-2 font-semibold text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($detail['requests'] as $request)
                                    @php
                                        $badge = ['given' => 'bg-green-100 text-green-800', 'requested' => 'bg-yellow-100 text-yellow-800', 'cancelled' => 'bg-gray-100 text-gray-600', 'voided' => 'bg-red-100 text-red-800'][$request->status] ?? 'bg-gray-100 text-gray-600';
                                    @endphp
                                    <tr wire:key="coin-detail-{{ $request->id }}">
                                        <td class="py-2 pr-3 whitespace-nowrap">{{ $request->requested_at?->format('H:i') }}</td>
                                        <td class="py-2 pr-3">{{ $request->quantity }} × {{ $request->product_name }}@if ($request->customer_name)<span class="block text-xs font-semibold text-blue-700">{{ $request->customer_name }}</span>@endif @if ($request->note)<span class="block text-xs text-gray-400">{{ $request->note }}</span>@endif</td>
                                        <td class="py-2 pr-3">{{ $request->requested_by_name }}</td>
                                        <td class="py-2 pr-3">
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $badge }}">{{ ucfirst($request->status) }}</span>
                                            @if ($request->status === 'voided' && $request->void_reason)<span class="block text-xs text-red-600">{{ $request->void_reason }}</span>@endif
                                        </td>
                                        <td class="py-2 pr-3 hidden sm:table-cell">{{ $request->given_by_name ? $request->given_by_name.' at '.$request->given_at?->format('H:i') : '—' }}</td>
                                        <td class="py-2 text-right {{ $request->status === 'voided' ? 'line-through text-gray-400' : '' }}">{{ $request->total !== null ? 'GH₵ '.number_format((float) $request->total, 2) : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="py-4 text-center text-gray-500">No requests this day.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

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

{{-- resources/views/livewire/pages/debt-payments.blade.php --}}
<div class="space-y-6">
    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Header -->
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Debt Payments</h2>
                <p class="text-base text-gray-600 mt-1">Record money received towards a month's unpaid debt</p>
            </div>

            @haspermission('create', 'debt_payments')
                <div class="flex space-x-3">
                    <button wire:click="openPaymentModal"
                        class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-800 transition-colors flex items-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>Record Payment</span>
                    </button>
                </div>
            @endhaspermission
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
            <div class="bg-white border border-gray-200 rounded-lg p-4 flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Debt for {{ $month->format('F Y') }}</p>
                    <p class="text-2xl font-bold text-gray-900">₵{{ number_format($summary['debt'], 2) }}</p>
                    @php
                        $shortDays = $differences->where('difference', '>', 0)->count();
                        $overDays = $differences->where('difference', '<', 0)->count();
                    @endphp
                    @if ($monthNet < 0)
                        <p class="text-xs text-green-700">Over-collected by ₵{{ number_format(-$monthNet, 2) }}, nothing to pay</p>
                    @else
                        <p class="text-xs text-gray-500">{{ $shortDays }} short day{{ $shortDays !== 1 ? 's' : '' }}, {{ $overDays }} over-collected day{{ $overDays !== 1 ? 's' : '' }}</p>
                    @endif
                </div>
                <div class="bg-gray-100 p-2 rounded-full">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-4 flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Paid so far</p>
                    <p class="text-2xl font-bold text-green-700">₵{{ number_format($summary['paid'], 2) }}</p>
                    <p class="text-xs text-gray-500">Voided payments are not counted</p>
                </div>
                <div class="bg-green-100 p-2 rounded-full">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
            </div>

            <div class="{{ $summary['outstanding'] > 0 ? 'bg-yellow-50 border-yellow-200' : 'bg-green-50 border-green-200' }} border rounded-lg p-4 flex items-center justify-between">
                <div>
                    <p class="text-sm {{ $summary['outstanding'] > 0 ? 'text-yellow-700' : 'text-green-700' }}">Outstanding</p>
                    <p class="text-2xl font-bold {{ $summary['outstanding'] > 0 ? 'text-yellow-900' : 'text-green-900' }}">₵{{ number_format($summary['outstanding'], 2) }}</p>
                    <p class="text-xs {{ $summary['outstanding'] > 0 ? 'text-yellow-600' : 'text-green-600' }}">
                        {{ $summary['outstanding'] > 0 ? 'Still to be paid' : ($summary['debt'] > 0 ? 'Fully paid' : 'No debt this month') }}
                    </p>
                </div>
                <div class="{{ $summary['outstanding'] > 0 ? 'bg-yellow-100' : 'bg-green-100' }} p-2 rounded-full">
                    <svg class="w-5 h-5 {{ $summary['outstanding'] > 0 ? 'text-yellow-600' : 'text-green-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        @if ($summary['overpaid'] > 0)
            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-800 text-sm">
                Payments recorded for {{ $month->format('F Y') }} exceed the current debt by
                <span class="font-semibold">₵{{ number_format($summary['overpaid'], 2) }}</span>.
                This can happen when a closed day was edited after payments were received. Please review the payments below.
            </div>
        @endif

        <!-- Filters -->
        <div class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4 mt-6">
            <div class="flex-1 relative">
                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text" placeholder="Search by payer, phone or staff..."
                    wire:model.live.debounce.300ms="searchTerm"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            <input type="month" wire:model.live="selectedMonth" max="{{ now()->format('Y-m') }}"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            <label class="flex items-center space-x-2 text-sm text-gray-700">
                <input type="checkbox" wire:model.live="allMonths" class="rounded border-gray-300" />
                <span>Show payments from all months</span>
            </label>
        </div>
    </div>

    <!-- Daily differences -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">Daily differences in {{ $month->format('F Y') }}</h3>
            <p class="text-sm text-gray-500 mt-0.5">Over-collected days offset short days, so the total is the same as Total Loss on Analytics for the month.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Expected</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Collected</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Difference</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($differences as $day)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-base text-gray-900">
                                {{ \Carbon\Carbon::parse($day['date'])->format('D, M j, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-base text-gray-900">₵{{ number_format($day['expected'], 2) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-base text-gray-900">₵{{ number_format($day['collected'], 2) }}</td>
                            @if ($day['difference'] > 0)
                                <td class="px-6 py-4 whitespace-nowrap text-base font-semibold text-red-600">₵{{ number_format($day['difference'], 2) }} short</td>
                            @else
                                <td class="px-6 py-4 whitespace-nowrap text-base font-semibold text-green-600">₵{{ number_format(-$day['difference'], 2) }} over</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-base text-gray-500 text-center">
                                No differences recorded for this month.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($differences->isNotEmpty())
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="3" class="px-6 py-3 text-base font-semibold text-gray-900">Month total</td>
                            @if ($monthNet > 0)
                                <td class="px-6 py-3 whitespace-nowrap text-base font-bold text-red-600">₵{{ number_format($monthNet, 2) }} short</td>
                            @elseif ($monthNet < 0)
                                <td class="px-6 py-3 whitespace-nowrap text-base font-bold text-green-600">₵{{ number_format(-$monthNet, 2) }} over</td>
                            @else
                                <td class="px-6 py-3 whitespace-nowrap text-base font-bold text-gray-900">₵0.00</td>
                            @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">
                Payments {{ $allMonths ? 'from all months' : 'for '.$month->format('F Y') }}
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Date Paid</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Paid By</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Month Settled</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Method</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Received By</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($payments as $payment)
                        <tr class="hover:bg-gray-50 {{ $payment->isVoided() ? 'opacity-60' : '' }}">
                            <td class="px-6 py-4 whitespace-nowrap text-base text-gray-900">
                                {{ $payment->paid_at->format('M j, Y') }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-base font-medium text-gray-900">{{ $payment->payer_name }}</div>
                                @if ($payment->payer_phone)
                                    <div class="text-sm text-gray-500">{{ $payment->payer_phone }}</div>
                                @endif
                                @if ($payment->notes)
                                    <div class="text-xs text-gray-400 truncate max-w-[220px]" title="{{ $payment->notes }}">{{ $payment->notes }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-base text-gray-900">
                                {{ $payment->period_month->format('F Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base font-semibold text-gray-900 {{ $payment->isVoided() ? 'line-through' : '' }}">
                                    ₵{{ number_format((float) $payment->amount, 2) }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    ₵{{ number_format((float) $payment->outstanding_before, 2) }} → ₵{{ number_format((float) $payment->outstanding_after, 2) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium capitalize
                                    {{ $payment->payment_method === 'cash' ? 'bg-green-100 text-green-700' :
                                       ($payment->payment_method === 'momo' ? 'bg-yellow-100 text-yellow-700' : 'bg-indigo-100 text-indigo-700') }}">
                                    {{ $payment->payment_method }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-base text-gray-900">
                                {{ $payment->received_by_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($payment->isVoided())
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700"
                                        title="{{ $payment->void_reason }}">Voided</span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Recorded</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-base font-medium">
                                @haspermission('delete', 'debt_payments')
                                    @unless ($payment->isVoided())
                                        <button wire:click="openVoidModal({{ $payment->id }})"
                                            class="text-red-600 hover:text-red-900 text-sm" title="Void this payment">
                                            Void
                                        </button>
                                    @endunless
                                @endhaspermission
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-base text-gray-500 text-center">
                                No payments found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $payments->links() }}
        </div>
    </div>

    <!-- Record Payment Modal -->
    @if ($showPaymentModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg w-full max-w-lg mx-auto max-h-[90vh] overflow-y-auto">
                <form wire:submit="savePayment" class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Record Debt Payment</h3>
                            <p class="text-sm text-gray-500 mt-0.5">The amount is deducted from the month's total debt.</p>
                        </div>
                        <button type="button" wire:click="closePaymentModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Month the debt is from *</label>
                            <input type="month" wire:model.live="paymentMonth" max="{{ now()->format('Y-m') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" />
                            @error('paymentMonth') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        @if ($modalSummary)
                            <div class="{{ $modalSummary['outstanding'] > 0 ? 'bg-yellow-50 border-yellow-200' : 'bg-green-50 border-green-200' }} border rounded-lg p-4">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Debt for the month</span>
                                    <span class="font-medium text-gray-900">₵{{ number_format($modalSummary['debt'], 2) }}</span>
                                </div>
                                <div class="flex justify-between text-sm mt-1">
                                    <span class="text-gray-600">Already paid</span>
                                    <span class="font-medium text-gray-900">₵{{ number_format($modalSummary['paid'], 2) }}</span>
                                </div>
                                <div class="flex justify-between text-sm mt-2 pt-2 border-t {{ $modalSummary['outstanding'] > 0 ? 'border-yellow-200' : 'border-green-200' }}">
                                    <span class="font-medium text-gray-800">Outstanding</span>
                                    <span class="font-bold text-gray-900">₵{{ number_format($modalSummary['outstanding'], 2) }}</span>
                                </div>
                                @if ($modalSummary['outstanding'] <= 0)
                                    <p class="text-xs text-green-700 mt-2">There is no outstanding debt for this month.</p>
                                @endif
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name of person paying *</label>
                            <input type="text" wire:model="payerName" placeholder="Enter customer name"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" />
                            @error('payerName') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone number</label>
                            <input type="tel" wire:model="payerPhone" placeholder="0XX XXX XXXX"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" />
                            @error('payerPhone') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label class="block text-sm font-medium text-gray-700">Amount paid (₵) *</label>
                                @if ($modalSummary && $modalSummary['outstanding'] > 0)
                                    <button type="button" wire:click="$set('amount', '{{ number_format($modalSummary['outstanding'], 2, '.', '') }}')"
                                        class="text-xs text-green-700 hover:text-green-900 underline">
                                        Pay full outstanding
                                    </button>
                                @endif
                            </div>
                            <input type="text" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');" step="0.01" min="0.01" wire:model.live.debounce.300ms="amount" placeholder="0.00"
                                class="w-full px-3 py-2 border border-gray-300 focus:border-0 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" />
                            @error('amount') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            @if ($amountAfter !== null)
                                <p class="text-xs mt-1 {{ $amountAfter < 0 ? 'text-red-600' : 'text-gray-500' }}">
                                    @if ($amountAfter < 0)
                                        This is more than the outstanding debt.
                                    @else
                                        Outstanding after this payment: ₵{{ number_format($amountAfter, 2) }}
                                    @endif
                                </p>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Payment method *</label>
                                <select wire:model="paymentMethod"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    @foreach ($methods as $method)
                                        <option value="{{ $method }}">{{ ucfirst($method) }}</option>
                                    @endforeach
                                </select>
                                @error('paymentMethod') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date paid *</label>
                                <input type="date" wire:model="paidAt" max="{{ now()->format('Y-m-d') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" />
                                @error('paidAt') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea wire:model="notes" rows="2" placeholder="Optional"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"></textarea>
                            @error('notes') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <p class="text-xs text-gray-500">
                            Received by <span class="font-medium text-gray-700">{{ auth()->user()->name }}</span> (recorded automatically).
                        </p>
                    </div>

                    <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-gray-200">
                        <button type="button" wire:click="closePaymentModal"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="savePayment"
                            @disabled($modalSummary && $modalSummary['outstanding'] <= 0)
                            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="savePayment">Save Payment</span>
                            <span wire:loading wire:target="savePayment">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Void Payment Modal -->
    @if ($showVoidModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg w-full max-w-md mx-auto">
                <form wire:submit="voidPayment" class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Void this payment?</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        The amount is added back to the month's outstanding debt. The payment stays in the list marked as voided.
                    </p>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason *</label>
                        <textarea wire:model="voidReason" rows="3" placeholder="e.g. Recorded against the wrong month"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"></textarea>
                        @error('voidReason') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-gray-200">
                        <button type="button" wire:click="closeVoidModal"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                            class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition-colors">
                            Void Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

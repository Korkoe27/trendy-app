{{-- resources/views/livewire/pages/expenses.blade.php --}}
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
                <h2 class="text-xl font-semibold text-gray-900">Spending</h2>
                <p class="text-base text-gray-600 mt-1">Track all pub expenses & purchases</p>
            </div>

            @haspermission('create', 'expenses')
                <div class="flex space-x-3">
                    <button wire:click="$set('showAddExpenseModal', true)"
                        class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-800 transition-colors flex items-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>Add Expense</span>
                    </button>

                    <button wire:click="$set('showExportModal', true)"
                        class="bg-white text-gray-800 border border-gray-600 px-6 py-2 rounded-lg hover:bg-gray-200 transition-colors flex items-center space-x-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                        <span>Export</span>
                    </button>
                </div>
            @endhaspermission
        </div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
    <div class="bg-white border border-gray-200 rounded-lg p-4 flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-600">Spending this month</p>
            <p class="text-2xl font-bold text-gray-900">₵{{ number_format($stats['this_month'], 2) }}</p>
            <p class="text-xs {{ $stats['change'] < 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ $stats['change'] < 0 ? '↓' : '↑' }} 
                {{ abs($stats['change']) }}% vs last month
            </p>
        </div>
        <div class="bg-{{ $stats['change'] < 0 ? 'green' : 'red' }}-100 p-2 rounded-full">
            <svg class="w-5 h-5 text-{{ $stats['change'] < 0 ? 'green' : 'red' }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 13l-7 7-7-7m14-8l-7 7-7-7"></path>
            </svg>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-4 flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-600">Previous month</p>
            <p class="text-2xl font-bold text-gray-900">₵{{ number_format($stats['last_month'], 2) }}</p>
            <p class="text-xs text-gray-500">{{ number_format($stats['change'], 1) }}% change</p>
        </div>
        <div class="bg-gray-100 p-2 rounded-full">
            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
        </div>
    </div>

    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 flex items-center justify-between">
        <div>
            <p class="text-sm text-yellow-700">Pending transactions</p>
            <p class="text-2xl font-bold text-yellow-900">₵{{ number_format($stats['pending_total'], 2) }}</p>
            <p class="text-xs text-yellow-600">{{ $stats['pending_count'] }} transaction{{ $stats['pending_count'] != 1 ? 's' : '' }}</p>
        </div>
        <div class="bg-yellow-100 p-2 rounded-full">
            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
    </div>
</div>

        <!-- Search and Filter -->
        <div class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4 mt-6">
            <div class="flex-1 relative">
                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text" placeholder="Search expenses, vendor, or reference..."
                    wire:model.live="searchTerm"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            <select wire:model.live="selectedCategory"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="all">All Categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->category }}">{{ $category->category }}</option>
                @endforeach
            </select>
            <select wire:model.live="selectedMonth"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">This Month</option>
                <option value="last">Last Month</option>
                <option value="all">All Time</option>
            </select>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Reference
                        </th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Description
                        </th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Vendor
                        </th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Date
                        </th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Amount
                        </th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Payment
                        </th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($expenses as $expense)
                        <tr wire:click="viewExpense({{ $expense->id }})"
                            class="hover:bg-gray-50 cursor-pointer transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $expense->category === 'STOCK PURCHASE' ? 'bg-blue-100 text-blue-800' :
                                           ($expense->category === 'OPERATIONAL' ? 'bg-green-100 text-green-800' :
                                           ($expense->category === 'EQUIPMENT' ? 'bg-purple-100 text-purple-800' :
                                           ($expense->category === 'MAINTENANCE' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800'))) }}">
                                        #{{ $expense->reference }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-base font-medium text-gray-900">{{ $expense->description }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base text-gray-900">{{ $expense->vendor }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base text-gray-900">
                                    {{ \Carbon\Carbon::parse($expense->date)->format('M j, Y') }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base font-semibold text-gray-900">
                                    ₵{{ number_format($expense->amount, 2) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                    {{ $expense->payment_method === 'BANK TRANSFER' ? 'bg-indigo-100 text-indigo-700' :
                                       ($expense->payment_method === 'CASH' ? 'bg-green-100 text-green-700' :
                                       ($expense->payment_method === 'MOBILE MONEY' ? 'bg-yellow-100 text-yellow-700' :
                                       ($expense->payment_method === 'CREDIT' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700'))) }}">
                                    {{ $expense->payment_method }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                    {{ $expense->status === 'PAID' ? 'bg-green-100 text-green-700' :
                                       ($expense->status === 'PENDING' ? 'bg-yellow-100 text-yellow-700' :
                                       ($expense->status === 'CANCELLED' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')) }}">
                                    {{ $expense->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-base font-medium" onclick="event.stopPropagation();">
                                <div class="flex space-x-2">
                                    @haspermission('modify', 'expenses')
                                        <button wire:click.stop="editExpense({{ $expense->id }})"
                                            class="text-green-600 hover:text-green-800" title="Edit">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 4h2m-6.586 9.414l8.586-8.586a2 2 0 112.828 2.828l-8.586 8.586H7v-2.828zM5 19h14" />
                                            </svg>
                                        </button>
                                    @endhaspermission
                                    @haspermission('delete', 'expenses')
                                        <button wire:click.stop="deleteExpense({{ $expense->id }})"
                                            wire:confirm="Are you sure you want to delete this expense?"
                                            class="text-red-600 hover:text-red-900" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endhaspermission
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-4 whitespace-nowrap text-base text-gray-500 text-center">
                                No expenses found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $expenses->links() }}
        </div>
    </div>

    <!-- View Expense Modal -->
@if ($viewExpenseModal && $selectedExpense)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-4xl mx-auto max-h-[90vh] overflow-y-auto">
            {{-- Invoice Header --}}
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Expense Invoice</h2>
                        <p class="text-sm text-gray-500 mt-1">{{ config('app.name', 'Your Business') }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Invoice Number</div>
                        <div class="text-lg font-semibold text-gray-900">#{{ $selectedExpense->reference }}</div>
                    </div>
                </div>
            </div>

            {{-- Invoice Details --}}
            <div class="p-6 border-b border-gray-200">
                <div class="grid grid-cols-2 gap-6">
                    {{-- Date Information --}}
                    <div>
                        <div class="mb-4">
                            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Issued</div>
                            <div class="text-sm font-medium text-gray-900">
                                {{ \Carbon\Carbon::parse($selectedExpense->date)->format('M d, Y') }}
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Category</div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                {{ $selectedExpense->category === 'STOCK PURCHASE' ? 'bg-blue-100 text-blue-800' :
                                   ($selectedExpense->category === 'OPERATIONAL' ? 'bg-green-100 text-green-800' :
                                   ($selectedExpense->category === 'EQUIPMENT' ? 'bg-purple-100 text-purple-800' :
                                   ($selectedExpense->category === 'MAINTENANCE' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800'))) }}">
                                {{ str_replace('_', ' ', $selectedExpense->category) }}
                            </span>
                        </div>
                    </div>

                    {{-- Payment Information --}}
                    <div class="text-right">
                        <div class="mb-4">
                            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Payment Method</div>
                            <div class="text-sm font-medium text-gray-900">{{ $selectedExpense->payment_method }}</div>
                        </div>
                        <div class="mb-4">
                            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Status</div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                {{ $selectedExpense->status == 'paid' ? 'bg-green-100 text-green-700' :
                                   ($selectedExpense->status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                                   ($selectedExpense->status === 'canceled' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')) }}">
                                {{ $selectedExpense->status }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Vendor/Supplier Information --}}
                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Vendor/Supplier</div>
                            <div class="text-sm font-semibold text-gray-900 mt-1">{{ $selectedExpense->vendor }}</div>
                        </div>
                        @if($selectedExpense->paid_by)
                        <div class="text-right">
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Paid By</div>
                            <div class="text-sm font-medium text-gray-900 mt-1">{{ $selectedExpense->paid_by }}</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Items Table (if available in metadata) --}}
            @php
                $metadata = json_decode($selectedExpense->metadata, true) ?? [];
                $items = json_decode($selectedExpense->items, true) ?? [];
            @endphp

            @if(!empty($items))
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">Items</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Free</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Cost</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($items as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $item['product_name'] ?? 'N/A' }}</div>
                                    @if(isset($item['sku']) && $item['sku'])
                                        <div class="text-xs text-gray-500">SKU: {{ $item['sku'] }}</div>
                                    @endif
                                    @if(isset($item['category']) && $item['category'])
                                        <div class="text-xs text-gray-500">{{ $item['category'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-900">{{ $item['supplier_name'] ?? 'N/A' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="text-sm text-gray-900">{{ number_format($item['paid_quantity'] ?? 0) }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="text-sm text-green-600 font-medium">
                                        {{ $item['free_quantity'] > 0 ? '+' . number_format($item['free_quantity']) : '-' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="text-sm text-gray-900">₵{{ number_format($item['unit_cost'] ?? 0, 2) }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="text-sm font-semibold text-gray-900">₵{{ number_format($item['total_cost'] ?? 0, 2) }}</div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Summary Stats --}}
                @if(isset($metadata['total_units']) || isset($metadata['total_free_units']))
                <div class="mt-4 grid grid-cols-3 gap-4">
                    @if(isset($metadata['total_items']))
                    <div class="bg-blue-50 p-3 rounded-lg">
                        <div class="text-xs text-blue-600 uppercase tracking-wide">Total Items</div>
                        <div class="text-lg font-bold text-blue-900">{{ $metadata['total_items'] }}</div>
                    </div>
                    @endif
                    @if(isset($metadata['total_units']))
                    <div class="bg-purple-50 p-3 rounded-lg">
                        <div class="text-xs text-purple-600 uppercase tracking-wide">Total Units</div>
                        <div class="text-lg font-bold text-purple-900">{{ number_format($metadata['total_units']) }}</div>
                    </div>
                    @endif
                    @if(isset($metadata['total_free_units']) && $metadata['total_free_units'] > 0)
                    <div class="bg-green-50 p-3 rounded-lg">
                        <div class="text-xs text-green-600 uppercase tracking-wide">Free Units</div>
                        <div class="text-lg font-bold text-green-900">{{ number_format($metadata['total_free_units']) }}</div>
                    </div>
                    @endif
                </div>
                @endif
            </div>
            @endif

            {{-- Description and Notes --}}
            <div class="p-6 border-b border-gray-200 bg-gray-50">
                <div class="mb-4">
                    <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">Description</div>
                    <div class="text-sm text-gray-700">{{ $selectedExpense->description }}</div>
                </div>
                @if($selectedExpense->notes)
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">Notes</div>
                    <div class="text-sm text-gray-600 italic">{{ $selectedExpense->notes }}</div>
                </div>
                @endif
            </div>

            {{-- Total Amount --}}
            <div class="p-6 bg-gray-50">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-600">Subtotal</span>
                    <span class="text-sm text-gray-900">₵{{ number_format($selectedExpense->amount, 2) }}</span>
                </div>
                @if(!empty($items))
                    @php
                        $subtotal = array_sum(array_column($items, 'total_cost'));
                        $discount = $subtotal - $selectedExpense->amount;
                    @endphp
                    @if($discount > 0)
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm text-green-600">Discount</span>
                        <span class="text-sm text-green-600">-₵{{ number_format($discount, 2) }}</span>
                    </div>
                    @endif
                @endif
                <div class="border-t border-gray-300 pt-2 mt-2">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-semibold text-gray-900">Total Amount</span>
                        <span class="text-2xl font-bold text-gray-900">₵{{ number_format($selectedExpense->amount, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="p-6 bg-white border-t border-gray-200">
                <div class="flex justify-between items-center">
                    <button wire:click="closeViewModal" 
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Close
                    </button>
                    @if($selectedExpense->status === 'PENDING')
                    <button wire:click="confirmPendingTransaction({{ $selectedExpense->id }})"
                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Confirm Transaction</span>
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif

    <!-- Export Modal (Same as Stock Export) -->
    @if ($showExportModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold">Export Expenses - Advanced Filters</h3>
                    <button wire:click="$set('showExportModal', false)" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Filters similar to stock export -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select wire:model="exportFilters.category"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="all">All Categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->category }}">{{ $category->category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Vendor</label>
                        <input type="text" wire:model="exportFilters.vendor"
                            placeholder="Search by vendor name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <!-- Amount Range -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Amount Range (GH₵)</label>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="number" wire:model="exportFilters.amount_min"
                                placeholder="Min amount" step="0.01" min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <input type="number" wire:model="exportFilters.amount_max"
                                placeholder="Max amount" step="0.01" min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <!-- Date Range -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="date" wire:model="exportFilters.date_from"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <input type="date" wire:model="exportFilters.date_to"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select wire:model="exportFilters.status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="all">All Status</option>
                            <option value="CONFIRMED">Confirmed</option>
                            <option value="PENDING">Pending</option>
                            <option value="CANCELLED">Cancelled</option>
                        </select>
                    </div>
                </div>

                @php
                    $activeFilters = collect($exportFilters)->filter(fn($v) => $v !== 'all' && $v !== '' && $v !== null)->count();
                @endphp

                @if($activeFilters > 0)
                    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                </svg>
                                <span class="text-sm font-medium text-blue-900">{{ $activeFilters }} filter(s) active</span>
                            </div>
                            <button wire:click="resetExportFilters" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                Clear all filters
                            </button>
                        </div>
                    </div>
                @endif

                <div class="flex justify-between mt-6 gap-3">
                    <button wire:click="resetExportFilters"
                        class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Reset Filters
                    </button>
                    <div class="flex gap-3">
                        <button wire:click="$set('showExportModal', false)"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button wire:click="exportExpenses"
                            class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            <span>Export</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <!-- Add Expense Modal -->
@if ($showAddExpenseModal)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-auto max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-900">Add New Expense</h3>
                <button wire:click="closeAddModal" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="saveExpense" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Reference -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reference</label>
                        <input type="text" wire:model="reference" readonly
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                        @error('reference') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date *</label>
                        <input type="date" wire:model="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        @error('date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                        <select wire:model="category"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Category</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->category }}">{{ $cat->category }}</option>
                            @endforeach
                        </select>
                        @error('category') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Amount -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Amount (GH₵) *</label>
                        <input type="number" wire:model="amount" step="0.01" min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="0.00">
                        @error('amount') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Vendor -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Vendor *</label>
                        <input type="text" wire:model="vendor"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter vendor name">
                        @error('vendor') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Payment Method -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
                        <select wire:model="payment_method"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="CASH">Cash</option>
                            <option value="BANK TRANSFER">Bank Transfer</option>
                            <option value="MOBILE MONEY">Mobile Money</option>
                            <option value="CREDIT">Credit</option>
                        </select>
                        @error('payment_method') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                        <select wire:model="status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="PENDING">Pending</option>
                            <option value="CONFIRMED">Confirmed</option>
                            <option value="CANCELLED">Cancelled</option>
                        </select>
                        @error('status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Paid By -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Paid By</label>
                        <input type="text" wire:model="paid_by"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Person who made payment">
                        @error('paid_by') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                    <textarea wire:model="description" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="Enter expense description"></textarea>
                    @error('description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                    <textarea wire:model="notes" rows="2"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="Additional notes or comments"></textarea>
                    @error('notes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <!-- Actions -->
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" wire:click="closeAddModal"
                        class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

<!-- Edit Expense Modal -->
@if ($showEditExpenseModal)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-4xl mx-auto max-h-[90vh] overflow-y-auto">
            {{-- Header --}}
            <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900">Edit Expense Invoice</h3>
                        <p class="text-sm text-gray-600 mt-1">Update expense details and information</p>
                    </div>
                    <button wire:click="closeEditModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <form wire:submit.prevent="updateExpense">
                {{-- Invoice Info Section --}}
                <div class="p-6 border-b border-gray-200 bg-gray-50">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wide mb-2">
                                Reference Number
                            </label>
                            <input type="text" wire:model="reference" readonly
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 font-mono text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wide mb-2">
                                Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" wire:model="date"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            @error('date') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wide mb-2">
                                Category <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="category"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase">
                                <option value="">Select Category</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat?->category }}">{{ $cat?->category }}</option>
                                @endforeach
                            </select>
                            @error('category') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- Vendor & Payment Section --}}
                <div class="p-6 border-b border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">Payment Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-2">
                                Vendor/Supplier <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                </div>
                                <input type="text" wire:model="vendor"
                                    class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase"
                                    placeholder="Enter vendor name">
                            </div>
                            @error('vendor') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-2">
                                Paid By
                            </label>
                            <input type="text" wire:model="paid_by"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Person who made payment">
                            @error('paid_by') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-2">
                                Payment Method <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="payment_method"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="CASH">Cash</option>
                                <option value="BANK TRANSFER">Bank Transfer</option>
                                <option value="MOBILE MONEY">Mobile Money</option>
                                <option value="CREDIT">Credit</option>
                            </select>
                            @error('payment_method') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-2">
                                Status <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="status"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="pending">Pending</option>
                                <option value="paid">Confirmed</option>
                                <option value="canceled">Cancelled</option>
                            </select>
                            @error('status') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- Amount & Description Section --}}
                <div class="p-6 border-b border-gray-200 bg-gray-50">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wide mb-2">
                                Total Amount (GH₵) <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 text-lg">₵</span>
                                </div>
                                <input type="number" wire:model="amount" step="0.01" min="0"
                                    class="w-full pl-10 pr-4 py-3 text-xl font-semibold border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            @error('amount') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-2">
                                Description <span class="text-red-500">*</span>
                            </label>
                            <textarea wire:model="description" rows="3"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter detailed description of the expense"></textarea>
                            @error('description') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-2">
                                Additional Notes
                            </label>
                            <textarea wire:model="notes" rows="2"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Any additional notes or comments"></textarea>
                            @error('notes') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div class="p-6 bg-white flex justify-end space-x-3">
                    <button type="button" wire:click="closeEditModal"
                        class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Update Expense</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
</div>
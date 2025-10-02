<div class="space-y-6">
    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Header --}}
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Daily Inventory</h2>
                <p class="text-sm text-gray-600 mt-1">Record daily sales and inventory counts</p>
            </div>

            <div class="flex items-center space-x-3">
                {{-- Date selector (binds to selectedDate if you want to query historical days later) --}}
                <input type="date" wire:model="selectedDate"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                <button wire:click="openTakeInventoryModal"
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <span>Take Inventory</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Daily Sales Records Table --}}
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Daily Sales Records</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cash
                            Sales</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Mobile Money</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Hubtel</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total
                            Revenue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items
                            Sold</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @if (count($dailySalesRecords) > 0)
                        @foreach ($dailySalesRecords as $record)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                            </path>
                                        </svg>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ \Carbon\Carbon::parse($record->date)->format('M j, Y') }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ \Carbon\Carbon::parse($record->date)->format('l') }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-green-600">
                                        GH₵ {{ number_format($record->total_cash, 2) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-blue-600">
                                        GH₵ {{ number_format($record->total_momo, 2) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-purple-600">
                                        GH₵ {{ number_format($record->total_hubtel, 2) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-gray-900">
                                        GH₵ {{ number_format($record->total_revenue, 2) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ $record->total_products }} items
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-3">
                                        <button wire:click="openDetailsModal({{ $record->first_id }})"
                                            class="text-blue-600 cursor-pointer hover:text-blue-900 flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                </path>
                                            </svg>
                                            <span>View</span>
                                        </button>

                                        <button wire:click="openEditModal({{ $record->first_id }})"
                                            class="text-amber-600 cursor-pointer hover:text-amber-900 flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                </path>
                                            </svg>
                                            <span>Edit</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">No sales records found</td>
                        </tr>
                    @endif
                </tbody>

            </table>
        </div>
    </div>

    {{-- Take Inventory Modal --}}
    @if ($showTakeInventoryModal)
        <div class="fixed inset-0 bg-black/50 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">
                        @if ($isEditing)
                            Edit Inventory -
                            {{ \Carbon\Carbon::parse($editingOriginalRecord['date'])->format('M j, Y') }}
                            @if (Auth::user())
                                {{-- @if (auth()->user()->role !== 'admin') --}}
                                <span class="text-sm font-normal text-amber-600">(Stock counts only)</span>
                            @endif
                        @else
                            Take Inventory - {{ \Carbon\Carbon::parse($selectedDate)->format('M j, Y') }}
                        @endif
                    </h3>
                    <button wire:click="closeTakeInventoryModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                {{-- Stepper --}}
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        @php
                            $steps = [
                                ['number' => 1, 'title' => 'Cash Sales', 'icon' => 'dollar'],
                                ['number' => 2, 'title' => 'Mobile Money', 'icon' => 'phone'],
                                ['number' => 3, 'title' => 'Hubtel', 'icon' => 'credit-card'],
                                ['number' => 4, 'title' => 'Food Total', 'icon' => 'food'],
                                ['number' => 5, 'title' => 'Stock Count', 'icon' => 'package'],
                            ];
                        @endphp
                        @foreach ($steps as $index => $step)
                            <div class="flex items-center">
                                <div
                                    class="flex items-center justify-center w-10 h-10 rounded-full border-2 {{ $currentStep > $step['number']
                                        ? 'bg-green-600 border-green-600 text-white'
                                        : ($currentStep == $step['number']
                                            ? 'bg-blue-600 border-blue-600 text-white'
                                            : 'border-gray-300 text-gray-400') }}">
                                    @if ($currentStep > $step['number'])
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    @else
                                        @if ($step['icon'] == 'dollar')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                                                </path>
                                            </svg>
                                        @elseif($step['icon'] == 'phone')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z">
                                                </path>
                                            </svg>
                                        @elseif($step['icon'] == 'food')
                                            {{-- <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z">
                                                </path>
                                            </svg> --}}
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                class="w-5 h-5">
                                                <path d="M12 21a9 9 0 0 0 9-9H3a9 9 0 0 0 9 9Z" />
                                                <path d="M7 21h10" />
                                                <path d="M19.5 12 22 6" />
                                                <path
                                                    d="M16.25 3c.27.1.8.53.75 1.36-.06.83-.93 1.2-1 2.02-.05.78.34 1.24.73 1.62" />
                                                <path
                                                    d="M11.25 3c.27.1.8.53.74 1.36-.05.83-.93 1.2-.98 2.02-.06.78.33 1.24.72 1.62" />
                                                <path
                                                    d="M6.25 3c.27.1.8.53.75 1.36-.06.83-.93 1.2-1 2.02-.05.78.34 1.24.74 1.62" />
                                            </svg>
                                        @elseif($step['icon'] == 'credit-card')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2z">
                                                </path>
                                            </svg>
                                        @else
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4">
                                                </path>
                                            </svg>
                                        @endif
                                    @endif
                                </div>
                                <div class="ml-3">
                                    <p
                                        class="text-sm font-medium {{ $currentStep == $step['number']
                                            ? 'text-blue-600'
                                            : ($currentStep > $step['number']
                                                ? 'text-green-600'
                                                : 'text-gray-400') }}">
                                        {{ $step['title'] }}
                                    </p>
                                </div>
                                @if ($index < count($steps) - 1)
                                    <div
                                        class="flex-1 h-0.5 mx-4 {{ $currentStep > $step['number'] ? 'bg-green-600' : 'bg-gray-300' }}">
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Step Content --}}
                <div class="mb-8">
                    @if ($currentStep == 1)
                        <div wire:key="cash-step" class="space-y-4">
                            <div class="text-center mb-6">
                                <svg class="w-12 h-12 text-green-600 mx-auto mb-2" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                                    </path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900">Cash Sales</h3>
                                <p class="text-sm text-gray-600">Enter the total cash amount collected today</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Cash Amount (GH₵)</label>
                                <input type="number" min="0" step="0.01" wire:model.lazy="cashAmount"
                                    placeholder="0.00"
                                    class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-center" />
                                @error('cashAmount')
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    @elseif($currentStep == 2)
                        <div wire:key="momo-step" class="space-y-4">
                            <div class="text-center mb-6">
                                <svg class="w-12 h-12 text-blue-600 mx-auto mb-2" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z">
                                    </path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900">Mobile Money Sales</h3>
                                <p class="text-sm text-gray-600">Enter the total mobile money amount collected today
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Mobile Money Amount
                                    (GH₵)</label>
                                <input type="number" min="0" step="0.01" wire:model.defer="momoAmount"
                                    placeholder="0.00"
                                    class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-center" />
                                @error('momoAmount')
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    @elseif($currentStep == 3)
                        <div wire:key="hubtel-step" class="space-y-4">
                            <div class="text-center mb-6">
                                <svg class="w-12 h-12 text-purple-600 mx-auto mb-2" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2z">
                                    </path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900">Hubtel Sales</h3>
                                <p class="text-sm text-gray-600">Enter the total Hubtel amount collected today</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-2">Hubtel Amount (GH₵)</label>
                                <input type="number" step="0.01" min="0" wire:model.defer="hubtelAmount"
                                    placeholder="0.00"
                                    class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-center" />
                                @error('hubtelAmount')
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    @elseif($currentStep == 4)
                        <div wire:key="foodSales-step" class="space-y-4">
                            <div class="text-center mb-6">
                                {{-- <svg class="w-12 h-12 text-purple-600 mx-auto mb-2" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2z">
                                    </path>
                                </svg> --}}
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-12 h-12 text-amber-600 mx-auto mb-2"><path d="M12 21a9 9 0 0 0 9-9H3a9 9 0 0 0 9 9Z"/><path d="M7 21h10"/><path d="M19.5 12 22 6"/><path d="M16.25 3c.27.1.8.53.75 1.36-.06.83-.93 1.2-1 2.02-.05.78.34 1.24.73 1.62"/><path d="M11.25 3c.27.1.8.53.74 1.36-.05.83-.93 1.2-.98 2.02-.06.78.33 1.24.72 1.62"/><path d="M6.25 3c.27.1.8.53.75 1.36-.06.83-.93 1.2-1 2.02-.05.78.34 1.24.74 1.62"/></svg>
                                <h3 class="text-lg font-semibold text-gray-900">Food Sales</h3>
                                <p class="text-sm text-gray-600">Enter the total Food amount collected today</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-2">Food Total (GH₵)</label>
                                <input type="number" step="0.01" min="0" wire:model.defer="foodTotal"
                                    placeholder="0.00"
                                    class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-center" />
                                @error('foodTotal')
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    @elseif($currentStep == 5)
                        <div wire:key="stock-step" class="space-y-4">
                            <div class="text-center mb-6">
                                <svg class="w-12 h-12 text-orange-600 mx-auto mb-2" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900">Stock Count</h3>
                                <p class="text-sm text-gray-600">Enter the remaining stock for each product</p>
                            </div>

                            <div class="max-h-96 overflow-y-auto">
                                <div class="space-y-4">
                                    @foreach ($products as $product)
                                        @php
                                            $currentStock = $product->stocks;
                                            $currentTotalUnits = $currentStock ? $currentStock->total_units : 0;
                                            $currentBoxes =
                                                $product->units_per_box > 0
                                                    ? floor($currentTotalUnits / $product->units_per_box)
                                                    : 0;
                                            $remainingUnits =
                                                $currentTotalUnits - $currentBoxes * ($product->units_per_box ?? 1);
                                        @endphp
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex items-center justify-between mb-3">
                                                <div>
                                                    <h4 class="font-medium text-gray-900">{{ $product->name }}</h4>
                                                    {{-- <p class="text-sm text-gray-500">
                                                        {{ $product->category->name ?? 'N/A' }}</p> --}}
                                                </div>
                                                <div class="text-right text-sm text-gray-600">
                                                    <div>Current: {{ $currentTotalUnits }} units</div>
                                                    <div>Boxes: {{ $currentBoxes }} ({{ $remainingUnits }} loose
                                                        units)</div>
                                                    <div class="text-xs text-gray-500">
                                                        {{ $product->units_per_box ?? 1 }} units/box</div>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Closing
                                                        Boxes</label>
                                                    <input type="number" min="0"
                                                        wire:model="productStocks.{{ $product->id }}.closing_boxes"
                                                        placeholder="0"
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Closing
                                                        Units</label>
                                                    <input type="number" min="0"
                                                        wire:model="productStocks.{{ $product->id }}.closing_units"
                                                        placeholder="0"
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Damaged
                                                        Units</label>
                                                    <input type="number" min="0"
                                                        wire:model="productStocks.{{ $product->id }}.damaged_units"
                                                        placeholder="0"
                                                        class="w-full px-3 py-2 text-sm border border-red-300 rounded-md focus:ring-2 focus:ring-red-500 focus:border-transparent" />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Credit
                                                        Units</label>
                                                    <input type="number" min="0"
                                                        wire:model="productStocks.{{ $product->id }}.credit_units"
                                                        placeholder="0"
                                                        class="w-full px-3 py-2 text-sm border border-yellow-300 rounded-md focus:ring-2 focus:ring-yellow-500 focus:border-transparent" />
                                                </div>
                                                <div>
                                                    <label
                                                        class="block text-xs font-medium text-gray-700 mb-1">Expected
                                                        Revenue</label>
                                                    <div
                                                        class="w-full px-3 py-2 text-sm bg-green-50 border border-green-200 rounded-md text-green-700 font-medium">
                                                        GH₵
                                                        {{ number_format($this->calculateExpectedRevenue($product->id), 2) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Modal Footer --}}
                <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                    <div class="flex space-x-3">
                        @if ($currentStep > 1 && (!$isEditing || Auth::user()))
                            {{-- @if ($currentStep > 1 && (!$isEditing || auth()->user()->role === 'admin')) --}}
                            <button wire:click="previousStep"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Previous
                            </button>
                        @endif
                    </div>

                    <div class="flex space-x-3">
                        <button wire:click="closeTakeInventoryModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>

                        @if ($currentStep < 5 && (!$isEditing || Auth::user()))
                            <button wire:click="nextStep"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Next
                            </button>
                        @else
                            @if ($isEditing)
                                <button wire:click="updateInventory"
                                    class="px-6 py-2 text-sm font-medium text-white bg-amber-600 border border-transparent rounded-md hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                        </path>
                                    </svg>
                                    <span>Update Record</span>
                                </button>
                            @else
                                <button wire:click="submitInventory"
                                    class="px-6 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span>Submit Inventory</span>
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Details Modal --}}
    {{-- Details Modal (Redesigned) --}}
    @if ($showDetailsModal && $selectedRecord)
        <div class="fixed inset-0 bg-black/50 bg-opacity-50 flex items-center justify-center z-50">
            <div
                class="bg-white rounded-2xl p-6 md:p-8 w-full max-w-6xl mx-4 max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100">
                {{-- Header --}}
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h3 class="text-xl md:text-2xl font-semibold text-gray-900 tracking-tight">
                            Sales Details — {{ \Carbon\Carbon::parse($selectedRecord['date'])->format('M j, Y') }}
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">Complete breakdown of daily sales, payments, and stock
                            activity</p>
                    </div>
                    <button wire:click="closeDetailsModal"
                        class="text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-lg hover:bg-gray-100">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                @php
                    $sumUnits = collect($selectedRecord['products'] ?? [])->sum('units_sold');
                    $sumBoxes = collect($selectedRecord['products'] ?? [])->sum('boxes_sold');
                    $sumRevenue = collect($selectedRecord['products'] ?? [])->sum('revenue');

                    $money = number_format($selectedRecord['total_money'] ?? 0, 2);
                    $momo = number_format($selectedRecord['total_momo'] ?? 0, 2);
                    $cash = number_format($selectedRecord['total_cash'] ?? 0, 2);
                    $hubtel = number_format($selectedRecord['total_hubtel'] ?? 0, 2);

                    $expected = number_format($selectedRecord['total_revenue'] ?? 0, 2);
                    $profit = number_format($selectedRecord['total_profit'] ?? 0, 2);

                    $lossAmt = number_format($selectedRecord['total_loss_amount'] ?? 0, 2);
                    $creditAmt = number_format($selectedRecord['total_credit_amount'] ?? 0, 2);
                    $creditU = number_format($selectedRecord['total_credit_units'] ?? 0, 0);
                    $damagedU = number_format($selectedRecord['total_damaged'] ?? 0, 0);
                @endphp

                {{-- Group 1: Financial Overview --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
                    {{-- Sum Total (Collected) --}}
                    <div class="rounded-xl border border-green-200 bg-gradient-to-b from-green-50 to-white p-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-medium text-green-700 uppercase tracking-wider">Sum Total
                                    Collected</p>
                                <p class="mt-2 text-2xl font-bold text-green-900">GH₵ {{ $money }}</p>
                            </div>
                            <div class="shrink-0 rounded-lg bg-green-100 p-2">
                                <svg class="w-6 h-6 text-green-700" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M17 6a8 8 0 1 0 0 12" stroke-linecap="round" stroke-linejoin="round" />
                                    <line x1="12" y1="2" x2="12" y2="22"
                                        stroke-linecap="round" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-3 text-sm">
                            <div class="rounded-lg bg-white border border-green-100 p-3">
                                <p class="text-xs text-gray-500">MoMo</p>
                                <p class="font-semibold text-gray-900">{{ $momo }}</p>
                            </div>
                            <div class="rounded-lg bg-white border border-green-100 p-3">
                                <p class="text-xs text-gray-500">Cash</p>
                                <p class="font-semibold text-gray-900">{{ $cash }}</p>
                            </div>
                            <div class="rounded-lg bg-white border border-green-100 p-3">
                                <p class="text-xs text-gray-500">Hubtel</p>
                                <p class="font-semibold text-gray-900">{{ $hubtel }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Expected Total --}}
                    <div class="rounded-xl border border-gray-400 bg-gray-50 p-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-600 uppercase tracking-wider">Expected Total
                                </p>
                                <p class="mt-2 text-2xl font-bold text-gray-900">GH₵ {{ $expected }}</p>
                            </div>
                            <div class="shrink-0 rounded-lg bg-gray-100 p-2">
                                <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 14l2-2 4 4m0 0l4-4m-4 4V7" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4 rounded-lg border border-gray-100 bg-white p-3 text-sm">
                            <p class="text-gray-600">
                                Based on product sales and unit prices.
                            </p>
                        </div>
                    </div>



                    {{-- Difference --}}
                    @php
                        $collected = $selectedRecord['total_money'] ?? 0;
                        $expectedVal = $selectedRecord['total_revenue'] ?? 0;
                        $difference = $collected - $expectedVal;
                        $diffFormatted = number_format($difference, 2);

                        // Decide color classes and icons
                        if ($difference > 0) {
                            $iconBg = 'bg-green-100';
                            $iconColor = 'text-green-700';
                            $labelColor = 'text-green-700';
                            $iconPath = 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'; // Trending up icon
                        } elseif ($difference < 0) {
                            $iconBg = 'bg-rose-100';
                            $iconColor = 'text-rose-700';
                            $labelColor = 'text-rose-700';
                            $iconPath = 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6'; // Trending down icon
                        } else {
                            $iconBg = 'bg-purple-100';
                            $iconColor = 'text-purple-700';
                            $labelColor = 'text-purple-700';
                            $iconPath = 'M5 12h14'; // Minus/equal icon
                        }
                    @endphp
                    <div class="rounded-xl border border-slate-400 bg-slate-50 p-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-medium {{ $labelColor }} uppercase tracking-wider">Difference
                                </p>
                                <p class="mt-2 text-2xl font-bold text-slate-900">
                                    GH₵ {{ $diffFormatted }}
                                </p>
                            </div>
                            <div class="shrink-0 rounded-lg {{ $iconBg }} p-2">
                                <svg class="w-6 h-6 {{ $iconColor }}" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4 rounded-lg border border-slate-100 bg-white p-3 text-sm">
                            <p class="{{ $labelColor }}">
                                @if ($difference > 0)
                                    Surplus: Collected more than expected.
                                @elseif($difference < 0)
                                    Deficit: Collected less than expected.
                                @else
                                    Perfect match: Collected equals expected.
                                @endif
                            </p>
                        </div>
                    </div>


                    {{-- Profit --}}
                    <div class="rounded-xl border border-amber-400 bg-amber-50 p-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-medium text-amber-700 uppercase tracking-wider">Total Profit</p>
                                <p class="mt-2 text-2xl font-bold text-amber-900">GH₵ {{ $profit }}</p>
                            </div>
                            <div class="shrink-0 rounded-lg bg-amber-100 p-2">
                                <svg class="w-6 h-6 text-amber-700" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M17 6a8 8 0 1 0 0 12" stroke-linecap="round" stroke-linejoin="round" />
                                    <line x1="12" y1="2" x2="12" y2="22"
                                        stroke-linecap="round" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-3 text-xs text-amber-700">
                            {{-- <span class="inline-flex items-center ">After
                                adjustments</span> --}}
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-600">Damages & credits
                                accounted for</span>
                        </div>
                    </div>
                </div>

                {{-- Group 2: Payment Breakdown & Adjustments --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
                    {{-- Payment Breakdown --}}
                    <div class="rounded-xl border border-blue-400 bg-blue-50 p-5">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-blue-900">Payment Breakdown</h4>
                            <span class="text-xs text-blue-700 bg-blue-100 px-2 py-1 rounded-full">Channels</span>
                        </div>
                        <div class="mt-4 divide-y divide-blue-100 bg-white rounded-lg border border-blue-100">
                            <div class="flex items-center justify-between p-3">
                                <span class="text-sm text-gray-600">Mobile Money</span>
                                <span class="font-semibold text-gray-900">{{ $momo }}</span>
                            </div>
                            <div class="flex items-center justify-between p-3">
                                <span class="text-sm text-gray-600">Cash</span>
                                <span class="font-semibold text-gray-900">{{ $cash }}</span>
                            </div>
                            <div class="flex items-center justify-between p-3">
                                <span class="text-sm text-gray-600">Hubtel</span>
                                <span class="font-semibold text-gray-900">{{ $hubtel }}</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-blue-50/50">
                                <span class="text-sm text-blue-700">Total Collected</span>
                                <span class="font-semibold text-blue-900">GH₵ {{ $money }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Adjustments (Losses, Credits, Damages) --}}
                    <div class="rounded-xl border border-rose-400 bg-rose-50 p-5">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-rose-900">Adjustments</h4>
                            <span class="text-xs text-rose-700 bg-rose-100 px-2 py-1 rounded-full">Affecting
                                Profit</span>
                        </div>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="rounded-lg bg-white border border-rose-100 p-4">
                                <p class="text-xs text-gray-500">Losses</p>
                                <p class="mt-1 text-lg font-semibold text-rose-700">GH₵ {{ $lossAmt }}</p>
                            </div>
                            <div class="rounded-lg bg-white border border-rose-100 p-4">
                                <p class="text-xs text-gray-500">Credited Amount</p>
                                <p class="mt-1 text-lg font-semibold text-rose-700">GH₵ {{ $creditAmt }}</p>
                            </div>
                            <div class="rounded-lg bg-white border border-rose-100 p-4">
                                <p class="text-xs text-gray-500">Credited Items</p>
                                <p class="mt-1 text-lg font-semibold text-rose-700">{{ $creditU }} units</p>
                            </div>
                        </div>
                        <div class="mt-3 rounded-lg bg-white border border-rose-100 p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-gray-600">Damaged Units</p>
                                <p class="text-sm font-semibold text-rose-700">{{ $damagedU }} units</p>
                            </div>
                        </div>
                    </div>
                </div>


                {{-- Product Breakdown --}}
                <div class="rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                        <h4 class="text-md font-semibold text-gray-900">Product Breakdown</h4>
                        <div class="text-sm text-gray-600">
                            <span class="mr-4">Units: <span
                                    class="font-semibold text-gray-900">{{ number_format($sumUnits) }}</span></span>
                            <span class="mr-4">Boxes: <span
                                    class="font-semibold text-gray-900">{{ number_format($sumBoxes) }}</span></span>
                            <span>Total: <span class="font-semibold text-gray-900">GH₵
                                    {{ number_format($sumRevenue, 2) }}</span></span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Product</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Category</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Opening (u)</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Closing (u)</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Opening (bx)</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Closing (bx)</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Units Sold</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Boxes Sold</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Damaged</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Credited</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Loss</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Credited Amount</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Revenue (GH₵)</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white uppercase divide-y divide-gray-100">
                                @forelse($selectedRecord['products'] ?? [] as $sale)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $sale['product_name'] }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-700">{{ $sale['category'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ number_format($sale['opening_stock']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ number_format($sale['closing_stock']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ number_format($sale['opening_boxes']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ number_format($sale['closing_boxes']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                            {{ number_format($sale['units_sold']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                            {{ number_format($sale['boxes_sold']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                            {{ number_format($sale['damaged_units']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                            {{ number_format($sale['credit_units']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">GH₵
                                            {{ number_format($sale['loss_amount'] ?? 0, 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">GH₵
                                            {{ number_format($sale['credit_amount'] ?? 0, 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">GH₵
                                            {{ number_format($sale['revenue'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="13" class="px-6 py-6 text-center text-gray-500">No detailed
                                            sales found for this date.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex justify-end mt-6">
                    <button wire:click="closeDetailsModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>

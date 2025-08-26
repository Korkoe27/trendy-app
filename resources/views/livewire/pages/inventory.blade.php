<div class="space-y-6">
    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
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
                <input
                    type="date"
                    wire:model="selectedDate"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
                <button
                    wire:click="openTakeInventoryModal"
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center space-x-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cash Sales</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mobile Money</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hubtel</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items Sold</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($dailySalesRecords as $record)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
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
                                <div class="text-sm font-medium text-green-600">${{ number_format($record->total_cash, 2) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-blue-600">${{ number_format($record->total_momo, 2) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-purple-600">${{ number_format($record->total_hubtel, 2) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-900">${{ number_format($record->total_revenue, 2) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $record->total_products }} items</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button
                                    wire:click="openDetailsModal({{ $record->first_id }})"
                                    class="text-blue-600 cursor-pointer hover:text-blue-900 flex items-center space-x-1"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    <span>View Details</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">No sales records found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Take Inventory Modal --}}
    @if($showTakeInventoryModal)
        <div class="fixed inset-0 bg-black/50 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Take Inventory - {{ \Carbon\Carbon::parse($selectedDate)->format('M j, Y') }}
                    </h3>
                    <button wire:click="closeTakeInventoryModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
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
                                ['number' => 4, 'title' => 'Stock Count', 'icon' => 'package']
                            ];
                        @endphp
                        @foreach($steps as $index => $step)
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 {{
                                    $currentStep > $step['number']
                                        ? 'bg-green-600 border-green-600 text-white'
                                        : ($currentStep == $step['number']
                                            ? 'bg-blue-600 border-blue-600 text-white'
                                            : 'border-gray-300 text-gray-400')
                                }}">
                                    @if($currentStep > $step['number'])
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    @else
                                        @if($step['icon'] == 'dollar')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                            </svg>
                                        @elseif($step['icon'] == 'phone')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                            </svg>
                                        @elseif($step['icon'] == 'credit-card')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2z"></path>
                                            </svg>
                                        @else
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                            </svg>
                                        @endif
                                    @endif
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium {{
                                        $currentStep == $step['number']
                                            ? 'text-blue-600'
                                            : ($currentStep > $step['number'] ? 'text-green-600' : 'text-gray-400')
                                    }}">
                                        {{ $step['title'] }}
                                    </p>
                                </div>
                                @if($index < count($steps) - 1)
                                    <div class="flex-1 h-0.5 mx-4 {{ $currentStep > $step['number'] ? 'bg-green-600' : 'bg-gray-300' }}"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Step Content --}}
                <div class="mb-8">
                    @if($currentStep == 1)
                        <div wire:key="cash-step" class="space-y-4">
                            <div class="text-center mb-6">
                                <svg class="w-12 h-12 text-green-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900">Cash Sales</h3>
                                <p class="text-sm text-gray-600">Enter the total cash amount collected today</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Cash Amount ($)</label>
                                <input
                                    type="number" step="0.01" wire:model.defer="cashAmount" placeholder="0.00"
                                    class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-center"
                                />
                                @error('cashAmount') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @elseif($currentStep == 2)
                        <div wire:key="momo-step" class="space-y-4">
                            <div class="text-center mb-6">
                                <svg class="w-12 h-12 text-blue-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900">Mobile Money Sales</h3>
                                <p class="text-sm text-gray-600">Enter the total mobile money amount collected today</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Mobile Money Amount ($)</label>
                                <input
                                    type="number" step="0.01" wire:model.defer="momoAmount" placeholder="0.00"
                                    class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-center"
                                />
                                @error('momoAmount') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @elseif($currentStep == 3)
                        <div wire:key="hubtel-step" class="space-y-4">
                            <div class="text-center mb-6">
                                <svg class="w-12 h-12 text-purple-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2z"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900">Hubtel Sales</h3>
                                <p class="text-sm text-gray-600">Enter the total Hubtel amount collected today</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-2">Hubtel Amount ($)</label>
                                <input
                                    type="number" step="0.01" wire:model.defer="hubtelAmount" placeholder="0.00"
                                    class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-center"
                                />
                                @error('hubtelAmount') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @elseif($currentStep == 4)
                        <div wire:key="stock-step" class="space-y-4">
                            <div class="text-center mb-6">
                                <svg class="w-12 h-12 text-orange-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900">Stock Count</h3>
                                <p class="text-sm text-gray-600">Enter the remaining stock for each product</p>
                            </div>

                            <div class="max-h-96 overflow-y-auto">
                                <div class="space-y-4">
                                    @foreach($products as $product)
                                        @php
                                            $currentStock = $product->stocks;
                                            $currentTotalUnits = $currentStock ? $currentStock->total_units : 0;
                                            $currentBoxes = $product->units_per_box > 0 ? floor($currentTotalUnits / $product->units_per_box) : 0;
                                            $remainingUnits = $currentTotalUnits - ($currentBoxes * ($product->units_per_box ?? 1));
                                        @endphp
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex items-center justify-between mb-3">
                                                <div>
                                                    <h4 class="font-medium text-gray-900">{{ $product->name }}</h4>
                                                    <p class="text-sm text-gray-500">{{ $product->category->name ?? 'N/A' }}</p>
                                                </div>
                                                <div class="text-right text-sm text-gray-600">
                                                    <div>Current: {{ $currentTotalUnits }} units</div>
                                                    <div>Boxes: {{ $currentBoxes }} ({{ $remainingUnits }} loose units)</div>
                                                    <div class="text-xs text-gray-500">{{ $product->units_per_box ?? 1 }} units/box</div>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Closing Boxes</label>
                                                    <input
                                                        type="number" min="0"
                                                        wire:model="productStocks.{{ $product->id }}.closing_boxes"
                                                        placeholder="0"
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Closing Units</label>
                                                    <input
                                                        type="number" min="0"
                                                        wire:model="productStocks.{{ $product->id }}.closing_units"
                                                        placeholder="0"
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Total Units</label>
                                                    <div class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-md text-gray-900 font-medium">
                                                        {{ $this->calculateTotalUnits($product->id) }}
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Expected Revenue</label>
                                                    <div class="w-full px-3 py-2 text-sm bg-green-50 border border-green-200 rounded-md text-green-700 font-medium">
                                                        ${{ number_format($this->calculateExpectedRevenue($product->id), 2) }}
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
                        @if($currentStep > 1)
                            <button
                                wire:click="previousStep"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                Previous
                            </button>
                        @endif
                    </div>

                    <div class="flex space-x-3">
                        <button
                            wire:click="closeTakeInventoryModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            Cancel
                        </button>

                        @if($currentStep < 4)
                            <button
                                wire:click="nextStep"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                Next
                            </button>
                        @else
                            <button
                                wire:click="submitInventory"
                                class="px-6 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 flex items-center space-x-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Submit Inventory</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Details Modal --}}
    @if($showDetailsModal && $selectedRecord)
        <div class="fixed inset-0 bg-black/50 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-6xl mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            Sales Details - {{ \Carbon\Carbon::parse($selectedRecord['date'])->format('M j, Y') }}
                        </h3>
                        <p class="text-sm text-gray-600">Complete breakdown of daily sales and inventory</p>
                    </div>
                    <button wire:click="closeDetailsModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                {{-- Summary Cards --}}
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                    {{-- Cash --}}
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-green-600">Cash Sales</p>
                                <p class="text-lg font-semibold text-green-900">
                                    ${{ number_format($selectedRecord['total_cash'] ?? 0, 2) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Mobile Money --}}
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-blue-600">Mobile Money</p>
                                <p class="text-lg font-semibold text-blue-900">
                                    ${{ number_format($selectedRecord['total_momo'] ?? 0, 2) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Hubtel --}}
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2z"></path>
                            </svg>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-purple-600">Hubtel</p>
                                <p class="text-lg font-semibold text-purple-900">
                                    ${{ number_format($selectedRecord['total_hubtel'] ?? 0, 2) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Total Revenue --}}
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-8 h-8 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l2-2 4 4m0 0l4-4m-4 4V7"></path>
                            </svg>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                                <p class="text-lg font-semibold text-gray-900">
                                    ${{ number_format($selectedRecord['total_revenue'] ?? 0, 2) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Total Profit --}}
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-amber-600">Total Profit</p>
                                <p class="text-lg font-semibold text-amber-900">
                                    ${{ number_format($selectedRecord['total_profit'] ?? 0, 2) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Products Table --}}
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h4 class="text-md font-semibold text-gray-900">Product Breakdown</h4>
                        @php
                            $sumUnits = collect($selectedRecord['products'] ?? [])->sum('units_sold');
                            $sumBoxes = collect($selectedRecord['products'] ?? [])->sum('boxes_sold');
                            $sumRevenue = collect($selectedRecord['products'] ?? [])->sum('revenue');
                        @endphp
                        <div class="text-sm text-gray-600">
                            <span class="mr-4">Units sold: <span class="font-semibold text-gray-900">{{ number_format($sumUnits) }}</span></span>
                            <span class="mr-4">Boxes sold: <span class="font-semibold text-gray-900">{{ number_format($sumBoxes) }}</span></span>
                            <span>Total: <span class="font-semibold text-gray-900">${{ number_format($sumRevenue, 2) }}</span></span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Opening (u)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Closing (u)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Opening (bx)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Closing (bx)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units Sold</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Boxes Sold</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue ($)</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($selectedRecord['products'] ?? [] as $sale)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $sale['product_name'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-700">{{ $sale['category'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($sale['opening_stock']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($sale['closing_stock']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($sale['opening_boxes']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($sale['closing_boxes']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">{{ number_format($sale['units_sold']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">{{ number_format($sale['boxes_sold']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">${{ number_format($sale['revenue'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-6 py-4 text-center text-gray-500">No detailed sales found for this date.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(($selectedRecord['products'] ?? []))
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <th colspan="6" class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">Totals</th>
                                        <th class="px-6 py-3 text-sm font-semibold text-gray-900">{{ number_format($sumUnits) }}</th>
                                        <th class="px-6 py-3 text-sm font-semibold text-gray-900">{{ number_format($sumBoxes) }}</th>
                                        <th class="px-6 py-3 text-sm font-semibold text-gray-900">${{ number_format($sumRevenue, 2) }}</th>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex justify-end mt-6">
                    <button
                        wire:click="closeDetailsModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

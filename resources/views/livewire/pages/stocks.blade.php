<!-- resources/views/livewire/pages/stocks.blade.php -->
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
                <h2 class="text-xl font-semibold text-gray-900">Stock Management</h2>
                <p class="text-base text-gray-600 mt-1">Track available stock levels</p>
            </div>

@haspermission('create', 'stocks')
    <div class="flex space-x-3">
        <button wire:click="showAddNewStockModal"
            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-800 transition-colors flex items-center space-x-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            <span>Add New Stock</span>
        </button>
        
        <button wire:click="$set('showExportModal', true)"
            class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition-colors flex items-center space-x-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            <span>Export Stocks</span>
        </button>
    </div>
@endhaspermission
        </div>

        <!-- Search and Filter -->
        <div class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4 mt-6">
            <div class="flex-1 relative">
                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text" placeholder="Search products, SKU, or barcode..." wire:model.live="searchTerm"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            <select wire:model.live="selectedCategory"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="all">All Categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->name }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Stock Table -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Product</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Boxes</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Units</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Supplier</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Cost Price</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Profit Margin</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Restock Date</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($stocks as $stock)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    <div>
                                        <div class="text-base font-medium uppercase text-gray-900">
                                            {{ $stock->product->name }}</div>
                                        <div class="text-base uppercase text-gray-500">
                                            {{ $stock->product->category->name }} • {{ $stock->product->sku ?? 'N/A' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base text-gray-900">
                                    {{ $stock->product->units_per_box > 0 ? number_format($stock->total_units / $stock->product->units_per_box, 1) : 'N/A' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base text-gray-900">{{ number_format($stock->total_units, 0) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base uppercase text-gray-900">{{ $stock->supplier ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base text-gray-900">
                                    @if ($stock->cost_price)
                                        GH₵ {{ number_format($stock->cost_price, 2) }}
                                    @else
                                        N/A
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base">
                                    @if ($stock->cost_margin)
                                        <span
                                            class="text-{{ $stock->cost_margin >= 0 ? 'green' : 'red' }}-600 font-semibold">
                                            GH₵ {{ number_format($stock->cost_margin, 2) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base text-gray-900">
                                    {{ $stock->restock_date ? \Carbon\Carbon::parse($stock->restock_date)->format('M j, Y') : 'N/A' }}
                                </div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-base font-medium">
                                <div class="flex space-x-2">
                                    <button wire:click="viewStock({{ $stock->id }})"
                                        class="text-blue-600 hover:text-blue-800" title="View Stock">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>


                                    @haspermission('modify', 'stocks')
                                        <button wire:click="editStock({{ $stock->id }})"
                                            class="text-green-600 hover:text-green-800" title="Edit Stock">
                                            {{-- <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5h2m2 0h3m-3 0v3m0-3v3m-6 8h6m2 0h3m-3 0v3m0-3v3" />
                                        </svg> --}}
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 4h2m-6.586 9.414l8.586-8.586a2 2 0 112.828 2.828l-8.586 8.586H7v-2.828zM5 19h14" />
                                            </svg>
                                        </button>
                                    @endhaspermission



                                    @haspermission('delete', 'stocks')
                                        <button wire:click="deleteStockEntry({{ $stock->product_id }})"
                                            wire:confirm="Are you sure you want to delete this stock entry?"
                                            class="text-red-600 hover:text-red-900" title="Delete Stock">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
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
                            <td colspan="8"
                                class="px-6 py-4 whitespace-nowrap text-base text-gray-500 text-center">
                                No stock entries found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $stocks->links() }}
        </div>
    </div>

    <!-- Add New Stock Modal -->
    @if ($addNewStockModal)
        <div class="fixed inset-0 bg-black/50 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-7xl mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-900">Add New Stock</h3>

                    <div class="flex items-center space-x-10">

                        <input type="date" wire:model.live="restockDate" max="{{ date('Y-m-d') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required />
                        <button wire:click="closeAddStockModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <form wire:submit.prevent="saveNewStock">
                    <!-- Stock Items Section -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <label class="block text-sm font-medium text-gray-700">
                                Stock Items <span class="text-red-400">*</span>
                            </label>
                            <button type="button" wire:click="addStockItem"
                                class="text-blue-600 hover:text-blue-800 font-medium text-sm flex items-center space-x-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4"></path>
                                </svg>
                                <span>Add Item</span>
                            </button>
                        </div>

                        <!-- Stock Items Table -->
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Product</th>
                                        {{-- <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Boxes</th> --}}
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Units</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Free Units</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total Cost*</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Supplier*</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Cost Price</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Profit Margin</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($newStockItems as $index => $item)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <select wire:model.live="newStockItems.{{ $index }}.product_id"
                                                    class="w-full px-3 py-2 border border-gray-300 capitalize rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                    required>
                                                    <option value="">Select Product</option>
                                                    @foreach ($products as $product)
                                                        <option value="{{ $product->id }}"
                                                            class="uppercase font-semibold">
                                                            {{ $product->name }} - {{ $product->category->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('newStockItems.' . $index . '.product_id')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            {{-- <td class="px-4 py-3">
                                                <input 
                                                    type="number" 
                                                    step="0.1"
                                                    placeholder="0.0"
                                                    wire:model.live="newStockItems.{{ $index }}.input_boxes"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                    min="0"
                                                />
                                                @error('newStockItems.' . $index . '.input_boxes')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td> --}}
                                            <td class="px-4 py-3">
                                                <input type="number" placeholder="0"
                                                    wire:model.live="newStockItems.{{ $index }}.input_units"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                    min="0" />
                                                @error('newStockItems.' . $index . '.input_units')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="number" placeholder="0"
                                                    wire:model.live="newStockItems.{{ $index }}.free_units"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                    min="0" />
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="number" step="0.01" placeholder="0.00"
                                                    wire:model.live="newStockItems.{{ $index }}.total_cost"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                    min="0" required />
                                                @error('newStockItems.' . $index . '.total_cost')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="text" placeholder="Supplier name"
                                                    wire:model.live="newStockItems.{{ $index }}.supplier"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                    required />
                                                @error('newStockItems.' . $index . '.supplier')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="text-sm text-gray-600 text-center">
                                                    @if (isset($item['calculated_cost_price']) && $item['calculated_cost_price'])
                                                        ₵{{ number_format($item['calculated_cost_price'], 2) }}
                                                    @else
                                                        -
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="text-sm font-medium text-center">
                                                    @if (isset($item['calculated_profit_margin']) && $item['calculated_profit_margin'] !== '')
                                                        <span
                                                            class="text-{{ $item['calculated_profit_margin'] >= 0 ? 'green' : 'red' }}-600">
                                                            ₵{{ number_format($item['calculated_profit_margin'], 2) }}
                                                        </span>
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if (count($newStockItems) > 1)
                                                    <button type="button"
                                                        wire:click="removeStockItem({{ $index }})"
                                                        class="text-red-600 hover:text-red-900">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                            </path>
                                                        </svg>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                                No items added yet. Click "Add Item" to start.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @error('newStockItems')
                            <div class="text-red-500 text-sm mt-2">{{ $message }}</div>
                        @enderror

                        <!-- Total Summary -->
                        @if (!empty($newStockItems))
                            @php
                                $totalUnitsAcrossAllItems = 0;
                                $totalCostAcrossAllItems = 0;
                                foreach ($newStockItems as $item) {
                                    if (isset($item['calculated_total_units']) && $item['calculated_total_units'] > 0) {
                                        $totalUnitsAcrossAllItems += $item['calculated_total_units'];
                                    }
                                    if (isset($item['total_cost']) && $item['total_cost'] > 0) {
                                        $totalCostAcrossAllItems += floatval($item['total_cost']);
                                    }
                                }
                            @endphp
                            @if ($totalUnitsAcrossAllItems > 0 || $totalCostAcrossAllItems > 0)
                                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <h4 class="font-semibold text-blue-900">Summary</h4>
                                        <div class="flex space-x-6 text-sm">
                                            <div class="text-blue-700">
                                                <span class="font-medium">Total Units:</span>
                                                <span
                                                    class="font-bold text-blue-900">{{ number_format($totalUnitsAcrossAllItems, 0) }}</span>
                                            </div>
                                            <div class="text-blue-700">
                                                <span class="font-medium">Total Cost:</span>
                                                <span
                                                    class="font-bold text-blue-900">₵{{ number_format($totalCostAcrossAllItems, 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>

                    <!-- Notes Section -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Notes
                        </label>
                        <textarea wire:model="notes" rows="3" placeholder="Add any notes about this stock entry..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"></textarea>
                        @error('notes')
                            <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end space-x-4 pt-4 border-t border-gray-200">
                        <button type="button" wire:click="closeAddStockModal"
                            class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Save Stock</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
    @if ($viewStockModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Stock Details</h3>
                    <button wire:click="closeViewStockModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                @if ($selectedStock)
                    <div class="space-y-4 capitalize text-gray-700">
                        <p><strong>Product:</strong> {{ $selectedStock->product->name }}</p>
                        <p><strong>Category:</strong> {{ $selectedStock->product->category->name }}</p>
                        <p><strong>Supplier:</strong> {{ $selectedStock->supplier ?? 'N/A' }}</p>
                        <p><strong>Total Units:</strong> {{ number_format($selectedStock->total_units, 0) }}</p>
                        <p><strong>Cost Price:</strong> GH₵{{ number_format($selectedStock->cost_price, 2) }}</p>
                        <p><strong>Profit Margin:</strong> GH₵ {{ number_format($selectedStock->cost_margin, 2) }}</p>
                        <p><strong>Restock Date:</strong>
                            {{ \Carbon\Carbon::parse($selectedStock->restock_date)->format('M j, Y') }}</p>
                        <p><strong>Notes:</strong> {{ $selectedStock->notes ?? 'No notes available' }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
    @if ($editStockModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-900">Edit Stock Entry</h3>
                    <button wire:click="closeEditStockModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                @if ($selectedStock)
                    <form wire:submit.prevent="updateStock">
                        <div class="space-y-4">
                            <!-- Product (read-only) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                                <input type="text" value="{{ $selectedStock->product->name }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg uppercase bg-gray-100 text-gray-600 cursor-not-allowed"
                                    readonly />
                            </div>

                            <!-- Supplier -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Supplier *</label>
                                <input type="text" wire:model.live="editStockItem.supplier"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg uppercase focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Supplier name" required />
                                @error('editStockItem.supplier')
                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Total Units -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Total Units *</label>
                                <input type="number" step="1" wire:model.live="editStockItem.input_units"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="0" min="0" required />
                                @error('editStockItem.input_units')
                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <!-- Free Units -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Free Units</label>
                                <input type="number" step="1" wire:model.live="editStockItem.free_units"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Free or discounted units" min="0" />
                                @error('editStockItem.free_units')
                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Total Cost -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Total Cost (₵) *</label>
                                <input type="number" step="0.01" wire:model.live="editStockItem.total_cost"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="0.00" min="0" required />
                                @error('editStockItem.total_cost')
                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Cost Price (Calculated) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cost Price (₵)</label>
                                <div
                                    class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                                    @if (isset($editStockItem['calculated_cost_price']) && $editStockItem['calculated_cost_price'])
                                        ₵{{ number_format($editStockItem['calculated_cost_price'], 2) }}
                                    @else
                                        ₵0.00
                                    @endif
                                </div>
                            </div>

                            <!-- Profit Margin (Calculated) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Profit Margin (₵)</label>
                                <div class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50">
                                    @if (isset($editStockItem['calculated_profit_margin']) && $editStockItem['calculated_profit_margin'] !== '')
                                        <span
                                            class="text-{{ $editStockItem['calculated_profit_margin'] >= 0 ? 'green' : 'red' }}-600 font-semibold">
                                            ₵{{ number_format($editStockItem['calculated_profit_margin'], 2) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">₵0.00</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Restock Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Restock Date *</label>
                                <input type="date" wire:model.live="editRestockDate"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    max="{{ date('Y-m-d') }}" required />
                                @error('editRestockDate')
                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                <textarea wire:model="editNotes" rows="3"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Add any notes about this stock entry..."></textarea>
                                @error('editNotes')
                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Modal Actions -->
                        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200 mt-6">
                            <button type="button" wire:click="closeEditStockModal"
                                class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Save Changes</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
        <!-- Export Filter Modal -->
@if ($showExportModal)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold">Export Stocks - Advanced Filters</h3>
                <button wire:click="$set('showExportModal', false)" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Category Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select wire:model="exportFilters.category_id" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="all">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Supplier Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Supplier</label>
                    <input type="text" wire:model="exportFilters.supplier" 
                        placeholder="Search by supplier name"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Stock Units Range -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Stock Units Range</label>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="number" wire:model="exportFilters.stock_min" 
                            placeholder="Min units" min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <input type="number" wire:model="exportFilters.stock_max" 
                            placeholder="Max units" min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Cost Price Range -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cost Price Range (GH₵)</label>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="number" wire:model="exportFilters.cost_price_min" 
                            placeholder="Min cost price" step="0.01" min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <input type="number" wire:model="exportFilters.cost_price_max" 
                            placeholder="Max cost price" step="0.01" min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Total Cost Range -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Total Stock Cost Range (GH₵)</label>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="number" wire:model="exportFilters.total_cost_min" 
                            placeholder="Min total cost" step="0.01" min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <input type="number" wire:model="exportFilters.total_cost_max" 
                            placeholder="Max total cost" step="0.01" min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Profit Margin Range -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Profit Margin Range (GH₵)</label>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="number" wire:model="exportFilters.margin_min" 
                            placeholder="Min margin" step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <input type="number" wire:model="exportFilters.margin_max" 
                            placeholder="Max margin" step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Restock Date Range -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Restock Date Range</label>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="date" wire:model="exportFilters.restock_date_from" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <input type="date" wire:model="exportFilters.restock_date_to" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Has Notes Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <select wire:model="exportFilters.has_notes" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="all">All Entries</option>
                        <option value="yes">With Notes Only</option>
                        <option value="no">Without Notes</option>
                    </select>
                </div>
            </div>

            <!-- Active Filters Summary -->
            @php
                $activeFilters = collect($exportFilters)->filter(function($value, $key) {
                    return $value !== 'all' && $value !== '' && $value !== null;
                })->count();
            @endphp
            
            @if($activeFilters > 0)
                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                            <span class="text-sm font-medium text-blue-900">
                                {{ $activeFilters }} filter(s) active
                            </span>
                        </div>
                        <button wire:click="resetExportFilters" 
                            class="text-sm text-blue-600 hover:text-blue-800 font-medium">
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
                    <button wire:click="exportStocks" 
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

</div>

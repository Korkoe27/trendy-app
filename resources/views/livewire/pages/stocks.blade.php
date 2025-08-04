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
            <div class="flex space-x-3">
                <button 
                    wire:click="showAddNewStockModal"
                    class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-800 transition-colors flex items-center space-x-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>Add New Stock</span>
                </button>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4 mt-6">
            <div class="flex-1 relative">
                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input
                    type="text"
                    placeholder="Search products, SKU, or barcode..."
                    wire:model.live="searchTerm"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
            </div>
            <select
                wire:model.live="selectedCategory"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
                <option value="all">All Categories</option>
                @foreach($categories as $category)
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
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Boxes</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Units</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Supplier</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Cost Price</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Profit Margin</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Notes</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($stocks as $stock)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    <div>
                                        <div class="text-base font-medium uppercase text-gray-900">{{ $stock->product->name }}</div>
                                        <div class="text-base uppercase text-gray-500">{{ $stock->product->category->name }} • {{ $stock->product->sku ?? 'N/A' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base text-gray-900">{{ number_format($stock->available_boxes, 1) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base text-gray-900">{{ number_format($stock->available_units, 0) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base text-gray-900">{{ $stock->supplier ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base text-gray-900">
                                    @if($stock->cost_price)
                                        ₵{{ number_format($stock->cost_price, 2) }}
                                    @else
                                        N/A
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base">
                                    @if($stock->cost_margin)
                                        <span class="text-{{ $stock->cost_margin >= 0 ? 'green' : 'red' }}-600 font-semibold">
                                            ₵{{ number_format($stock->cost_margin, 2) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-500 max-w-xs truncate">{{ $stock->notes ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-base font-medium">
                                <div class="flex space-x-2">
                                    <button 
                                        wire:click="deleteStockEntry({{ $stock->product_id }})"
                                        wire:confirm="Are you sure you want to delete this stock entry?"
                                        class="text-red-600 hover:text-red-900">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-4 whitespace-nowrap text-base text-gray-500 text-center">
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
                    <button wire:click="closeAddStockModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="saveNewStock">
                    <!-- Stock Items Section -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <label class="block text-sm font-medium text-gray-700">
                                Stock Items <span class="text-red-400">*</span>
                            </label>
                            <button 
                                type="button"
                                wire:click="addStockItem"
                                class="text-blue-600 hover:text-blue-800 font-medium text-sm flex items-center space-x-1"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                <span>Add Item</span>
                            </button>
                        </div>

                        <!-- Stock Items Table -->
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Boxes</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost Price*</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier*</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Cost</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit Margin</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($newStockItems as $index => $item)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <select 
                                                    wire:model.live="newStockItems.{{ $index }}.product_id"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                    required
                                                >
                                                    <option value="">Select Product</option>
                                                    @foreach($products as $product)
                                                        <option value="{{ $product->id }}">
                                                            {{ $product->name }} - {{ $product->category->name }}
                                                            @if($product->sku)
                                                                ({{ $product->sku }})
                                                            @endif
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('newStockItems.' . $index . '.product_id')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="number" 
                                                    step="0.1"
                                                    placeholder="0.0"
                                                    wire:model.live="newStockItems.{{ $index }}.available_boxes"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm {{ isset($item['boxes_disabled']) && $item['boxes_disabled'] ? 'bg-gray-100 cursor-not-allowed' : '' }}"
                                                    min="0"
                                                    {{ isset($item['boxes_disabled']) && $item['boxes_disabled'] ? 'disabled' : '' }}
                                                />
                                                @error('newStockItems.' . $index . '.available_boxes')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="number" 
                                                    placeholder="0"
                                                    wire:model.live="newStockItems.{{ $index }}.available_units"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm {{ isset($item['units_disabled']) && $item['units_disabled'] ? 'bg-gray-100 cursor-not-allowed' : '' }}"
                                                    min="0"
                                                    {{ isset($item['units_disabled']) && $item['units_disabled'] ? 'disabled' : '' }}
                                                />
                                                @error('newStockItems.' . $index . '.available_units')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="number" 
                                                    step="0.01"
                                                    placeholder="0.00"
                                                    wire:model.live="newStockItems.{{ $index }}.cost_price"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                    min="0"
                                                    required
                                                />
                                                @error('newStockItems.' . $index . '.cost_price')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="text" 
                                                    placeholder="Supplier name"
                                                    wire:model.live="newStockItems.{{ $index }}.supplier"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                />
                                                @error('newStockItems.' . $index . '.supplier')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="text-sm text-gray-600">
                                                    @if(isset($item['unit_cost']) && $item['unit_cost'])
                                                        ₵{{ number_format($item['unit_cost'], 2) }}
                                                    @else
                                                        -
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="text-sm font-medium">
                                                    @if(isset($item['profit_margin']) && $item['profit_margin'] !== '')
                                                        <span class="text-{{ $item['profit_margin'] >= 0 ? 'green' : 'red' }}-600">
                                                            ₵{{ number_format($item['profit_margin'], 2) }}
                                                        </span>
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                @if(count($newStockItems) > 1)
                                                    <button 
                                                        type="button"
                                                        wire:click="removeStockItem({{ $index }})"
                                                        class="text-red-600 hover:text-red-900"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
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
                    </div>

                    <!-- Notes Section -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Notes
                        </label>
                        <textarea 
                            wire:model="notes"
                            rows="3"
                            placeholder="Add any notes about this stock entry..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                        ></textarea>
                        @error('notes')
                            <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end space-x-4 pt-4 border-t border-gray-200">
                        <button 
                            type="button"
                            wire:click="closeAddStockModal"
                            class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center space-x-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Save Stock</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
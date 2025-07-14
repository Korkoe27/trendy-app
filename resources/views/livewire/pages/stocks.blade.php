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
                <p class="text-base text-gray-600 mt-1">Track daily stock levels and sales</p>
            </div>
            <div class="flex space-x-3">
                <input 
                    type="date" 
                    wire:model.live="selectedDate"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                <button 
                    wire:click="openTakeStockModal"
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center space-x-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>Take Stock</span>
                </button>
                <button 
                    wire:click="addNewStockModal"
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
                    placeholder="Search products or SKU..."
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
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Opening Stock</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Added</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Closing Stock</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Sales</th>
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
                                <div class="text-base text-gray-900">
                                    <div>Units: {{ $stock->opening_units }}</div>
                                    <div class="text-sm text-gray-500">Boxes: {{ $stock->opening_boxes }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base text-gray-900">{{ $stock->added_units }} units</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base text-gray-900">
                                    <div>Units: {{ $stock->closing_units }}</div>
                                    <div class="text-sm text-gray-500">Boxes: {{ number_format($stock->closing_boxes, 1) }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base font-medium text-green-600">
                                    <div>Units: {{ number_format($stock->sales_units, 1) }}</div>
                                    <div class="text-sm">Boxes: {{ number_format($stock->sales_boxes, 1) }}</div>
                                </div>
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
                            <td colspan="6" class="px-6 py-4 whitespace-nowrap text-base text-gray-500 text-center">
                                No stock entries found for {{ \Carbon\Carbon::parse($selectedDate)->format('M d, Y') }}.
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

    <!-- Take Stock Modal -->
    @if($showTakeStockModal)
        <div class="fixed inset-0 bg-black/50 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-6xl mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Take Stock - {{ \Carbon\Carbon::parse($selectedDate)->format('M d, Y') }}</h3>
                    <button wire:click="closeTakeStockModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Opening</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Added</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Closing</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sales</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($stockData as $productId => $data)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2">
                                        <div class="text-sm font-medium text-gray-900">{{ $data['product']->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $data['product']->category->name }}</div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="space-y-1">
                                            <input 
                                                type="number" 
                                                placeholder="Units"
                                                wire:model.blur="stockData.{{ $productId }}.opening_units"
                                                class="w-20 px-2 py-1 text-xs border border-gray-300 rounded"
                                            >
                                            <input 
                                                type="number" 
                                                placeholder="Boxes"
                                                step="0.1"
                                                wire:model.blur="stockData.{{ $productId }}.opening_boxes"
                                                class="w-20 px-2 py-1 text-xs border border-gray-300 rounded"
                                            >
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input 
                                            type="number" 
                                            placeholder="Added units"
                                            wire:model.blur="stockData.{{ $productId }}.added_units"
                                            class="w-24 px-2 py-1 text-xs border border-gray-300 rounded"
                                        >
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="space-y-1">
                                            <input 
                                                type="number" 
                                                placeholder="Units"
                                                wire:model.blur="stockData.{{ $productId }}.closing_units"
                                                class="w-20 px-2 py-1 text-xs border border-gray-300 rounded"
                                            >
                                            <input 
                                                type="number" 
                                                placeholder="Boxes"
                                                step="0.1"
                                                wire:model.blur="stockData.{{ $productId }}.closing_boxes"
                                                class="w-20 px-2 py-1 text-xs border border-gray-300 rounded"
                                            >
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="text-sm text-green-600">
                                            <div>{{ number_format($data['sales_units'], 1) }} units</div>
                                            <div class="text-xs">{{ number_format($data['sales_boxes'], 1) }} boxes</div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <button 
                                            wire:click="saveStockEntry({{ $productId }})"
                                            class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 {{ $data['has_stock_entry'] ? 'bg-green-600 hover:bg-green-700' : '' }}">
                                            {{ $data['has_stock_entry'] ? 'Update' : 'Save' }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button 
                        wire:click="closeTakeStockModal"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button 
                        wire:click="saveAllStockEntries"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Save All Entries
                    </button>
                </div>
            </div>
        </div>
    @endif


    @if ($addNewStockModal)

        <div class="fixed inset-0 bg-black/50 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-6xl mx-4 max-h-[90vh] overflow-y-auto">
                <h1 class="">heyyy</h1>
            </div>
        </div>
        
    @endif
</div>
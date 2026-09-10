<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Stock Movements</h2>
                <p class="text-sm text-gray-600 mt-1">Click a product to view its stock history and trend</p>
            </div>
        </div>

        {{-- Search / Filter --}}
        <div class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4 mt-6">
            <div class="flex-1 relative">
                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text" placeholder="Search products or SKU..." wire:model.live="searchTerm"
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

    {{-- Products Table --}}
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Products</h3>
            <span class="text-xs text-gray-500">{{ $products->total() ?? 0 }} products</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Last Movement</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($products as $product)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $product->name }}</div>
                                @if($product->sku)
                                    <div class="text-xs text-gray-500">SKU: {{ $product->sku }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                    {{ $product->category?->name ?? '—' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                @if($product->currentStock)
                                    <div class="text-sm font-semibold text-gray-900">
                                        {{ number_format($product->currentStock->total_units) }}
                                        <span class="text-xs font-normal text-gray-500">units</span>
                                    </div>
                                @else
                                    <span class="text-sm text-gray-400">No stock</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                @if($product->currentStock)
                                    <div class="text-sm text-gray-600">
                                        {{ \Carbon\Carbon::parse($product->currentStock->created_at)->format('M j, Y') }}
                                    </div>
                                @else
                                    <span class="text-sm text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <button wire:click="openTrendModal({{ $product->id }})"
                                    class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-blue-100 transition-colors cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v18h18m-16-5l4-4 3 3 5-5m2-2h-4m4 0v4"></path>
                                    </svg>
                                    View Trend
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                </svg>
                                <p class="text-gray-500 text-sm font-medium">No products found</p>
                                <p class="text-gray-400 text-xs mt-1">Try adjusting your search or filters</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $products->links() }}
            </div>
        @endif
    </div>

    {{-- Stock Trend Modal --}}
    @if($showTrendModal && $selectedProduct)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click.self="closeTrendModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl mx-4 max-h-[90vh] overflow-y-auto">
                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $selectedProduct->name }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            @if($selectedProduct->category)
                                {{ $selectedProduct->category->name }} •
                            @endif
                            {{ $productSummary['count'] ?? 0 }} stock movements
                        </p>
                    </div>
                    <button wire:click="closeTrendModal"
                        class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 hover:text-gray-700 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-6">
                    @if(empty($productMovements))
                        <div class="text-center py-12">
                            <svg class="w-14 h-14 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-gray-500 text-sm font-medium">No stock movements recorded for this product</p>
                        </div>
                    @else
                        {{-- Summary Stats --}}
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</p>
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                </div>
                                <p class="text-2xl font-bold text-gray-900 mt-2">{{ number_format($productSummary['current_stock'] ?? 0) }}</p>
                                <p class="text-xs text-gray-500 mt-1">units</p>
                            </div>

                            <div class="bg-green-50 rounded-lg border border-green-200 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-medium text-green-700 uppercase tracking-wider">Total Purchased</p>
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                </div>
                                <p class="text-2xl font-bold text-green-800 mt-2">{{ number_format($productSummary['total_purchased'] ?? 0) }}</p>
                                <p class="text-xs text-green-600 mt-1">units added</p>
                            </div>

                            <div class="bg-orange-50 rounded-lg border border-orange-200 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-medium text-orange-700 uppercase tracking-wider">Total Sold</p>
                                    <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h1.5a2 2 0 011.34.51l6.99 6.49a2 2 0 01.76 1.53V18a2 2 0 01-2 2H9a2 2 0 01-2-2v-6.47a2 2 0 01.76-1.53l6.99-6.49A2 2 0 0116.5 3H21"></path>
                                    </svg>
                                </div>
                                <p class="text-2xl font-bold text-orange-800 mt-2">{{ number_format($productSummary['total_sold'] ?? 0) }}</p>
                                <p class="text-xs text-orange-600 mt-1">units sold</p>
                            </div>

                            <div class="bg-amber-50 rounded-lg border border-amber-200 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-medium text-amber-700 uppercase tracking-wider">Damaged</p>
                                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                    </svg>
                                </div>
                                <p class="text-2xl font-bold text-amber-800 mt-2">{{ number_format($productSummary['total_damaged'] ?? 0, 1) }}</p>
                                <p class="text-xs text-amber-600 mt-1">units</p>
                            </div>
                        </div>

                        {{-- Stock Level Trend Chart --}}
                        <div class="bg-white rounded-lg border border-gray-200 p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-sm font-semibold text-gray-900">Stock Level Trend</h4>
                                <div class="flex items-center gap-4 text-xs text-gray-500">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-sm bg-green-500 inline-block"></span>
                                        Purchase
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-sm bg-blue-500 inline-block"></span>
                                        Inventory
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-sm bg-amber-500 inline-block"></span>
                                        Edit
                                    </span>
                                </div>
                            </div>

                            @php
                                $maxClosing = collect($productMovements)->max('closing');
                                $maxClosing = $maxClosing > 0 ? $maxClosing : 1;
                                $chartHeight = 160;
                                $movementCount = count($productMovements);
                                $labelStep = $movementCount > 12 ? ceil($movementCount / 12) : 1;
                            @endphp

                            <div class="flex items-end gap-1" style="height: {{ $chartHeight }}px;">
                                @foreach($productMovements as $index => $movement)
                                    <div class="flex-1 flex items-end justify-center h-full">
                                        <div
                                            class="w-full rounded-t transition-colors
                                                   @if($movement['type'] === 'purchase') bg-green-500
                                                   @elseif($movement['type'] === 'inventory') bg-blue-500
                                                   @else bg-amber-500 @endif"
                                            style="height: {{ max(5, round(($movement['closing'] / $maxClosing) * $chartHeight)) }}px;"
                                            title="{{ $movement['date_full'] }} — {{ number_format($movement['closing']) }} units ({{ ucfirst($movement['type']) }})">
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex gap-1 mt-2">
                                @foreach($productMovements as $index => $movement)
                                    <div class="flex-1 text-[9px] text-gray-400 text-center truncate">
                                        @if($index % $labelStep === 0 || $index === $movementCount - 1)
                                            {{ \Carbon\Carbon::parse($movement['date'])->format('M j') }}
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Movement History Table --}}
                        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-200">
                                <h4 class="text-sm font-semibold text-gray-900">Movement History</h4>
                            </div>
                            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                            <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Opening</th>
                                            <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Closing</th>
                                            <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Change</th>
                                            <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sold</th>
                                            <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Damaged</th>
                                            <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach(array_reverse($productMovements) as $movement)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">{{ $movement['date_full'] }}</div>
                                                    <div class="text-xs text-gray-400">{{ $movement['time'] }}</div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                           @if($movement['type'] === 'purchase') bg-green-100 text-green-700
                                                           @elseif($movement['type'] === 'inventory') bg-blue-100 text-blue-700
                                                           @else bg-amber-100 text-amber-700 @endif">
                                                        {{ ucfirst($movement['type']) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-600">
                                                    {{ $movement['opening'] > 0 ? number_format($movement['opening']) : '—' }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                                                    {{ number_format($movement['closing']) }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium
                                                       @if($movement['change'] > 0) text-green-600
                                                       @elseif($movement['change'] < 0) text-red-600
                                                       @else text-gray-400 @endif">
                                                    @if($movement['change'] > 0)
                                                        +{{ number_format($movement['change']) }}
                                                    @elseif($movement['change'] < 0)
                                                        {{ number_format($movement['change']) }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-600">
                                                    {{ $movement['units_sold'] !== null ? number_format($movement['units_sold']) : '—' }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-600">
                                                    {{ $movement['damaged'] !== null && $movement['damaged'] > 0 ? number_format($movement['damaged'], 1) : '—' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-500 max-w-[200px]">
                                                    @if($movement['supplier'])
                                                        <div class="text-xs">Supplier: {{ $movement['supplier'] }}</div>
                                                    @endif
                                                    @if($movement['credit'] && $movement['credit'] > 0)
                                                        <div class="text-xs text-purple-600">Credits: {{ number_format($movement['credit'], 1) }}</div>
                                                    @endif
                                                    @if($movement['notes'])
                                                        <div class="text-xs truncate" title="{{ $movement['notes'] }}">{{ $movement['notes'] }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
<!-- resources/views/livewire/pages/products.blade.php -->
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
                <h2 class="text-xl font-semibold text-gray-900">Product Management</h2>
                <p class="text-base text-gray-600 mt-1">Manage your pub's product inventory</p>
            </div>
            <livewire:components.create-product/>
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

    <!-- Products Table -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Stock Status</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Cost Price</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Selling Price</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Margin</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($products as $product)
                        @php
                            $stockStatus = $this->getStockStatus($product->current_stock ?? 0, $product->min_stock ?? 0);
                            $margin = $this->calculateMargin($product->cost_price, $product->selling_price);
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-base font-medium uppercase text-gray-900">{{ $product->name }}</div>
                                        <div class="text-base uppercase text-gray-500">{{ $product->category->name }} • {{ $product->sku ?? 'N/A' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-base font-medium text-gray-900">{{ $product->current_stock ?? 0 }} units</div>
                                    <span class="inline-block px-2 py-1 text-xs font-medium rounded-full {{ $stockStatus['color'] }}">
                                        {{ $stockStatus['text'] }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base text-gray-900">${{ number_format($product->cost_price, 2) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base text-gray-900">${{ number_format($product->selling_price, 2) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-base font-medium {{ $margin > 50 ? 'text-green-600' : ($margin > 25 ? 'text-yellow-600' : 'text-red-600') }}">
                                    {{ number_format($margin, 1) }}%
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button 
                                    wire:click="toggleProductStatus({{ $product->id }})"
                                    class="inline-block px-2 py-1 text-xs font-medium rounded-full {{ $product->is_active ? 'text-green-600 bg-green-100 hover:bg-green-200' : 'text-gray-600 bg-gray-100 hover:bg-gray-200' }} transition-colors">
                                    {{ $product->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-base font-medium">
                                <div class="flex space-x-2">
                                    <button class="">Edit Will go here</button>
                                    <button 
                                        wire:click="deleteProduct({{ $product->id }})"
                                        wire:confirm="Are you sure you want to delete this product?"
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
                            <td colspan="7" class="px-6 py-4 whitespace-nowrap text-base text-gray-500 text-center">
                                No products found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $products->links() }}
        </div>
    </div>
</div>
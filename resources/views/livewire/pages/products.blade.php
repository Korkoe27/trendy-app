<div class="space-y-6">
    {{-- Flash --}}
    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('message') }}</span>
        </div>
    @endif

    {{-- Header --}}
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Product Management</h2>
                <p class="text-base text-gray-600 mt-1">Manage your pub's product inventory</p>
            </div>
            @haspermission('create','products')
                <div class="flex gap-2">
                    
                    <livewire:components.create-product />
                    <button wire:click="exportTemplate"
                        class="bg-green-600 text-white px-4 py-2 rounded-xl hover:bg-green-700">
                        Download Template
                    </button>

                    <button wire:click="$set('showImportModal', true)"
                        class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700">
                        Import Products
                    </button>

                    <button wire:click="$set('showExportModal', true)"
                        class="bg-gray-600 flex items-center gap-2 text-white px-4 py-2 rounded-xl hover:bg-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
</svg>

                        <span>Export Products</span>
                    </button>
                </div>
            @endhaspermission
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

    {{-- Table --}}
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Product</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Stock Status</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Cost Price</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Selling Price</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Margin</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Status</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($products as $product)
                        @php
                            $stockStatus = $this->getStockStatus($product->id, $product->stock_limit);
                            $margin = $product?->stocks?->cost_margin ?? 0;
                        @endphp

                        <tr class="hover:bg-gray-50" wire:key="product-row-{{ $product->id }}">
                            {{-- Product --}}
                            <td class="px-6 py-2 whitespace-nowrap">
                                <div class="text-base font-medium uppercase text-gray-900">{{ $product->name }}</div>
                                <div class="text-base uppercase text-gray-500">{{ $product->category->name }} •
                                    {{ $product->barcode ?? 'N/A' }}</div>
                            </td>

                            {{-- Stock --}}
                            <td class="px-6 py-2 whitespace-nowrap">
                                <div class="text-base font-medium text-gray-900">{{ $stockStatus['current'] }} units
                                </div>
                                @if ($product->stock_limit)
                                    <div class="text-xs text-gray-500 mb-1">Limit: {{ $product->stock_limit }}</div>
                                @endif
                                <span
                                    class="inline-block px-2 py-1 text-xs font-medium rounded-full {{ $stockStatus['color'] }}">
                                    {{ $stockStatus['text'] }}
                                </span>
                            </td>

                            {{-- Cost/Sell/Margin --}}
                            <td class="px-6 py-2 whitespace-nowrap">
                                <div class="text-base text-gray-900">GH₵
                                    {{ number_format($product->stocks->cost_price ?? 0, 2) }}</div>
                            </td>
                            <td class="px-6 py-2 whitespace-nowrap">
                                <div class="text-base text-gray-900">GH₵
                                    {{ number_format($product->selling_price, 2) }}</div>
                            </td>
                            <td class="px-6 py-2 whitespace-nowrap">
                                <div
                                    class="text-base font-medium {{ $margin > 50 ? 'text-green-600' : ($margin > 25 ? 'text-yellow-600' : 'text-red-600') }}">
                                    {{ $margin }}
                                </div>
                            </td>

                            {{-- Active/Inactive --}}
                            <td class="px-6 py-2 whitespace-nowrap">
                                <button wire:click="toggleProductStatus({{ $product->id }})"
                                    class="inline-block px-2 py-1 text-xs font-medium rounded-full {{ $product->is_active ? 'text-green-600 bg-green-100 hover:bg-green-200' : 'text-gray-600 bg-gray-100 hover:bg-gray-200' }} transition-colors">
                                    {{ $product->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4 whitespace-nowrap text-base font-medium">
                                <div class="flex items-center space-x-3">
                                    <button wire:click="viewProduct({{ $product->id }})"
                                        class="text-blue-600 hover:text-blue-900 flex items-center space-x-1"
                                        title="View">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                            </path>
                                        </svg>
                                    </button>

                                    
                                    @haspermission('modify','products')
                                    <button wire:click="editProduct({{ $product->id }})"
                                        class="text-green-600 hover:text-green-900" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 4h2m-6.586 9.414l8.586-8.586a2 2 0 112.828 2.828l-8.586 8.586H7v-2.828zM5 19h14" />
                                        </svg>
                                    </button>
                                    @endhaspermission

                                    
                                    @haspermission('delete','products')
                                    <button wire:click="deleteProduct({{ $product->id }})"
                                        wire:confirm="Are you sure you want to delete this product?"
                                        class="text-red-600 hover:text-red-900" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                            </path>
                                        </svg>
                                    </button>
                                    @endhaspermission
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

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $products->links() }}
        </div>
    </div>

    {{-- View Product Modal (placed AFTER table for clean re-render) --}}
    @if ($showModal && $product)
        <div class="fixed inset-0 bg-black/50 bg-opacity-50 flex items-center justify-center z-50"
            aria-labelledby="modal-title" role="dialog" aria-modal="true">
            {{-- <div class="bg-white rounded-lg p-6 w-full max-w-6xl mx-4 max-h-[90vh] overflow-y-auto"> --}}
            {{-- <div class="fixed inset-0 bg-black/50 bg-opacity-75 transition-opacity" wire:click="closeModal"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span> --}}
            {{-- <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button> --}}

            <div class="bg-white rounded-lg p-6 w-full max-w-6xl mx-4 max-h-[90vh] overflow-y-auto">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">Product Details</h3>
                        <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Product Info --}}
                        <div class="space-y-4">
                            <h4 class="text-md font-semibold text-gray-800 border-b pb-2">Product Information</h4>

                            <div>
                                <label class="block text-sm font-medium text-gray-600">Product Name</label>
                                <p class="text-base text-gray-900 uppercase">{{ $product->name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Category</label>
                                <p class="text-base text-gray-900">{{ $product->category->name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Barcode</label>
                                <p class="text-base text-gray-900">{{ $product->barcode ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Selling Price</label>
                                <p class="text-base font-semibold text-gray-900">GH₵
                                    {{ number_format($product->selling_price, 2) }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Stock Limit</label>
                                <p class="text-base text-gray-900">{{ $product->stock_limit ?? 'No limit set' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Status</label>
                                <span
                                    class="inline-block px-2 py-1 text-sm font-medium rounded-full {{ $product->is_active ? 'text-green-600 bg-green-100' : 'text-gray-600 bg-gray-100' }}">
                                    {{ $product->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>

                        {{-- Stock Info --}}
                        <div class="space-y-4">
                            <h4 class="text-md font-semibold text-gray-800 border-b pb-2">Stock Information</h4>

                            @php
                                $currentStock = $this->getCurrentStock($product->id);
                                $stockStatus = $this->getStockStatus($product->id, $product->stock_limit);
                                $latestStock = $stockHistory->first();
                            @endphp

                            <div>
                                <label class="block text-sm font-medium text-gray-600">Current Stock</label>
                                <p class="text-xl font-bold text-gray-900">{{ $currentStock }} units</p>
                                <span
                                    class="inline-block px-2 py-1 text-sm font-medium rounded-full {{ $stockStatus['color'] }}">
                                    {{ $stockStatus['text'] }}
                                </span>
                            </div>

                            @if ($latestStock)
                                <div>
                                    <label class="block text-sm font-medium text-gray-600">Last Cost Price</label>
                                    <p class="text-base text-gray-900">GH₵
                                        {{ number_format($latestStock->cost_price, 2) }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-600">Cost Margin</label>
                                    <p
                                        class="text-base font-medium {{ $latestStock->cost_margin > 50 ? 'text-green-600' : ($latestStock->cost_margin > 25 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ $latestStock->cost_margin ?? 0 }}%
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-600">Last Updated</label>
                                    <p class="text-sm text-gray-700">
                                        {{ $latestStock->created_at->format('M d, Y h:i A') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Stock History --}}
                    @if ($stockHistory && $stockHistory->count() > 0)
                        <div class="mt-6">
                            <h4 class="text-md font-semibold text-gray-800 border-b pb-2 mb-4">Recent Stock History
                            </h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th
                                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Date</th>
                                            <th
                                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Stock</th>
                                            <th
                                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Cost Price</th>
                                            <th
                                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Margin</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach ($stockHistory as $stock)
                                            <tr>
                                                <td class="px-4 py-2 text-sm text-gray-900">
                                                    {{ $stock->created_at->format('M d, Y') }}</td>
                                                <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                                    {{ $stock->total_units }}</td>
                                                <td class="px-4 py-2 text-sm text-gray-900">GH₵
                                                    {{ number_format($stock->cost_price, 2) }}</td>
                                                <td
                                                    class="px-4 py-2 text-sm font-medium {{ $stock->cost_margin > 50 ? 'text-green-600' : ($stock->cost_margin > 25 ? 'text-yellow-600' : 'text-red-600') }}">
                                                    {{ $stock->cost_margin ?? 0 }}%
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

            {{-- </div> --}}
        </div>
    @endif

    <!-- resources/views/livewire/components/edit-product.blade.php -->
    <div>
        @if ($editModal)
            <div class="fixed inset-0 bg-black/50 bg-opacity-50 flex items-center justify-center z-50"
                aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
                    {{-- <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span> --}}

                    {{-- <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"> --}}
                    <form wire:submit.prevent="updateProduct">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="w-full">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modal-title">
                                        Edit <span class="uppercase font-semibold text-blue-400">
                                            {{ $name }}
                                        </span>
                                    </h3>

                                    <div class="space-y-4">
                                        <!-- Product Name -->
                                        <div>
                                            <label for="name"
                                                class="block text-sm font-medium text-gray-700 mb-1">Product
                                                Name</label>
                                            <input type="text" wire:model="name" id="name"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 capitalize focus:ring-blue-500 focus:border-transparent">
                                            @error('name')
                                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- Category -->
                                        <div>
                                            <label for="category_id"
                                                class="block text-sm font-medium text-gray-700">Category</label>
                                            <select wire:model="category_id" id="category_id"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg capitalize focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                <option value="">Select Category</option>
                                                @foreach ($categories as $category)
                                                    <option class="capitalize" value="{{ $category->id }}">
                                                        {{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('category_id')
                                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- Barcode -->
                                        <div>
                                            <label for="barcode"
                                                class="block text-sm font-medium text-gray-700">Barcode
                                                (Optional)</label>
                                            <input type="text" wire:model="barcode" id="barcode"
                                                class="mt-1 block w-full border border-gray-300 rounded-md capitalize shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            @error('barcode')
                                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- Selling Price -->
                                        <div>
                                            <label for="selling_price"
                                                class="block text-sm font-medium text-gray-700">Selling Price
                                                (GH₵)</label>
                                            <input type="number" min="0" step="0.01"
                                                wire:model="selling_price" id="selling_price"
                                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 capitalize px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            @error('selling_price')
                                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div>
                                            <label for="selling_price"
                                                class="block text-sm font-medium text-gray-700">Units Per Box</label>
                                            <input type="number" min="0" step="0.01"
                                                wire:model="units_per_box" id="units_per_box"
                                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 capitalize px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            @error('units_per_box')
                                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- Stock Limit -->
                                        <div>
                                            <label for="stock_limit"
                                                class="block text-sm font-medium text-gray-700">Stock Limit
                                                (Optional)</label>
                                            <input type="number" min="0" wire:model="stock_limit"
                                                id="stock_limit"
                                                class="mt-1 block w-full border border-gray-300 rounded-md capitalize shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            @error('stock_limit')
                                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- Status -->
                                        <div class="flex items-center">
                                            <input type="checkbox" wire:model="is_active" id="is_active"
                                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label for="is_active"
                                                class="ml-2 block text-sm text-gray-900">Active</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Update Product
                            </button>
                            <button type="button" wire:click="closeEditModal"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                    {{-- </div> --}}
                </div>
            </div>
        @endif
        @if ($showImportModal)
            <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold">Import Products</h3>
                        <button wire:click="$set('showImportModal', false)" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit.prevent="importProducts">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Select CSV/Excel File
                            </label>
                            <input type="file" wire:model="importFile" accept=".csv,.xlsx,.xls"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" />
                            @error('importFile')
                                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="$set('showImportModal', false)"
                                class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove>Import</span>
                                <span wire:loading>Importing...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Export Filter Modal -->
@if ($showExportModal)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Export Products - Filters</h3>
                <button wire:click="$set('showExportModal', false)" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="space-y-4">
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

                <!-- Price Range -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Price Range (GH₵)</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <input type="number" wire:model="exportFilters.price_min" 
                                placeholder="Min price" step="0.01" min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <input type="number" wire:model="exportFilters.price_max" 
                                placeholder="Max price" step="0.01" min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Stock Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Stock Status</label>
                    <select wire:model="exportFilters.stock_status" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="all">All Stock Levels</option>
                        <option value="good">Good Stock</option>
                        <option value="low">Low Stock</option>
                        <option value="out">Out of Stock</option>
                    </select>
                </div>

                <!-- Active Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Product Status</label>
                    <select wire:model="exportFilters.is_active" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="all">All Products</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-between mt-6 gap-2">
                <button wire:click="resetExportFilters" 
                    class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Reset Filters
                </button>
                <div class="flex gap-2">
                    <button wire:click="$set('showExportModal', false)" 
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button wire:click="exportProducts" 
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        Export
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
    </div>
</div>

<div>
    <button x-on:click="$wire.showModal = true" class="bg-gradient-to-b text-white from-[#2b2b2b] to-black px-4 py-2 rounded-xl">New Products</button>
 
    <div wire:show="showModal" class="fixed inset-0 bg-black/50 bg-opacity-50 flex items-center justify-center z-50"
    wire:click.self="$set('showModal', false)">
        <div class="bg-white rounded-lg p-6 w-full max-w-6xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-900">Add New Products</h3>
                <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form wire:submit="saveProducts" class="space-y-6">
                @csrf
                
                <!-- Products Section -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <label class="block text-sm font-medium text-gray-700">
                            Products <span class="text-red-400">*</span>
                        </label>
                        <button 
                            type="button"
                            wire:click="addProduct"
                            class="text-blue-600 hover:text-blue-800 font-medium text-sm flex items-center space-x-1"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            <span>Add Product</span>
                        </button>
                    </div>

                    <!-- Products Table -->
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name <span class="text-red-600">*</span></th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category <span class="text-red-600">*</span></th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barcode<span class="text-red-600">*</span></th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Limit<span class="text-red-600">*</span></th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Selling Price (GH₵)<span class="text-red-600">*</span></th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units/Box</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($newProducts as $index => $product)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="text" 
                                                    wire:model="newProducts.{{ $index }}.name" 
                                                    placeholder="Small Club Beer"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                    required
                                                />
                                                @error('newProducts.' . $index . '.name')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                <select 
                                                    wire:model="newProducts.{{ $index }}.category_id"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                    required
                                                >
                                                    <option value="">Select Category</option>
                                                    @foreach($categories as $category)
                                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('newProducts.' . $index . '.category_id')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="text" 
                                                    wire:model="newProducts.{{ $index }}.sku"
                                                    placeholder="SKU123"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                />
                                                @error('newProducts.' . $index . '.sku')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="text" 
                                                    wire:model="newProducts.{{ $index }}.barcode"
                                                    placeholder="1234567890"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                />
                                                @error('newProducts.' . $index . '.barcode')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="number" 
                                                    wire:model="newProducts.{{ $index }}.stock_limit"
                                                    placeholder="100"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                    min="0"
                                                />
                                                @error('newProducts.' . $index . '.stock_limit')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="number" 
                                                    wire:model="newProducts.{{ $index }}.selling_price" 
                                                    step="0.01"
                                                    placeholder="0.00"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                    min="0"
                                                    required
                                                />
                                                @error('newProducts.' . $index . '.selling_price')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="number" 
                                                    wire:model="newProducts.{{ $index }}.units_per_box"
                                                    step="0.01"
                                                    placeholder="24"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                    min="0"
                                                />
                                                @error('newProducts.' . $index . '.units_per_box')
                                                    <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                @if(count($newProducts) > 1)
                                                    <button 
                                                        type="button"
                                                        wire:click="removeProduct({{ $index }})"
                                                        class="text-red-600 hover:text-red-800 font-medium text-sm"
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
                                            <td colspan="8" class="px-4 py-3 text-center text-gray-500">
                                                No products added yet
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3">
                    <button 
                        wire:click="$set('showModal', false)" 
                        type="button"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50 cursor-not-allowed"
                    >
                        <span wire:loading.remove>Add Products</span>
                        <span wire:loading>Adding Products...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
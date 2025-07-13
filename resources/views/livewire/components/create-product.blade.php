<div>
    <button x-on:click="$wire.showModal = true" class="bg-gradient-to-b text-white from-[#2b2b2b] to-black px-4 py-2 rounded-xl">New Product</button>
 
    <div wire:show="showModal" class="fixed inset-0 bg-black/50 bg-opacity-50 flex items-center justify-center z-50"
    wire:click.self="$set('showModal', false)">
    <aside class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Add New Product</h3>
            <form wire:submit="save" class="space-y-4">
              @csrf
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Product Name</label>
                <input type="text" name="name" wire:model="name" placeholder="Small Club Beer" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category_id" wire:model="category_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="" selected disabled class="">What category is the product?</option>
                    @foreach($categories as $category)
                        <option class="uppercase" value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">SKU</label>
                <input type="text" name="sku" wire:model="sku" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Cost Price (GH₵)</label>
                  <input type="number" wire:model="cost_price" name="cost_price" placeholder="GHC" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                </div>
                <div>
                  <label class="block items-center text-sm font-medium text-gray-700 mb-1">Selling Price (GH₵) <span class="text-red-600 font-bold">*</span></label>
                  <input type="number" wire:model="selling_price" name="selling_price" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Units per Box</label>
                <input type="number" name="units_per_box" wire:model="units_per_box" placeholder="24" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
              <div class="flex space-x-3 mt-6">
                <button 
                
                class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                >
                Cancel
              </button>
              <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Add Product
              </button>
            </div>
          </aside>
        </form>
    </div>
</div>
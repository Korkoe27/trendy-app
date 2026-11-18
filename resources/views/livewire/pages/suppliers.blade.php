<div class="space-y-6">
    {{-- Flash --}}
    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('message') }}</span>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Header --}}
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Suppliers</h2>
                <p class="text-base text-gray-600 mt-1">Business profiles, product price lists and spend history</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <input type="text" wire:model.live="searchTerm" placeholder="Search name, contact, region, type…"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm w-64 focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                @haspermission('create', 'suppliers')
                <button wire:click="openCreateModal"
                    class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Supplier
                </button>
                @endhaspermission
            </div>
        </div>
    </div>

    {{-- Suppliers table --}}
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Supplier</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Products</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Total Spend</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Purchases</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Last Purchase</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($suppliers as $supplier)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">{{ $supplier->name }}</div>
                            <div class="text-xs text-gray-500">{{ $supplier->contact_person ?: $supplier->phone ?: '—' }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $supplier->supplier_type ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $supplier->product_count }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900 text-right">GH₵ {{ number_format((float) $supplier->total_spend, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ $supplier->purchase_count }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $supplier->last_purchase_at ? $supplier->last_purchase_at->format('j M Y') : '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs font-medium rounded-full
                                {{ $supplier->status === 'preferred' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                {{ $supplier->status === 'active' ? 'bg-blue-100 text-blue-700' : '' }}
                                {{ $supplier->status === 'inactive' ? 'bg-gray-100 text-gray-600' : '' }}
                                {{ $supplier->status === 'blacklisted' ? 'bg-red-100 text-red-700' : '' }}">
                                {{ ucfirst($supplier->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button wire:click="viewSupplier({{ $supplier->id }})" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View</button>
                            @haspermission('modify', 'suppliers')
                            <button wire:click="editSupplier({{ $supplier->id }})" class="ml-3 text-yellow-600 hover:text-yellow-800 text-sm font-medium">Edit</button>
                            @endhaspermission
                            @haspermission('delete', 'suppliers')
                            <button wire:click="deleteSupplier({{ $supplier->id }})"
                                onclick="confirm('Delete this supplier?') || event.stopImmediatePropagation()"
                                class="ml-3 text-red-600 hover:text-red-800 text-sm font-medium">Delete</button>
                            @endhaspermission
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500">No suppliers found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create / Edit modal --}}
    @if ($showFormModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit Supplier' : 'New Supplier' }}</h3>
                <button wire:click="closeFormModal" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <div class="px-6 py-4 grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[70vh] overflow-y-auto">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Business Name *</label>
                    <input type="text" wire:model="form.name" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                    @error('form.name') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Contact Person</label>
                    <input type="text" wire:model="form.contact_person" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Supplier Type</label>
                    <select wire:model="form.supplier_type" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="">—</option>
                        <option value="distributor">Distributor</option>
                        <option value="wholesaler">Wholesaler</option>
                        <option value="manufacturer">Manufacturer</option>
                        <option value="importer">Importer</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                    <input type="text" wire:model="form.phone" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Alt Phone</label>
                    <input type="text" wire:model="form.alt_phone" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" wire:model="form.email" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Region</label>
                    <input type="text" wire:model="form.region" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Address</label>
                    <input type="text" wire:model="form.address" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Tax / VAT ID</label>
                    <input type="text" wire:model="form.tax_id" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Registration Number</label>
                    <input type="text" wire:model="form.registration_number" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Payment Terms (days)</label>
                    <input type="number" min="0" wire:model="form.payment_terms_days" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Lead Time (days)</label>
                    <input type="number" min="0" wire:model="form.lead_time_days" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Rating (0–5)</label>
                    <input type="number" step="0.1" min="0" max="5" wire:model="form.rating" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Reliability (0–100)</label>
                    <input type="number" min="0" max="100" wire:model="form.reliability_score" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select wire:model="form.status" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="active">Active</option>
                        <option value="preferred">Preferred</option>
                        <option value="inactive">Inactive</option>
                        <option value="blacklisted">Blacklisted</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200">
                <button wire:click="closeFormModal" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button wire:click="saveSupplier" class="px-5 py-2 text-sm bg-black text-white rounded-lg hover:bg-gray-800">Save</button>
            </div>
        </div>
    </div>
    @endif

    {{-- View supplier modal --}}
    @if ($showViewModal && $selectedSupplier)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ $selectedSupplier->name }}</h3>
                    <p class="text-sm text-gray-500">{{ $selectedSupplier->supplier_type ?: 'Supplier' }} · {{ $selectedSupplier->contact_person ?: $selectedSupplier->phone ?: 'No contact' }}</p>
                </div>
                <button wire:click="closeViewModal" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>

            <div class="px-6 py-4 space-y-6 max-h-[70vh] overflow-y-auto">
                {{-- Profile summary --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-gray-500 text-xs">Payment Terms</p>
                        <p class="font-medium">{{ $selectedSupplier->payment_terms_days ? $selectedSupplier->payment_terms_days.' days' : '—' }}</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-gray-500 text-xs">Lead Time</p>
                        <p class="font-medium">{{ $selectedSupplier->lead_time_days ? $selectedSupplier->lead_time_days.' days' : '—' }}</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-gray-500 text-xs">Rating</p>
                        <p class="font-medium">{{ $selectedSupplier->rating ?: '—' }}/5</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-gray-500 text-xs">Reliability</p>
                        <p class="font-medium">{{ $selectedSupplier->reliability_score ?: '—' }}/100</p>
                    </div>
                </div>

                {{-- Product price list --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-sm font-semibold text-gray-900">Product Price List</h4>
                        @haspermission('modify', 'suppliers')
                        <button wire:click="openCatalogModal({{ $selectedSupplier->id }})"
                            class="text-sm text-green-600 hover:text-green-800 font-medium">+ Add Product</button>
                        @endhaspermission
                    </div>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Product</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Unit Cost</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Their SKU</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Last Paid</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase">Preferred</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($supplierPrices as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm text-gray-900">{{ $row->product->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right">GH₵ {{ number_format((float) $row->unit_cost, 2) }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-600">{{ $row->supplier_sku ?: '—' }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-600 text-right">{{ $row->last_purchase_price ? 'GH₵ '.number_format((float) $row->last_purchase_price, 2) : '—' }}</td>
                                    <td class="px-3 py-2 text-center">{{ $row->is_preferred ? '★' : '' }}</td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap">
                                        @haspermission('modify', 'suppliers')
                                        <button wire:click="openCatalogModal({{ $selectedSupplier->id }}, {{ $row->id }})" class="text-yellow-600 hover:text-yellow-800 text-xs font-medium">Edit</button>
                                        <button wire:click="deleteCatalogItem({{ $row->id }})" class="ml-2 text-red-600 hover:text-red-800 text-xs font-medium">Remove</button>
                                        @endhaspermission
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="px-3 py-6 text-center text-sm text-gray-500">No products priced for this supplier yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Recent purchases --}}
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 mb-2">Recent Purchases</h4>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Product</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Units</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($recentStocks as $stock)
                                <tr>
                                    <td class="px-3 py-2 text-sm text-gray-600">{{ $stock->restock_date ? \Carbon\Carbon::parse($stock->restock_date)->format('j M Y') : '—' }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900">{{ $stock->product->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-600 text-right">{{ $stock->total_units }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right">GH₵ {{ number_format((float) $stock->total_cost, 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500">No purchases recorded.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="flex justify-end px-6 py-4 border-t border-gray-200">
                <button wire:click="closeViewModal" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">Close</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Catalog item modal --}}
    @if ($catalogModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">{{ $catalogEditingId ? 'Edit Price' : 'Add Product Price' }}</h3>
                <button wire:click="closeCatalogModal" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Product *</label>
                    <select wire:model="catalogForm.product_id" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="">Select product</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                    @error('catalogForm.product_id') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Unit Cost (GH₵) *</label>
                    <input type="number" step="0.01" min="0" wire:model="catalogForm.unit_cost" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                    @error('catalogForm.unit_cost') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Supplier SKU</label>
                    <input type="text" wire:model="catalogForm.supplier_sku" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="catalogForm.is_preferred" /> Preferred supplier for this product
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="catalogForm.is_active" /> Active
                </label>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200">
                <button wire:click="closeCatalogModal" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button wire:click="saveCatalogItem" class="px-5 py-2 text-sm bg-black text-white rounded-lg hover:bg-gray-800">Save</button>
            </div>
        </div>
    </div>
    @endif
</div>

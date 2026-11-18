<?php

namespace App\Livewire\Pages;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Livewire\Component;

class Suppliers extends Component
{
    public $searchTerm = '';

    // Supplier form (create/edit)
    public $showFormModal = false;

    public $editingId = null;

    public $form = [
        'name' => '',
        'contact_person' => '',
        'phone' => '',
        'alt_phone' => '',
        'email' => '',
        'address' => '',
        'region' => '',
        'supplier_type' => '',
        'tax_id' => '',
        'registration_number' => '',
        'payment_terms_days' => '',
        'lead_time_days' => '',
        'rating' => '',
        'reliability_score' => '',
        'status' => 'active',
    ];

    // Supplier detail modal
    public $showViewModal = false;

    public $selectedSupplier = null;

    // Catalog (supplier_product) editor
    public $catalogModal = false;

    public $catalogSupplierId = null;

    public $catalogEditingId = null;

    public $catalogForm = [
        'product_id' => '',
        'unit_cost' => '',
        'supplier_sku' => '',
        'is_preferred' => false,
        'is_active' => true,
    ];

    protected $rules = [
        'form.name' => 'required|string|max:255',
        'form.contact_person' => 'nullable|string|max:255',
        'form.phone' => 'nullable|string|max:255',
        'form.alt_phone' => 'nullable|string|max:255',
        'form.email' => 'nullable|email|max:255',
        'form.address' => 'nullable|string|max:255',
        'form.region' => 'nullable|string|max:255',
        'form.supplier_type' => 'nullable|string|max:255',
        'form.tax_id' => 'nullable|string|max:255',
        'form.registration_number' => 'nullable|string|max:255',
        'form.payment_terms_days' => 'nullable|integer|min:0',
        'form.lead_time_days' => 'nullable|integer|min:0',
        'form.rating' => 'nullable|numeric|min:0|max:5',
        'form.reliability_score' => 'nullable|integer|min:0|max:100',
        'form.status' => 'required|in:active,preferred,inactive,blacklisted',
    ];

    protected $messages = [
        'form.name.required' => 'Supplier name is required',
    ];

    public function updatedSearchTerm()
    {
        // nothing else needed; render() re-runs the query
    }

    private function suppliersQuery()
    {
        return Supplier::query()
            ->when($this->searchTerm, function ($q) {
                $term = $this->searchTerm;
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'ilike', "%{$term}%")
                        ->orWhere('contact_person', 'ilike', "%{$term}%")
                        ->orWhere('region', 'ilike', "%{$term}%")
                        ->orWhere('supplier_type', 'ilike', "%{$term}%");
                });
            })
            ->withCount('supplierProducts as product_count')
            ->withCount('stocks as purchase_count')
            ->withSum('stocks as total_spend', 'total_cost')
            ->orderBy('name');
    }

    /** The supplier form with every field present, so editing never starts from a half-empty array. */
    private function blankForm(): array
    {
        return [
            'name' => '',
            'contact_person' => '',
            'phone' => '',
            'alt_phone' => '',
            'email' => '',
            'address' => '',
            'region' => '',
            'supplier_type' => '',
            'tax_id' => '',
            'registration_number' => '',
            'payment_terms_days' => '',
            'lead_time_days' => '',
            'rating' => '',
            'reliability_score' => '',
            'status' => 'active',
        ];
    }

    public function openCreateModal()
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->form = $this->blankForm();
        $this->showFormModal = true;
    }

    public function editSupplier($id)
    {
        $supplier = Supplier::findOrFail($id);
        $this->resetErrorBag();
        $this->editingId = $id;
        $this->form = array_merge($this->blankForm(), array_map(fn ($value) => $value ?? '', $supplier->only(array_keys($this->blankForm()))));
        $this->showFormModal = true;
    }

    public function saveSupplier()
    {
        $this->validate();

        $data = $this->form;
        foreach (['payment_terms_days', 'lead_time_days', 'reliability_score'] as $intField) {
            $data[$intField] = $data[$intField] === '' || $data[$intField] === null
                ? null
                : (int) $data[$intField];
        }
        $data['rating'] = $data['rating'] === '' || $data['rating'] === null ? null : (float) $data['rating'];

        if ($this->editingId) {
            Supplier::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Supplier updated successfully.');
        } else {
            Supplier::create($data);
            session()->flash('message', 'Supplier created successfully.');
        }

        $this->showFormModal = false;
        $this->form = $this->blankForm();
    }

    public function deleteSupplier($id)
    {
        $supplier = Supplier::withCount('stocks')->findOrFail($id);

        if ($supplier->stocks_count > 0) {
            session()->flash('error', 'Cannot delete: this supplier has purchase history. Set status to "inactive" instead.');

            return;
        }

        $supplier->delete();
        session()->flash('message', 'Supplier deleted.');
    }

    public function closeFormModal()
    {
        $this->showFormModal = false;
        $this->editingId = null;
        $this->form = $this->blankForm();
    }

    public function viewSupplier($id)
    {
        // Only the supplier is kept between requests. Its price list and purchases are read in render():
        // Livewire rebuilds this model from the database on every request, so anything attached to it
        // here (such as a list of recent stocks) would be gone by the next click.
        $this->selectedSupplier = Supplier::findOrFail($id);
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->selectedSupplier = null;
    }

    // ---- Catalog (supplier_product) management ----

    public function openCatalogModal($supplierId, $catalogId = null)
    {
        $this->resetErrorBag();
        $this->catalogSupplierId = $supplierId;

        if ($catalogId) {
            $row = SupplierProduct::findOrFail($catalogId);
            $this->catalogEditingId = $catalogId;
            $this->catalogForm = [
                'product_id' => $row->product_id,
                'unit_cost' => $row->unit_cost,
                'supplier_sku' => $row->supplier_sku,
                'is_preferred' => (bool) $row->is_preferred,
                'is_active' => (bool) $row->is_active,
            ];
        } else {
            $this->catalogEditingId = null;
            $this->catalogForm = [
                'product_id' => '',
                'unit_cost' => '',
                'supplier_sku' => '',
                'is_preferred' => false,
                'is_active' => true,
            ];
        }

        $this->catalogModal = true;
    }

    public function saveCatalogItem()
    {
        $this->validate([
            'catalogForm.product_id' => 'required|exists:products,id',
            'catalogForm.unit_cost' => 'required|numeric|min:0',
            'catalogForm.supplier_sku' => 'nullable|string|max:255',
        ], [
            'catalogForm.product_id.required' => 'Select a product',
            'catalogForm.unit_cost.required' => 'Unit cost is required',
        ]);

        $attributes = [
            'supplier_id' => $this->catalogSupplierId,
            'product_id' => $this->catalogForm['product_id'],
        ];

        $values = [
            'unit_cost' => (float) $this->catalogForm['unit_cost'],
            'supplier_sku' => $this->catalogForm['supplier_sku'] ?: null,
            'is_preferred' => (bool) $this->catalogForm['is_preferred'],
            'is_active' => (bool) $this->catalogForm['is_active'],
        ];

        SupplierProduct::updateOrCreate($attributes, $values);

        $this->catalogModal = false;
        $this->refreshSelectedSupplier();
        session()->flash('message', 'Product price saved.');
    }

    public function deleteCatalogItem($catalogId)
    {
        $row = SupplierProduct::findOrFail($catalogId);
        $row->delete();
        $this->refreshSelectedSupplier();
        session()->flash('message', 'Product removed from this supplier.');
    }

    public function closeCatalogModal()
    {
        $this->catalogModal = false;
        $this->catalogEditingId = null;
    }

    private function refreshSelectedSupplier()
    {
        if ($this->selectedSupplier) {
            $this->viewSupplier($this->selectedSupplier->id);
        }
    }

    public function render()
    {
        $viewing = $this->showViewModal ? $this->selectedSupplier : null;

        return view('livewire.pages.suppliers', [
            'supplierPrices' => $viewing ? $viewing->supplierProducts()->with('product')->get() : collect(),
            'recentStocks' => $viewing
                ? Stock::with('product')->where('supplier_id', $viewing->id)->latest('created_at')->limit(10)->get()
                : collect(),
            'suppliers' => $this->suppliersQuery()->get(),
            // Only needed by the price window; kept out of public properties so Livewire never has to rehydrate it.
            'products' => $this->catalogModal
                ? Product::stocked()->where('is_active', true)->orderBy('name')->get()
                : collect(),
        ]);
    }
}

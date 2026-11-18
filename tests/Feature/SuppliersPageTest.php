<?php

namespace Tests\Feature;

use App\Livewire\Pages\Suppliers;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** The supplier directory: the form must keep working after a save, and the price window must open. */
class SuppliersPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = DB::table('roles')->insertGetId(['name' => 'Supplier Admin', 'created_at' => now(), 'updated_at' => now()]);
        $moduleId = DB::table('modules')->where('name', 'suppliers')->value('id')
            ?? DB::table('modules')->insertGetId(['name' => 'suppliers', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('permissions')->insert([
            'role_id' => $roleId, 'module_id' => $moduleId,
            'can_view' => true, 'can_create' => true, 'can_modify' => true, 'can_delete' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->admin = User::forceCreate(['name' => 'Admin', 'username' => 'admin'.uniqid(), 'phone' => '+233241112222', 'role_id' => $roleId, 'password' => '482913', 'must_change_password' => false]);
        $this->actingAs($this->admin);

        $games = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('products')->insert(['name' => 'Club Beer', 'category_id' => $games, 'selling_price' => 30, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_supplier_can_be_edited_after_another_was_saved(): void
    {
        $page = Livewire::test(Suppliers::class)
            ->call('openCreateModal')
            ->set('form.name', 'Accra Brewery')
            ->set('form.phone', '0241234567')
            ->call('saveSupplier')
            ->assertHasNoErrors();

        $other = Supplier::create(['name' => 'Kumasi Drinks', 'phone' => '0201112222']);

        // Editing straight after a save used to open an empty form and fail on save.
        $page->call('editSupplier', $other->id)
            ->assertSet('form.name', 'Kumasi Drinks')
            ->assertSet('form.phone', '0201112222')
            ->set('form.region', 'Ashanti')
            ->call('saveSupplier')
            ->assertHasNoErrors();

        $this->assertSame('Ashanti', $other->fresh()->region);
        $this->assertSame('Kumasi Drinks', $other->fresh()->name);
    }

    public function test_closing_the_form_leaves_it_ready_for_the_next_edit(): void
    {
        $supplier = Supplier::create(['name' => 'Accra Brewery']);

        Livewire::test(Suppliers::class)
            ->call('openCreateModal')
            ->call('closeFormModal')
            ->call('editSupplier', $supplier->id)
            ->assertSet('form.name', 'Accra Brewery')
            ->call('saveSupplier')
            ->assertHasNoErrors();
    }

    public function test_the_price_window_opens_and_lists_the_drinks(): void
    {
        $supplier = Supplier::create(['name' => 'Accra Brewery']);

        Livewire::test(Suppliers::class)
            ->assertOk()
            ->call('openCatalogModal', $supplier->id)
            ->assertSet('catalogModal', true)
            ->assertSee('Club Beer');
    }

    public function test_adding_a_product_price_from_a_suppliers_detail_window_works(): void
    {
        $supplier = Supplier::create(['name' => 'Accra Brewery']);
        $beer = DB::table('products')->where('name', 'Club Beer')->value('id');

        // Open the supplier, then the price window: each click is a separate request, which used to
        // lose the purchases list and crash with "foreach() argument must be of type array|object, null given".
        $page = Livewire::test(Suppliers::class)
            ->call('viewSupplier', $supplier->id)
            ->assertSee('No products priced for this supplier yet.')
            ->assertSee('No purchases recorded.')
            ->call('openCatalogModal', $supplier->id)
            ->assertSet('catalogModal', true)
            ->assertSee('Club Beer')
            ->set('catalogForm.product_id', $beer)
            ->set('catalogForm.unit_cost', '25')
            ->call('saveCatalogItem')
            ->assertHasNoErrors()
            ->assertSet('catalogModal', false)
            ->assertSee('GH₵ 25.00');

        $this->assertDatabaseHas('supplier_product', ['supplier_id' => $supplier->id, 'product_id' => $beer, 'unit_cost' => 25]);

        // Editing and removing the price keeps the detail window working.
        $row = \App\Models\SupplierProduct::firstOrFail();
        $page->call('openCatalogModal', $supplier->id, $row->id)
            ->assertSet('catalogForm.unit_cost', '25.00')
            ->call('closeCatalogModal')
            ->call('deleteCatalogItem', $row->id)
            ->assertSee('No products priced for this supplier yet.');
    }

    public function test_the_detail_window_lists_recent_purchases_after_other_actions(): void
    {
        $supplier = Supplier::create(['name' => 'Accra Brewery']);
        $beer = DB::table('products')->where('name', 'Club Beer')->value('id');
        DB::table('stocks')->insert(['product_id' => $beer, 'supplier_id' => $supplier->id, 'total_units' => 48, 'total_cost' => 960, 'restock_date' => '2026-11-10', 'created_at' => now(), 'updated_at' => now()]);

        Livewire::test(Suppliers::class)
            ->call('viewSupplier', $supplier->id)
            ->assertSee('10 Nov 2026')
            ->call('openCatalogModal', $supplier->id)
            ->call('closeCatalogModal')
            ->assertSee('10 Nov 2026')
            ->assertSee('GH₵ 960.00');
    }
}


<?php

namespace Tests\Feature;

use App\Livewire\Pages\Dashboard;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class UnpaidTabsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $waiter;

    private User $bartender;

    private int $beer;

    protected function setUp(): void
    {
        parent::setUp();

        $category = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);
        $this->beer = DB::table('products')->insertGetId(['name' => 'Club Beer', 'category_id' => $category, 'selling_price' => 30, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->waiter = $this->staff('Waiter', '0241000001');
        $this->bartender = $this->staff('Bartender', '0241000002');
    }

    private function staff(string $role, string $phone): User
    {
        return User::forceCreate([
            'name' => $role.' One', 'username' => strtolower($role), 'phone' => $phone,
            'role_id' => DB::table('roles')->where('name', $role)->value('id'),
            'password' => '482913', 'must_change_password' => false,
        ]);
    }

    public function test_users_who_can_view_debts_see_open_unpaid_tabs_with_the_total(): void
    {
        $service = app(OrderService::class);
        $service->place($this->waiter, 'Kwame Mensah', '0241234567', [$this->beer => 2]);                       // 60
        $ready = $service->markReady($service->place($this->waiter, 'Esi Boateng', '0245550000', [$this->beer => 1]), $this->bartender); // 30
        $paid = $service->markReady($service->place($this->waiter, 'Paid Person', '0205550000', [$this->beer => 5]), $this->bartender);
        $service->closeWithCash($paid, $this->waiter);
        $service->cancel($service->place($this->waiter, 'Cancelled Person', '0205550001', [$this->beer => 9]), $this->waiter, 'Left');

        $this->actingAs($this->waiter);

        Livewire::test(Dashboard::class)
            ->assertSee('Unpaid Tabs')
            ->assertSee('2 open tabs')
            ->assertSee('GH₵ 90.00')
            ->assertSee('Kwame Mensah')
            ->assertSee('Esi Boateng')
            ->assertDontSee('Paid Person')
            ->assertDontSee('Cancelled Person');
    }

    public function test_users_without_debt_permission_do_not_see_the_card(): void
    {
        app(OrderService::class)->place($this->waiter, 'Kwame Mensah', '0241234567', [$this->beer => 2]);

        $this->actingAs($this->bartender);

        Livewire::test(Dashboard::class)->assertDontSee('Unpaid Tabs')->assertDontSee('Kwame Mensah');
    }

    public function test_no_card_when_nothing_is_owed(): void
    {
        $this->actingAs($this->waiter);

        Livewire::test(Dashboard::class)->assertDontSee('Unpaid Tabs');
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Pages\DebtPayments;
use App\Models\DebtPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DebtPaymentsPageTest extends TestCase
{
    use RefreshDatabase;

    private int $roleId;

    private int $productId;

    private string $month;

    protected function setUp(): void
    {
        parent::setUp();

        $this->month = now()->format('Y-m');

        $this->roleId = DB::table('roles')->insertGetId(['name' => 'Manager', 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('categories')->insertGetId(['name' => 'Drinks', 'created_at' => now(), 'updated_at' => now()]);
        $this->productId = DB::table('products')->insertGetId(['name' => 'Water', 'category_id' => $categoryId, 'created_at' => now(), 'updated_at' => now()]);

        // Shortfall of 200 on the first day of the current month
        DB::table('daily_sales_summaries')->insert([
            'sales_date' => now()->startOfMonth()->toDateString(),
            'drinks_total' => 1000,
            'total_cash' => 800,
            'product_id' => $this->productId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function userWithPermissions(array $flags): User
    {
        $moduleId = DB::table('modules')->where('name', 'debt_payments')->value('id');

        DB::table('permissions')->insert([
            'role_id' => $this->roleId,
            'module_id' => $moduleId,
            'can_view' => $flags['view'] ?? false,
            'can_create' => $flags['create'] ?? false,
            'can_modify' => false,
            'can_delete' => $flags['delete'] ?? false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::forceCreate([
            'name' => 'Kwame Manager',
            'username' => 'kwame',
            'role_id' => $this->roleId,
            'password' => 'secret',
            'must_change_password' => false,
        ]);
    }

    public function test_route_requires_permission(): void
    {
        $user = $this->userWithPermissions(['view' => false]);

        $this->actingAs($user)->get(route('debt-payments'))->assertForbidden();
    }

    public function test_page_shows_month_totals_for_permitted_user(): void
    {
        $user = $this->userWithPermissions(['view' => true]);

        $this->actingAs($user)->get(route('debt-payments'))
            ->assertOk()
            ->assertSee('Debt Payments')
            ->assertSee('200.00');
    }

    public function test_over_collected_days_are_listed_and_offset_the_debt(): void
    {
        $user = $this->userWithPermissions(['view' => true]);

        // Second day over-collected by 50: the month's debt is 200 - 50 = 150.
        DB::table('daily_sales_summaries')->insert([
            'sales_date' => now()->startOfMonth()->addDay()->toDateString(),
            'drinks_total' => 300,
            'total_cash' => 350,
            'product_id' => $this->productId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(DebtPayments::class)
            ->assertSee('Daily differences')
            ->assertSee('50.00 over')
            ->assertSee('200.00 short')
            ->assertSee('150.00 short') // month total
            ->assertSee('1 short day, 1 over-collected day');
    }

    public function test_recording_a_payment_reduces_outstanding_and_stores_staff(): void
    {
        $user = $this->userWithPermissions(['view' => true, 'create' => true]);

        Livewire::actingAs($user)
            ->test(DebtPayments::class)
            ->call('openPaymentModal')
            ->set('paymentMonth', $this->month)
            ->set('payerName', 'Kofi Mensah')
            ->set('payerPhone', '0240000000')
            ->set('amount', '75')
            ->set('paymentMethod', 'momo')
            ->call('savePayment')
            ->assertHasNoErrors()
            ->assertSee('125.00');

        $payment = DebtPayment::first();
        $this->assertSame('Kofi Mensah', $payment->payer_name);
        $this->assertSame('Kwame Manager', $payment->received_by_name);
        $this->assertSame($user->id, $payment->received_by);
        $this->assertEquals(125.0, (float) $payment->outstanding_after);
    }

    public function test_amount_above_outstanding_is_rejected_with_an_error(): void
    {
        $user = $this->userWithPermissions(['view' => true, 'create' => true]);

        Livewire::actingAs($user)
            ->test(DebtPayments::class)
            ->call('openPaymentModal')
            ->set('payerName', 'Kofi Mensah')
            ->set('amount', '250')
            ->call('savePayment')
            ->assertHasErrors('amount');

        $this->assertSame(0, DebtPayment::count());
    }

    public function test_payer_name_is_required(): void
    {
        $user = $this->userWithPermissions(['view' => true, 'create' => true]);

        Livewire::actingAs($user)
            ->test(DebtPayments::class)
            ->call('openPaymentModal')
            ->set('amount', '50')
            ->call('savePayment')
            ->assertHasErrors('payerName');
    }

    public function test_user_without_create_permission_cannot_record(): void
    {
        $user = $this->userWithPermissions(['view' => true, 'create' => false]);

        Livewire::actingAs($user)
            ->test(DebtPayments::class)
            ->call('openPaymentModal')
            ->assertForbidden();
    }

    public function test_voiding_requires_delete_permission_and_a_reason(): void
    {
        $user = $this->userWithPermissions(['view' => true, 'create' => true, 'delete' => true]);

        $component = Livewire::actingAs($user)
            ->test(DebtPayments::class)
            ->call('openPaymentModal')
            ->set('payerName', 'Kofi Mensah')
            ->set('amount', '50')
            ->call('savePayment');

        $payment = DebtPayment::first();

        $component->call('openVoidModal', $payment->id)
            ->call('voidPayment')
            ->assertHasErrors('voidReason')
            ->set('voidReason', 'Entered twice')
            ->call('voidPayment')
            ->assertHasNoErrors();

        $this->assertNotNull($payment->fresh()->voided_at);
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Pages\Users;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class UsersPageTest extends TestCase
{
    use RefreshDatabase;

    private int $roleId;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleId = DB::table('roles')->insertGetId(['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]);
        $moduleId = DB::table('modules')->insertGetId(['name' => 'users', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('permissions')->insert([
            'role_id' => $this->roleId, 'module_id' => $moduleId,
            'can_view' => true, 'can_create' => true, 'can_modify' => true, 'can_delete' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->admin = User::forceCreate([
            'name' => 'Admin', 'username' => 'admin', 'phone' => '0240000001',
            'role_id' => $this->roleId, 'password' => '482913', 'must_change_password' => false,
        ]);

        $this->actingAs($this->admin);
    }

    private function fillNewUser(array $overrides = [])
    {
        $values = array_merge([
            'name' => 'Kofi Boateng', 'phone' => '055 123 4567', 'role_id' => $this->roleId, 'pin' => '905317',
        ], $overrides);

        $component = Livewire::test(Users::class)->call('showAddUserModal');

        foreach ($values as $key => $value) {
            $component->set($key, $value);
        }

        return $component->call('saveUser');
    }

    public function test_admin_creates_a_user_with_phone_and_temporary_pin(): void
    {
        $this->fillNewUser()->assertHasNoErrors();

        $user = User::where('phone', '+233551234567')->firstOrFail();

        $this->assertTrue($user->must_change_password);
        $this->assertTrue(\Hash::check('905317', $user->password));
        $this->assertNotEmpty($user->username); // generated when left blank
    }

    public function test_phone_is_compulsory_and_must_be_valid(): void
    {
        $this->fillNewUser(['phone' => ''])->assertHasErrors(['phone']);
        $this->fillNewUser(['phone' => '12345'])->assertHasErrors(['phone']);

        $this->assertDatabaseMissing('users', ['name' => 'Kofi Boateng']);
    }

    public function test_phone_must_be_unique_across_formats(): void
    {
        $this->fillNewUser(['phone' => '+233 24 000 0001'])->assertHasErrors(['phone']);
    }

    public function test_weak_pin_is_rejected(): void
    {
        $this->fillNewUser(['pin' => '123456'])->assertHasErrors(['pin']);
    }

    public function test_the_pin_is_never_written_to_the_activity_log(): void
    {
        $this->fillNewUser();

        $this->assertDatabaseMissing('activity_logs', ['metadata' => json_encode(['initial_password' => '905317'])]);
        $this->assertStringNotContainsString('905317', DB::table('activity_logs')->pluck('metadata')->join(' '));
    }

    public function test_reset_generates_a_temporary_pin_forces_a_change_and_texts_it(): void
    {
        config(['services.hubtel_sms' => ['url' => 'https://sms.example.test/send', 'user' => 'u', 'key' => 'k', 'from' => 'Trendy']]);
        Http::fake(['sms.example.test/*' => Http::response([], 200)]);

        $target = User::forceCreate([
            'name' => 'Yaw', 'username' => 'yaw', 'phone' => '0551111111',
            'role_id' => $this->roleId, 'password' => '482913', 'must_change_password' => false,
        ]);

        Livewire::test(Users::class)->call('resetUserPassword', $target->id);

        $target->refresh();
        $this->assertTrue($target->must_change_password);
        $this->assertFalse(\Hash::check('482913', $target->password));

        Http::assertSentCount(1);
        $this->assertStringNotContainsString('Temporary PIN', DB::table('activity_logs')->pluck('metadata')->join(' '));
    }

    public function test_search_finds_users_by_phone(): void
    {
        User::forceCreate([
            'name' => 'Yaw', 'username' => 'yaw', 'phone' => '0551111111',
            'role_id' => $this->roleId, 'password' => '482913', 'must_change_password' => false,
        ]);

        Livewire::test(Users::class)
            ->set('searchTerm', '055 111')
            ->assertSee('0551111111')
            ->assertDontSee('0240000001');
    }

    public function test_users_without_modify_permission_cannot_reset_pins(): void
    {
        DB::table('permissions')->update(['can_modify' => false]);

        Livewire::test(Users::class)->call('resetUserPassword', $this->admin->id)->assertForbidden();
    }
}

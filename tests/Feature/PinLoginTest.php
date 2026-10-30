<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PinLoginTest extends TestCase
{
    use RefreshDatabase;

    private int $roleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleId = DB::table('roles')->insertGetId(['name' => 'Test Role', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function makeUser(array $overrides = []): User
    {
        return User::forceCreate(array_merge([
            'name' => 'Ama Waiter',
            'username' => 'ama',
            'phone' => '0241234567',
            'role_id' => $this->roleId,
            'password' => '482913',
            'must_change_password' => false,
            'is_active' => true,
        ], $overrides));
    }

    public function test_phone_is_stored_in_one_format_whatever_was_typed(): void
    {
        $user = $this->makeUser(['phone' => '024 123 4567']);

        $this->assertSame('+233241234567', $user->fresh()->phone);
    }

    public function test_user_can_sign_in_with_phone_and_pin_in_any_phone_format(): void
    {
        $user = $this->makeUser();

        foreach (['0241234567', '+233 24 123 4567', '024-123-4567'] as $typed) {
            $this->post('signin', ['phone' => $typed, 'pin' => '482913'])->assertRedirect('/');
            $this->assertAuthenticatedAs($user);
            $this->post('logout');
        }

        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action_type' => 'login']);
    }

    public function test_wrong_pin_unknown_number_and_deactivated_user_get_the_same_message(): void
    {
        $this->makeUser();
        $this->makeUser(['username' => 'off', 'phone' => '0551234567', 'is_active' => false]);

        $cases = [
            ['phone' => '0241234567', 'pin' => '000001'], // wrong pin
            ['phone' => '0209999999', 'pin' => '482913'], // unknown number
            ['phone' => '0551234567', 'pin' => '482913'], // deactivated, correct pin
        ];

        foreach ($cases as $i => $case) {
            $this->flushSession();
            $response = $this->post('signin', $case);

            $response->assertSessionHasErrors(['phone' => 'The phone number or PIN is incorrect.']);
            $this->assertGuest();
        }
    }

    public function test_old_username_and_password_login_no_longer_works(): void
    {
        $this->makeUser();

        $this->post('signin', ['username' => 'ama', 'password' => '482913'])
            ->assertSessionHasErrors(['phone', 'pin']);

        $this->assertGuest();
    }

    public function test_pin_must_be_six_digits(): void
    {
        $this->makeUser();

        $this->post('signin', ['phone' => '0241234567', 'pin' => '1234'])->assertSessionHasErrors('pin');
        $this->post('signin', ['phone' => '0241234567', 'pin' => 'abcdef'])->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_five_wrong_pins_lock_the_login_even_for_the_right_pin(): void
    {
        $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post('signin', ['phone' => '0241234567', 'pin' => '000001']);
        }

        $response = $this->post('signin', ['phone' => '0241234567', 'pin' => '482913']);

        $response->assertSessionHasErrors('phone');
        $this->assertStringContainsString('Too many failed attempts', session('errors')->first('phone'));
        $this->assertGuest();
    }

    public function test_a_successful_login_clears_the_failure_count(): void
    {
        $this->makeUser();

        for ($i = 0; $i < 4; $i++) {
            $this->post('signin', ['phone' => '0241234567', 'pin' => '000001']);
        }

        $this->post('signin', ['phone' => '0241234567', 'pin' => '482913'])->assertRedirect('/');
        $this->post('logout');

        // Four more wrong tries are allowed again, so the right PIN still works afterwards.
        for ($i = 0; $i < 4; $i++) {
            $this->post('signin', ['phone' => '0241234567', 'pin' => '000001']);
        }

        $this->post('signin', ['phone' => '0241234567', 'pin' => '482913'])->assertRedirect('/');
    }

    public function test_a_lockout_is_recorded_on_the_users_activity_log(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 6; $i++) {
            $this->post('signin', ['phone' => '0241234567', 'pin' => '000001']);
        }

        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action_type' => 'login_failed']);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action_type' => 'login_locked']);
    }

    public function test_user_with_a_temporary_pin_must_set_their_own_before_using_the_app(): void
    {
        $this->makeUser(['must_change_password' => true]);

        $this->post('signin', ['phone' => '0241234567', 'pin' => '482913'])
            ->assertRedirect(route('password.change'));

        $this->get('/')->assertRedirect(route('password.change'));
    }

    public function test_changing_pin_requires_the_current_pin_and_a_strong_new_one(): void
    {
        $user = $this->makeUser(['must_change_password' => true]);
        $this->actingAs($user);

        $this->post(route('password.update'), ['current_pin' => '111111', 'pin' => '905317', 'pin_confirmation' => '905317'])
            ->assertSessionHasErrors('current_pin');

        $this->post(route('password.update'), ['current_pin' => '482913', 'pin' => '123456', 'pin_confirmation' => '123456'])
            ->assertSessionHasErrors('pin');

        $this->post(route('password.update'), ['current_pin' => '482913', 'pin' => '482913', 'pin_confirmation' => '482913'])
            ->assertSessionHasErrors('pin');

        $this->post(route('password.update'), ['current_pin' => '482913', 'pin' => '905317', 'pin_confirmation' => '905317'])
            ->assertRedirect('/');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(\Hash::check('905317', $user->password));
    }

    public function test_pin_change_is_rate_limited(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.update'), ['current_pin' => '111111', 'pin' => '905317', 'pin_confirmation' => '905317']);
        }

        $this->post(route('password.update'), ['current_pin' => '482913', 'pin' => '905317', 'pin_confirmation' => '905317'])
            ->assertSessionHasErrors('current_pin');

        $this->assertTrue(\Hash::check('482913', $user->fresh()->password));
    }

    public function test_signed_in_users_are_sent_away_from_the_login_page(): void
    {
        $this->actingAs($this->makeUser());

        $this->get('login')->assertRedirect();
    }

    public function test_set_phone_command_backfills_a_user_and_can_reset_the_pin(): void
    {
        $user = $this->makeUser(['phone' => null]);

        $this->artisan('users:set-phone', ['--list' => true])->assertSuccessful();

        $this->artisan('users:set-phone', ['username' => 'ama', 'phone' => '024 555 0101', '--pin' => '905317'])->assertSuccessful();

        $user->refresh();
        $this->assertSame('+233245550101', $user->phone);
        $this->assertTrue($user->must_change_password);

        $this->post('signin', ['phone' => '0245550101', 'pin' => '905317'])->assertRedirect(route('password.change'));
    }

    public function test_set_phone_command_refuses_duplicates_and_weak_pins(): void
    {
        $this->makeUser();
        $other = $this->makeUser(['username' => 'kojo', 'phone' => null]);

        $this->artisan('users:set-phone', ['username' => 'kojo', 'phone' => '0241234567'])->assertFailed();
        $this->artisan('users:set-phone', ['username' => 'kojo', 'phone' => '0209876543', '--pin' => '123456'])->assertExitCode(2);

        $this->assertNull($other->fresh()->phone);
    }
}

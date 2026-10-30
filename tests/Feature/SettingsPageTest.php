<?php

namespace Tests\Feature;

use App\Livewire\Pages\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // A role with no permissions at all: Settings must still work for everyone.
        $roleId = DB::table('roles')->insertGetId(['name' => 'No Access', 'created_at' => now(), 'updated_at' => now()]);

        $this->user = User::forceCreate([
            'name' => 'Garnet Mensah', 'username' => 'garnet', 'phone' => '0241234567', 'role_id' => $roleId,
            'password' => '482913', 'must_change_password' => false,
        ]);

        $this->actingAs($this->user);
    }

    /** activity_logs.metadata is stored as JSON text, sometimes encoded twice by older call sites. */
    private function metadata(object $log): array
    {
        $meta = json_decode($log->metadata, true);

        return is_string($meta) ? json_decode($meta, true) : $meta;
    }

    public function test_every_signed_in_user_can_open_settings_but_guests_cannot(): void
    {
        $this->get(route('settings'))->assertOk()->assertSee('Your name')->assertSee('Change your PIN')->assertSee('0241234567');

        auth()->logout();
        $this->get(route('settings'))->assertRedirect();
    }

    public function test_the_sidebar_link_shows_for_everyone(): void
    {
        // On another page, so the match is the sidebar link and not the Settings page itself.
        $this->get('/')->assertOk()->assertSee('href="'.route('settings').'"', false);
    }

    public function test_a_user_can_change_their_name(): void
    {
        Livewire::test(Settings::class)
            ->assertSet('name', 'Garnet Mensah')
            ->set('name', '  Garnet   Kofi  Mensah ')
            ->call('saveName')
            ->assertHasNoErrors()
            ->assertSet('name', 'Garnet Kofi Mensah')
            ->assertDispatched('profile-updated')
            ->assertSee('Your name has been updated.');

        $this->assertSame('Garnet Kofi Mensah', $this->user->fresh()->name);
        $this->assertSame('+233241234567', $this->user->fresh()->phone, 'The phone is untouched');

        $log = DB::table('activity_logs')->where('action_type', 'profile_update')->first();
        $this->assertSame($this->user->id, (int) $log->user_id);
        $this->assertSame('Garnet Mensah', $this->metadata($log)['old_name']);
    }

    public function test_the_name_is_validated_and_an_unchanged_name_is_a_no_op(): void
    {
        Livewire::test(Settings::class)->set('name', '')->call('saveName')->assertHasErrors(['name' => 'required']);
        Livewire::test(Settings::class)->set('name', 'A')->call('saveName')->assertHasErrors(['name' => 'min']);
        Livewire::test(Settings::class)->set('name', str_repeat('x', 256))->call('saveName')->assertHasErrors(['name' => 'max']);

        Livewire::test(Settings::class)->set('name', 'Garnet Mensah')->call('saveName')->assertSee('already saved');

        $this->assertSame('Garnet Mensah', $this->user->fresh()->name);
        $this->assertSame(0, DB::table('activity_logs')->where('action_type', 'profile_update')->count());
    }

    public function test_a_user_can_change_their_pin_and_log_in_with_the_new_one(): void
    {
        Livewire::test(Settings::class)
            ->set('currentPin', '482913')
            ->set('newPin', '730194')
            ->set('newPinConfirmation', '730194')
            ->call('changePin')
            ->assertHasNoErrors()
            ->assertSet('currentPin', '')
            ->assertSet('newPin', '')
            ->assertSee('Your PIN has been changed');

        $user = $this->user->fresh();
        $this->assertTrue(Hash::check('730194', $user->password));
        $this->assertFalse(Hash::check('482913', $user->password));
        $this->assertNotNull($user->password_changed_at);

        $log = DB::table('activity_logs')->where('action_type', 'password_change')->first();
        $this->assertStringNotContainsString('730194', $log->metadata);
        $this->assertSame('settings', $this->metadata($log)['source']);
        $this->assertStringNotContainsString('482913', $log->metadata);

        auth()->logout();
        $this->post(route('signin'), ['phone' => '0241234567', 'pin' => '730194'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_current_pin_must_be_right_and_wrong_guesses_are_throttled(): void
    {
        $component = Livewire::test(Settings::class);

        foreach (range(1, 5) as $i) {
            $component->set('currentPin', '111222')->set('newPin', '730194')->set('newPinConfirmation', '730194')
                ->call('changePin')->assertHasErrors(['currentPin']);
        }

        // Even the right PIN is refused once locked out.
        $component->set('currentPin', '482913')->call('changePin')->assertHasErrors(['currentPin']);
        $this->assertTrue(Hash::check('482913', $this->user->fresh()->password), 'PIN must not have changed');

        RateLimiter::clear('pin-change:'.$this->user->id);
    }

    public function test_pin_rules_are_enforced(): void
    {
        $cases = [
            ['482913', '123456', '123456', 'newPin'],       // too easy
            ['482913', '730', '730', 'newPin'],             // not six digits
            ['482913', '73019a', '73019a', 'newPin'],       // not digits
            ['482913', '730194', '730195', 'newPinConfirmation'], // mismatch
            ['482913', '482913', '482913', 'newPin'],       // same as current
            ['48', '730194', '730194', 'currentPin'],       // current is not six digits
            ['', '', '', 'currentPin'],
        ];

        foreach ($cases as [$current, $new, $confirm, $errorField]) {
            Livewire::test(Settings::class)
                ->set('currentPin', $current)->set('newPin', $new)->set('newPinConfirmation', $confirm)
                ->call('changePin')
                ->assertHasErrors([$errorField]);
        }

        $this->assertTrue(Hash::check('482913', $this->user->fresh()->password));
    }

    public function test_changing_the_pin_clears_a_forced_change_flag(): void
    {
        $this->user->update(['must_change_password' => true]);

        Livewire::test(Settings::class)
            ->set('currentPin', '482913')->set('newPin', '730194')->set('newPinConfirmation', '730194')
            ->call('changePin')->assertHasNoErrors();

        $this->assertFalse((bool) $this->user->fresh()->must_change_password);
    }

    public function test_a_user_with_a_forced_pin_change_is_sent_to_set_it_first(): void
    {
        $this->user->update(['must_change_password' => true]);

        $this->get(route('settings'))->assertRedirect(route('password.change'));
    }
}

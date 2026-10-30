<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PinResetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.hubtel_sms' => [
            'url' => 'https://sms.example.test/v1/messages/send?clientid=abc&clientsecret=def',
            'user' => 'sms-user',
            'key' => 'sms-key',
            'from' => 'Trendy',
        ]]);

        Http::fake(['sms.example.test/*' => Http::response(['status' => 0], 200)]);

        $roleId = DB::table('roles')->insertGetId(['name' => 'Test Role', 'created_at' => now(), 'updated_at' => now()]);

        $this->user = User::forceCreate([
            'name' => 'Ama Waiter',
            'username' => 'ama',
            'phone' => '0241234567',
            'role_id' => $roleId,
            'password' => '482913',
            'must_change_password' => false,
        ]);
    }

    private function sentCode(): string
    {
        $code = null;

        Http::assertSent(function (Request $request) use (&$code) {
            preg_match('/\b(\d{6})\b/', $request['Content'], $m);
            $code = $m[1] ?? null;

            return true;
        });

        return $code;
    }

    public function test_requesting_a_code_texts_the_user_through_hubtel(): void
    {
        $this->post(route('pin.reset.send'), ['phone' => '024 123 4567'])
            ->assertRedirect(route('pin.reset.verify'));

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://sms.example.test/v1/messages/send')
            && ! str_contains($r->url(), 'clientsecret')              // credentials go in Basic auth, not the URL
            && $r['To'] === '233241234567'
            && $r['From'] === 'Trendy'
            && $r->hasHeader('Authorization'));
    }

    public function test_unknown_numbers_get_the_same_response_and_no_sms(): void
    {
        $this->post(route('pin.reset.send'), ['phone' => '0209999999'])
            ->assertRedirect(route('pin.reset.verify'))
            ->assertSessionHas('message');

        Http::assertNothingSent();
    }

    public function test_correct_code_sets_the_new_pin_and_it_works_for_login(): void
    {
        $this->post(route('pin.reset.send'), ['phone' => '0241234567']);
        $code = $this->sentCode();

        $this->get(route('pin.reset.verify'))->assertOk()->assertSee('024 •• 4567');

        $this->post(route('pin.reset.update'), ['code' => $code, 'pin' => '905317', 'pin_confirmation' => '905317'])
            ->assertRedirect(route('login'));

        $this->post('signin', ['phone' => '0241234567', 'pin' => '905317'])->assertRedirect('/');
        $this->assertDatabaseHas('activity_logs', ['user_id' => $this->user->id, 'action_type' => 'pin_reset']);
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $this->post(route('pin.reset.send'), ['phone' => '0241234567']);
        $code = $this->sentCode();

        $this->post(route('pin.reset.update'), ['code' => $code, 'pin' => '905317', 'pin_confirmation' => '905317']);

        $this->flushSession();
        $this->post(route('pin.reset.send'), ['phone' => '0200000000']); // sets the session phone to an unknown number
        $this->withSession(['pin_reset_phone' => '+233241234567'])
            ->post(route('pin.reset.update'), ['code' => $code, 'pin' => '271828', 'pin_confirmation' => '271828'])
            ->assertSessionHasErrors('code');

        $this->assertTrue(\Hash::check('905317', $this->user->fresh()->password));
    }

    public function test_three_wrong_codes_burn_the_code(): void
    {
        $this->post(route('pin.reset.send'), ['phone' => '0241234567']);
        $code = $this->sentCode();
        $wrong = $code === '000000' ? '000001' : '000000';

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('pin.reset.update'), ['code' => $wrong, 'pin' => '905317', 'pin_confirmation' => '905317'])
                ->assertSessionHasErrors('code');
        }

        // Even the right code no longer works.
        $this->post(route('pin.reset.update'), ['code' => $code, 'pin' => '905317', 'pin_confirmation' => '905317'])
            ->assertSessionHasErrors('code');

        $this->assertTrue(\Hash::check('482913', $this->user->fresh()->password));
    }

    public function test_weak_new_pins_are_rejected_and_the_code_stays_usable(): void
    {
        $this->post(route('pin.reset.send'), ['phone' => '0241234567']);
        $code = $this->sentCode();

        $this->post(route('pin.reset.update'), ['code' => $code, 'pin' => '123456', 'pin_confirmation' => '123456'])
            ->assertSessionHasErrors('pin');

        $this->post(route('pin.reset.update'), ['code' => $code, 'pin' => '905317', 'pin_confirmation' => '905317'])
            ->assertRedirect(route('login'));
    }

    public function test_code_requests_are_limited_per_number(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('pin.reset.send'), ['phone' => '0241234567'])->assertRedirect(route('pin.reset.verify'));
        }

        $this->post(route('pin.reset.send'), ['phone' => '0241234567'])->assertSessionHasErrors('phone');
    }

    public function test_reset_page_needs_a_started_request(): void
    {
        $this->get(route('pin.reset.verify'))->assertRedirect(route('pin.reset.request'));
    }

    public function test_deactivated_users_get_no_code(): void
    {
        $this->user->update(['is_active' => false]);

        $this->post(route('pin.reset.send'), ['phone' => '0241234567'])->assertRedirect(route('pin.reset.verify'));

        Http::assertNothingSent();
    }
}

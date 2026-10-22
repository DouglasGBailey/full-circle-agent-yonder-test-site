<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Test-account sign-in: no self-registration, honeypot, throttle. */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['email' => 'reviewer.pilot@example.com', 'password' => 'correct-horse-battery']);
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('name="website"', false);
    }

    public function test_good_login_goes_home_and_clears_chat_state(): void
    {
        $this->user();
        $this->keepSession();
        $this->postJson('/chat', ['message' => 'hi'])->assertOk();
        $this->assertDatabaseCount('chat_states', 1);

        $this->post('/login', ['email' => 'Reviewer.Pilot@example.com', 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertDatabaseCount('chat_states', 0);
    }

    public function test_bad_password_is_rejected_with_one_generic_message(): void
    {
        $this->user();
        $this->from('/login')->post('/login', ['email' => 'reviewer.pilot@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'That email address and password do not match a test account.']);
        $this->assertGuest();
    }

    public function test_honeypot_blocks_bots_even_with_the_right_password(): void
    {
        $this->user();
        $this->post('/login', ['email' => 'reviewer.pilot@example.com', 'password' => 'correct-horse-battery', 'website' => 'http://spam'])
            ->assertSessionHasErrors('website');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_a_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'x@example.com', 'password' => 'nope'])->assertStatus(302);
        }
        $this->post('/login', ['email' => 'x@example.com', 'password' => 'nope'])->assertStatus(429);
    }

    public function test_signed_in_user_is_sent_away_from_login_and_can_log_out(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/login')->assertRedirect();
        $this->actingAs($user)->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_logout_needs_sign_in(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
    }
}

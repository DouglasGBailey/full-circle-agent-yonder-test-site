<?php

namespace Tests\Feature;

use App\Models\ChatState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** POST /chat, the website widget's endpoint. */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_unregistered_route_and_state_is_kept_between_messages(): void
    {
        $this->keepSession();
        $this->postJson('/chat', ['message' => 'hi'])
            ->assertOk()
            ->assertJsonPath('route', 'unregistered')
            ->assertJsonPath('messages.0.buttons', ['Rooms and rates', 'Restaurant', 'Create an account']);

        $this->postJson('/chat', ['message' => 'Restaurant'])
            ->assertOk()
            ->assertJsonPath('messages.0.buttons', ['View menu', 'Main menu']);
    }

    public function test_signed_in_guest_gets_registered_route(): void
    {
        $user = User::factory()->create(['name' => 'Yonder Reviewer']);
        $this->actingAs($user)->postJson('/chat', ['message' => 'hi'])
            ->assertOk()
            ->assertJsonPath('route', 'registered')
            ->assertJsonPath('messages.0.text', 'Welcome back, Yonder. What would you like to do today?');
    }

    public function test_validation(): void
    {
        $this->postJson('/chat', [])->assertStatus(422);
        $this->postJson('/chat', ['message' => str_repeat('a', 201)])->assertStatus(422);
        $this->postJson('/chat', ['message' => ['x']])->assertStatus(422);
    }

    public function test_html_is_returned_as_plain_text_never_markup(): void
    {
        $this->keepSession();
        $this->postJson('/chat', ['message' => 'hi']);
        $this->postJson('/chat', ['message' => 'Create an account']);
        $res = $this->postJson('/chat', ['message' => '<img src=x onerror=alert(1)>']);
        $res->assertOk();
        $this->assertStringNotContainsString('<img', $res->getContent());
    }

    public function test_rate_limited_after_40_a_minute(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $this->postJson('/chat', ['message' => 'hi'])->assertOk();
        }
        $this->postJson('/chat', ['message' => 'hi'])->assertStatus(429);
    }

    /*
     * Regression: the state used to live in the session, and a request that
     * overlapped a chat turn could save an older session over it (seen once
     * in the Chromium run: the bot answered "12 Dec, 2 nights" with the
     * fallback text). A session write must no longer touch the chat state.
     */
    public function test_chat_state_survives_a_stale_session_write(): void
    {
        $sessionId = $this->keepSession();
        $this->postJson('/chat', ['message' => 'hi'])->assertOk();
        $this->postJson('/chat', ['message' => 'Restaurant'])->assertOk();
        $this->assertSame('restaurant_info', ChatState::load($sessionId)['node'] ?? null);

        // What an overlapping request used to write back: the state from before "Restaurant".
        $this->withSession(['chat_state' => ['flow' => 'unregistered', 'node' => 'welcome', 'ctx' => []]]);
        $this->postJson('/chat', ['message' => 'View menu'])
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonMissing(['text' => 'Sorry, I did not understand that. Please choose one of the options below.']);
    }

    public function test_state_is_stored_by_hash_and_expires_with_the_session(): void
    {
        ChatState::save('abc', ['flow' => 'unregistered', 'node' => 'welcome', 'ctx' => []]);
        $this->assertDatabaseMissing('chat_states', ['session_hash' => 'abc']);
        $this->assertDatabaseHas('chat_states', ['session_hash' => hash('sha256', 'abc')]);
        $this->assertSame('welcome', ChatState::load('abc')['node']);

        $this->travel(config('session.lifetime') + 1)->minutes();
        $this->assertSame([], ChatState::load('abc'));
    }

    public function test_sign_out_forgets_the_conversation(): void
    {
        $user = User::factory()->create();
        $this->keepSession();
        $this->actingAs($user)->postJson('/chat', ['message' => 'hi'])->assertOk();
        $this->assertDatabaseCount('chat_states', 1);
        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertDatabaseCount('chat_states', 0);
    }
}

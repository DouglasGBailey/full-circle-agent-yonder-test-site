<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Slack-shaped endpoints the mock-Slack MCP server calls. */
class SlackApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.full_circle_agent.token' => str_repeat('t', 64)]);
        $this->withToken(str_repeat('t', 64));
    }

    public function test_history_replies_post_and_react(): void
    {
        $u = User::factory()->create(['name' => 'Yonder Requester']);
        $ts = $this->actingAs($u)->postJson('/channel/messages', ['text' => 'Add a spa page'])->json('ts');

        $this->getJson('/api/slack/conversations.history')->assertOk()
            ->assertJsonPath('messages.0.ts', $ts)
            ->assertJsonPath('messages.0.user', sprintf('U%06d', $u->id))
            ->assertJsonPath('messages.0.is_agent', false);

        $this->postJson('/api/slack/chat.postMessage', ['text' => 'On it', 'thread_ts' => $ts])->assertOk()
            ->assertJsonPath('message.user_name', 'Full Circle Agent')
            ->assertJsonPath('message.is_agent', true);
        $this->postJson('/api/slack/reactions.add', ['timestamp' => $ts, 'name' => 'eyes'])->assertOk();
        $this->postJson('/api/slack/reactions.add', ['timestamp' => $ts, 'name' => 'eyes'])->assertOk();   // idempotent
        $this->assertDatabaseCount('channel_reactions', 1);

        $this->getJson('/api/slack/conversations.replies?ts='.$ts)->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.1.text', 'On it');
    }

    public function test_errors(): void
    {
        $this->getJson('/api/slack/conversations.replies?ts=1.1')->assertStatus(404)->assertJsonPath('error', 'thread_not_found');
        $this->postJson('/api/slack/chat.postMessage', ['text' => 'x', 'thread_ts' => '1.1'])->assertStatus(404);
        $this->postJson('/api/slack/chat.postMessage', [])->assertStatus(422);
        $this->postJson('/api/slack/reactions.add', ['timestamp' => '1.1', 'name' => 'eyes'])->assertStatus(404)->assertJsonPath('error', 'message_not_found');
        $this->postJson('/api/slack/reactions.add', ['timestamp' => '1.1', 'name' => 'skull'])->assertStatus(422);
        $this->getJson('/api/slack/conversations.history?limit=500')->assertStatus(422);
    }
}

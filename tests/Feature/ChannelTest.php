<?php

namespace Tests\Feature;

use App\Models\ChannelMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The simulated Slack channel page. */
class ChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_read_the_channel_and_feed(): void
    {
        $this->get('/channel')->assertOk();
        $this->get('/channel/feed')->assertOk()->assertSee('No requests yet');
    }

    public function test_guests_cannot_post_or_react(): void
    {
        $this->post('/channel/messages', ['text' => 'hello'])->assertRedirect(route('login'));
        $this->postJson('/channel/messages', ['text' => 'hello'])->assertStatus(401);
        $this->postJson('/channel/reactions', ['ts' => '1.0', 'name' => 'eyes'])->assertStatus(401);
        $this->assertSame(0, ChannelMessage::count());
    }

    public function test_signed_in_user_posts_replies_and_text_is_escaped(): void
    {
        $user = User::factory()->create(['name' => 'Yonder Requester']);
        $ts = $this->actingAs($user)->postJson('/channel/messages', ['text' => '  Make the <b>hero</b> blue  '])
            ->assertOk()->json('ts');
        $this->assertDatabaseHas('channel_messages', ['ts' => $ts, 'text' => 'Make the <b>hero</b> blue', 'author' => 'Yonder Requester', 'is_agent' => false]);

        $this->actingAs($user)->post('/channel/messages', ['text' => 'approve', 'thread_ts' => $ts])
            ->assertRedirect(route('channel').'#m-'.str_replace('.', '-', $ts));

        $this->get('/channel/feed')->assertOk()
            ->assertSee('Make the &lt;b&gt;hero&lt;/b&gt; blue', false)
            ->assertDontSee('<b>hero</b>', false);
    }

    public function test_post_validation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/channel/messages', ['text' => 'x'])->assertStatus(422);
        $this->actingAs($user)->postJson('/channel/messages', ['text' => str_repeat('a', 2001)])->assertStatus(422);
        $this->actingAs($user)->postJson('/channel/messages', ['text' => 'hello', 'thread_ts' => '999.1'])->assertStatus(422);

        // Replies cannot be nested: thread_ts must be a top-level message.
        $ts = $this->actingAs($user)->postJson('/channel/messages', ['text' => 'parent'])->json('ts');
        $reply = $this->actingAs($user)->postJson('/channel/messages', ['text' => 'child', 'thread_ts' => $ts])->json('ts');
        $this->actingAs($user)->postJson('/channel/messages', ['text' => 'grandchild', 'thread_ts' => $reply])->assertStatus(422);
    }

    public function test_reaction_toggles_and_only_allowed_names(): void
    {
        $user = User::factory()->create();
        $ts = $this->actingAs($user)->postJson('/channel/messages', ['text' => 'a request'])->json('ts');

        $this->actingAs($user)->postJson('/channel/reactions', ['ts' => $ts, 'name' => 'white_check_mark'])->assertOk();
        $this->assertDatabaseHas('channel_reactions', ['name' => 'white_check_mark', 'user_id' => $user->id, 'is_agent' => false]);
        $this->actingAs($user)->postJson('/channel/reactions', ['ts' => $ts, 'name' => 'white_check_mark'])->assertOk();
        $this->assertDatabaseCount('channel_reactions', 0);

        $this->actingAs($user)->postJson('/channel/reactions', ['ts' => $ts, 'name' => 'rocket'])->assertStatus(422);
        $this->actingAs($user)->postJson('/channel/reactions', ['ts' => '1.000001', 'name' => 'eyes'])->assertStatus(422);
    }

    public function test_posting_is_throttled_at_twelve_a_minute(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            $this->actingAs($user)->postJson('/channel/messages', ['text' => "request {$i}"])->assertOk();
        }
        $this->actingAs($user)->postJson('/channel/messages', ['text' => 'one too many'])->assertStatus(429);
    }
}

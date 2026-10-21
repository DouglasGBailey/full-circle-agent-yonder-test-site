<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** The agent's work queue, the approval gate and the board-card status machine. */
class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = str_repeat('t', 64);
        config(['services.full_circle_agent.token' => $this->token]);
    }

    private function api(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->withToken($this->token)->json($method, '/api'.$uri, $data);
    }

    /** A person posts a request in the channel; returns its ts. */
    private function request(User $user, string $text = 'Please make the hero heading bigger'): string
    {
        return $this->actingAs($user)->postJson('/channel/messages', ['text' => $text])->json('ts');
    }

    private function reply(User $user, string $ts, string $text): void
    {
        $this->actingAs($user)->postJson('/channel/messages', ['text' => $text, 'thread_ts' => $ts])->assertOk();
    }

    private function card(string $ts): int
    {
        return $this->api('POST', '/board/cards', ['request_ts' => $ts, 'title' => 'Bigger hero heading'])
            ->assertCreated()->json('card.id');
    }

    private function move(int $id, string $status): TestResponse
    {
        return $this->api('PATCH', "/board/cards/{$id}", ['status' => $status]);
    }

    // ---------- token ----------

    public function test_api_is_disabled_without_a_long_token(): void
    {
        config(['services.full_circle_agent.token' => 'short']);
        $this->withToken('short')->getJson('/api/agent/pending')->assertStatus(503)->assertJson(['ok' => false, 'error' => 'api_disabled']);
    }

    public function test_api_needs_the_right_bearer_token(): void
    {
        $this->getJson('/api/agent/pending')->assertStatus(401)->assertJsonPath('error', 'invalid_auth');
        $this->withToken(str_repeat('x', 64))->getJson('/api/agent/pending')->assertStatus(401);
        $this->api('GET', '/agent/pending')->assertOk()->assertJson(['ok' => true, 'items' => []]);
    }

    // ---------- pending ----------

    public function test_new_request_is_pending_until_it_has_a_card(): void
    {
        $u = User::factory()->create(['name' => 'Yonder Requester']);
        $ts = $this->request($u);
        $this->api('GET', '/agent/pending')->assertJsonPath('items.0', [
            'kind' => 'new_request', 'request_ts' => $ts, 'card_id' => null,
            'user_name' => 'Yonder Requester', 'text' => 'Please make the hero heading bigger',
        ]);
        $this->card($ts);
        $this->api('GET', '/agent/pending')->assertJsonCount(0, 'items');
    }

    public function test_agent_messages_and_replies_are_never_new_requests(): void
    {
        $u = User::factory()->create();
        $ts = $this->request($u);
        $this->card($ts);
        $this->api('POST', '/slack/chat.postMessage', ['text' => 'Agent status update'])->assertOk();
        $this->reply($u, $ts, 'a thread reply');
        $this->api('GET', '/agent/pending')->assertJsonCount(0, 'items');
    }

    public function test_needs_info_reply_is_pending(): void
    {
        $u = User::factory()->create();
        $ts = $this->request($u);
        $id = $this->card($ts);
        $this->move($id, 'Needs info')->assertOk();
        $this->api('GET', '/agent/pending')->assertJsonCount(0, 'items');

        $this->travel(1)->seconds();
        $this->reply($u, $ts, 'Make it 20 percent bigger');
        $this->api('GET', '/agent/pending')->assertJsonPath('items.0.kind', 'reply')
            ->assertJsonPath('items.0.text', 'Make it 20 percent bigger');
    }

    public function test_retry_is_pending_only_while_attempts_remain(): void
    {
        $u = User::factory()->create();
        $ts = $this->request($u);
        $id = $this->card($ts);
        $this->move($id, 'In progress')->assertOk();
        $this->move($id, 'Failed')->assertOk();

        $this->travel(1)->seconds();
        $this->reply($u, $ts, 'that is a pity');
        $this->api('GET', '/agent/pending')->assertJsonCount(0, 'items');
        $this->reply($u, $ts, 'Retry please');
        $this->api('GET', '/agent/pending')->assertJsonPath('items.0.kind', 'retry');

        // attempt 1, fail, attempt 2, fail: no attempts left.
        $this->move($id, 'In progress')->assertJsonPath('card.attempts', 1);
        $this->move($id, 'Failed');
        $this->travel(1)->seconds();
        $this->reply($u, $ts, 'retry');
        $this->api('GET', '/agent/pending')->assertJsonPath('items.0.kind', 'retry');
        $this->move($id, 'In progress')->assertJsonPath('card.attempts', 2);
        $this->move($id, 'Failed');
        $this->travel(1)->seconds();
        $this->reply($u, $ts, 'retry');
        $this->api('GET', '/agent/pending')->assertJsonCount(0, 'items');
    }

    // ---------- the approval gate ----------

    private function waitingCard(User $u): array
    {
        $ts = $this->request($u);
        $id = $this->card($ts);
        $this->move($id, 'In progress')->assertOk();
        $this->move($id, 'Waiting approval')->assertOk();
        $this->travel(1)->seconds();

        return [$ts, $id];
    }

    public function test_deploy_is_refused_without_approval(): void
    {
        [$ts, $id] = $this->waitingCard(User::factory()->create());
        $this->api('GET', '/agent/approval?request_ts='.$ts)->assertJson(['ok' => true, 'status' => 'Waiting approval', 'approved' => false]);
        $this->move($id, 'Deploying')->assertStatus(409)->assertJsonPath('error', 'not_approved');
    }

    public function test_approve_reply_unlocks_deploy(): void
    {
        $reviewer = User::factory()->create(['name' => 'Yonder Reviewer']);
        [$ts, $id] = $this->waitingCard(User::factory()->create());
        $this->reply($reviewer, $ts, 'Approved, ship it');

        $this->api('GET', '/agent/approval?request_ts='.$ts)
            ->assertJson(['approved' => true, 'approved_by' => 'Yonder Reviewer', 'evidence' => 'thread reply: Approved, ship it']);
        $this->api('GET', '/agent/pending')->assertJsonPath('items.0.kind', 'approval');
        $this->move($id, 'Deploying')->assertOk()->assertJsonPath('card.approved_by', 'Yonder Reviewer');
        $this->move($id, 'Deployed')->assertOk();
        $this->assertNotNull(BoardCard::find($id)->deployed_at);
    }

    public function test_reaction_by_a_person_unlocks_deploy(): void
    {
        $reviewer = User::factory()->create(['name' => 'Yonder Reviewer']);
        [$ts, $id] = $this->waitingCard(User::factory()->create());
        $this->actingAs($reviewer)->postJson('/channel/reactions', ['ts' => $ts, 'name' => 'white_check_mark'])->assertOk();
        $this->api('GET', '/agent/approval?request_ts='.$ts)->assertJson(['approved' => true, 'evidence' => 'reaction :white_check_mark:']);
        $this->move($id, 'Deploying')->assertOk();
    }

    public function test_approval_given_before_waiting_does_not_count(): void
    {
        $u = User::factory()->create();
        $ts = $this->request($u);
        $id = $this->card($ts);
        $this->move($id, 'In progress');
        $this->reply($u, $ts, 'approve');      // too early
        $this->actingAs($u)->postJson('/channel/reactions', ['ts' => $ts, 'name' => 'white_check_mark']);
        $this->travel(1)->seconds();
        $this->move($id, 'Waiting approval');
        $this->api('GET', '/agent/approval?request_ts='.$ts)->assertJsonPath('approved', false);
        $this->move($id, 'Deploying')->assertStatus(409);
    }

    public function test_going_back_to_in_progress_needs_a_fresh_approval(): void
    {
        $u = User::factory()->create();
        [$ts, $id] = $this->waitingCard($u);
        $this->reply($u, $ts, 'approve');
        $this->move($id, 'In progress')->assertOk();   // more changes asked for
        $this->travel(1)->seconds();
        $this->move($id, 'Waiting approval')->assertOk();
        $this->travel(1)->seconds();
        $this->move($id, 'Deploying')->assertStatus(409);
    }

    public function test_words_that_only_contain_approve_do_not_count(): void
    {
        $u = User::factory()->create();
        [$ts, $id] = $this->waitingCard($u);
        foreach (['not approved yet', 'I disapprove', 'approval pending', 'unapproved'] as $text) {
            $this->reply($u, $ts, $text);
        }
        $this->api('GET', '/agent/approval?request_ts='.$ts)->assertJsonPath('approved', false);
    }

    public function test_agent_cannot_approve_itself(): void
    {
        [$ts, $id] = $this->waitingCard(User::factory()->create());
        $this->api('POST', '/slack/reactions.add', ['timestamp' => $ts, 'name' => 'white_check_mark'])
            ->assertStatus(403)->assertJsonPath('error', 'agent_cannot_approve');
        $this->api('POST', '/slack/chat.postMessage', ['text' => 'approved', 'thread_ts' => $ts])->assertOk();
        $this->api('GET', '/agent/approval?request_ts='.$ts)->assertJsonPath('approved', false);
        $this->move($id, 'Deploying')->assertStatus(409);
    }

    public function test_approval_lookup_for_unknown_card(): void
    {
        $this->api('GET', '/agent/approval?request_ts=1.2')->assertStatus(404)->assertJsonPath('error', 'card_not_found');
        $this->api('GET', '/agent/approval')->assertStatus(422);
    }

    // ---------- cards ----------

    public function test_card_creation_rules(): void
    {
        $u = User::factory()->create();
        $ts = $this->request($u);
        $this->api('POST', '/board/cards', ['request_ts' => '9.9', 'title' => 'Nothing'])->assertStatus(404)->assertJsonPath('error', 'request_not_found');
        $this->api('POST', '/board/cards', ['request_ts' => $ts, 'title' => 'ab'])->assertStatus(422);
        $this->card($ts);
        $this->api('POST', '/board/cards', ['request_ts' => $ts, 'title' => 'Again'])->assertStatus(422);

        $agentTs = $this->api('POST', '/slack/chat.postMessage', ['text' => 'agent says'])->json('ts');
        $this->api('POST', '/board/cards', ['request_ts' => $agentTs, 'title' => 'Agent card'])->assertStatus(404);

        $this->api('GET', '/board/cards')->assertOk()->assertJsonCount(1, 'cards');
    }

    public function test_bad_transitions_are_refused(): void
    {
        $id = $this->card($this->request(User::factory()->create()));
        $this->move($id, 'Deployed')->assertStatus(409)->assertJsonPath('error', 'bad_transition: New -> Deployed');
        $this->move($id, 'Waiting approval')->assertStatus(409);
        $this->move($id, 'Deploying')->assertStatus(409);
        $this->move($id, 'Nonsense')->assertStatus(422);
        $this->move($id, 'New')->assertOk();   // same status: no change
    }

    public function test_card_field_validation(): void
    {
        $id = $this->card($this->request(User::factory()->create()));
        $this->api('PATCH', "/board/cards/{$id}", ['branch' => 'fca/req-1; rm -rf /'])->assertStatus(422);
        $this->api('PATCH', "/board/cards/{$id}", ['commit_url' => 'https://evil.example/x'])->assertStatus(422);
        $this->api('PATCH', "/board/cards/{$id}", ['commit_url' => 'javascript:alert(1)'])->assertStatus(422);
        $this->api('PATCH', "/board/cards/{$id}", [
            'branch' => 'fca/req-1', 'commit_url' => 'https://github.com/dgb22736/x/commit/abc',
            'test_result' => '12 passed', 'notes' => 'ok',
        ])->assertOk()->assertJsonPath('card.branch', 'fca/req-1');
        $this->api('PATCH', '/board/cards/999', ['notes' => 'x'])->assertStatus(404);
    }

    public function test_board_page_shows_cards_escaped(): void
    {
        $ts = $this->request(User::factory()->create());
        $this->api('POST', '/board/cards', ['request_ts' => $ts, 'title' => '<script>x</script> title']);
        $this->get('/board')->assertOk()->assertSee('&lt;script&gt;x&lt;/script&gt; title', false)->assertDontSee('<script>x</script>', false);
    }
}

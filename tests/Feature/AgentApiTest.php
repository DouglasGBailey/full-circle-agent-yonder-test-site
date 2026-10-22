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

    // ---------- tickets from failed tests ----------

    private function tickets(int $id, array $titles): TestResponse
    {
        return $this->api('POST', "/board/cards/{$id}/tickets", [
            'tickets' => array_map(fn ($t) => ['title' => $t, 'detail' => "Failure output for {$t}"], $titles),
        ]);
    }

    /** A card in progress whose tests failed: tickets proposed and posted. Returns [ts, id]. */
    private function proposedCard(User $u, array $titles = ['Rooms page: check-in line missing', 'Firefox: menu does not close']): array
    {
        $ts = $this->request($u);
        $id = $this->card($ts);
        $this->move($id, 'In progress')->assertOk();
        $this->tickets($id, $titles)->assertCreated()->assertJsonPath('card.tickets.0.status', 'Proposed');
        $this->move($id, 'Tickets proposed')->assertOk();
        $this->travel(1)->seconds();

        return [$ts, $id];
    }

    public function test_failed_tests_become_proposed_tickets_on_the_card(): void
    {
        [, $id] = $this->proposedCard(User::factory()->create());
        $card = $this->api('GET', '/board/cards')->json('cards.0');
        $this->assertSame('Tickets proposed', $card['status']);
        $this->assertCount(2, $card['tickets']);
        $this->assertSame([1, 1], array_column($card['tickets'], 'round'));
        $this->assertSame(['Proposed', 'Proposed'], array_column($card['tickets'], 'status'));
        $this->get('/board')->assertOk()->assertSee('Tickets from failed tests')
            ->assertSee('Rooms page: check-in line missing')->assertSee('Round 1');
    }

    public function test_tickets_proposed_needs_a_proposed_ticket(): void
    {
        $id = $this->card($this->request(User::factory()->create()));
        $this->move($id, 'In progress')->assertOk();
        $this->move($id, 'Tickets proposed')->assertStatus(409)->assertJsonPath('error', 'no_proposed_tickets');
        $this->tickets($id, [])->assertOk();   // an empty round only closes; still none proposed
        $this->move($id, 'Tickets proposed')->assertStatus(409);
    }

    public function test_tickets_need_an_approve_reply_before_the_fix(): void
    {
        $u = User::factory()->create();
        [$ts, $id] = $this->proposedCard($u);
        $this->api('GET', '/agent/pending')->assertJsonCount(0, 'items');
        $this->move($id, 'In progress')->assertStatus(409)->assertJsonPath('error', 'not_approved');

        // A reaction is not enough, and neither is a word that only contains "approve".
        $this->actingAs($u)->postJson('/channel/reactions', ['ts' => $ts, 'name' => 'white_check_mark'])->assertOk();
        $this->reply($u, $ts, 'disapprove of this');
        $this->api('GET', '/agent/pending')->assertJsonCount(0, 'items');
        $this->move($id, 'In progress')->assertStatus(409);
    }

    public function test_approve_opens_the_tickets_for_the_fix(): void
    {
        $reviewer = User::factory()->create(['name' => 'Yonder Reviewer']);
        [$ts, $id] = $this->proposedCard(User::factory()->create());
        $this->reply($reviewer, $ts, 'approve');

        $this->api('GET', '/agent/pending')->assertJsonPath('items.0.kind', 'tickets_approval')
            ->assertJsonPath('items.0.user_name', 'Yonder Reviewer')->assertJsonPath('items.0.card_id', $id);
        $res = $this->move($id, 'In progress')->assertOk();
        $this->assertSame(['Open', 'Open'], array_column($res->json('card.tickets'), 'status'));
        $this->assertSame(['Yonder Reviewer', 'Yonder Reviewer'], array_column($res->json('card.tickets'), 'approved_by'));
        $this->assertSame(0, $res->json('card.attempts'));   // a fix round is not a retry
        $this->assertNull($res->json('card.approved_by'));   // and not a deploy approval
    }

    public function test_an_old_approve_does_not_approve_new_tickets(): void
    {
        $reviewer = User::factory()->create();
        $ts = $this->request(User::factory()->create());
        $id = $this->card($ts);
        $this->move($id, 'In progress')->assertOk();
        $this->travel(1)->seconds();
        $this->reply($reviewer, $ts, 'approve');
        $this->travel(1)->seconds();
        $this->tickets($id, ['A failing test'])->assertCreated();
        $this->move($id, 'Tickets proposed')->assertOk();
        $this->travel(1)->seconds();

        $this->api('GET', '/agent/pending')->assertJsonCount(0, 'items');
        $this->move($id, 'In progress')->assertStatus(409);
    }

    public function test_the_agent_cannot_approve_its_own_tickets(): void
    {
        [$ts, $id] = $this->proposedCard(User::factory()->create());
        $this->api('POST', '/slack/chat.postMessage', ['text' => 'approve', 'thread_ts' => $ts])->assertOk();
        $this->api('GET', '/agent/pending')->assertJsonCount(0, 'items');
        $this->move($id, 'In progress')->assertStatus(409);
    }

    public function test_next_round_marks_fixed_and_still_failing(): void
    {
        $reviewer = User::factory()->create();
        [$ts, $id] = $this->proposedCard(User::factory()->create(), ['Test A fails', 'Test B fails']);
        $this->reply($reviewer, $ts, 'approve');
        $this->move($id, 'In progress')->assertOk();

        // Round 2: A is fixed, B still fails, and C is new.
        $res = $this->tickets($id, ['Test B fails', 'Test C fails'])->assertCreated();
        $byRound = collect($res->json('card.tickets'))->map(fn ($t) => "{$t['round']} {$t['title']}: {$t['status']}")->all();
        $this->assertSame([
            '1 Test A fails: Fixed', '1 Test B fails: Still failing',
            '2 Test B fails: Proposed', '2 Test C fails: Proposed',
        ], $byRound);

        // Round 3 passes: an empty list closes round 2... once it is approved and Open.
        $this->move($id, 'Tickets proposed')->assertOk();
        $this->travel(1)->seconds();
        $this->reply($reviewer, $ts, 'Approve');
        $this->move($id, 'In progress')->assertOk();
        $res = $this->tickets($id, [])->assertOk();
        $this->assertSame(['Fixed', 'Still failing', 'Fixed', 'Fixed'], array_column($res->json('card.tickets'), 'status'));
        $this->move($id, 'Waiting approval')->assertOk();
    }

    public function test_rejecting_the_tickets_drops_them(): void
    {
        [, $id] = $this->proposedCard(User::factory()->create());
        $res = $this->move($id, 'Failed')->assertOk();
        $this->assertSame(['Dropped', 'Dropped'], array_column($res->json('card.tickets'), 'status'));
    }

    public function test_ticket_rounds_are_capped(): void
    {
        $reviewer = User::factory()->create();
        [$ts, $id] = $this->proposedCard(User::factory()->create(), ['Round 1 failure']);
        for ($round = 2; $round <= BoardCard::MAX_TICKET_ROUNDS + 1; $round++) {
            $this->reply($reviewer, $ts, 'approve');
            $this->move($id, 'In progress')->assertOk();
            $res = $this->tickets($id, ["Round {$round} failure"]);
            if ($round > BoardCard::MAX_TICKET_ROUNDS) {
                $res->assertStatus(409)->assertJsonPath('error', 'too_many_rounds');
                break;
            }
            $res->assertCreated();
            $this->move($id, 'Tickets proposed')->assertOk();
            $this->travel(1)->seconds();
        }
        $this->tickets($id, [])->assertOk();   // closing a round is always allowed
    }

    public function test_tickets_validation_and_status_rules(): void
    {
        $id = $this->card($this->request(User::factory()->create()));
        $this->tickets($id, ['A failing test'])->assertStatus(409)->assertJsonPath('error', 'bad_status: New');
        $this->move($id, 'In progress')->assertOk();
        $this->api('POST', "/board/cards/{$id}/tickets", [])->assertStatus(422);
        $this->api('POST', "/board/cards/{$id}/tickets", ['tickets' => [['title' => 'x']]])->assertStatus(422);
        $this->api('POST', "/board/cards/{$id}/tickets", ['tickets' => [['title' => str_repeat('a', 201)]]])->assertStatus(422);
        $this->api('POST', "/board/cards/{$id}/tickets", ['tickets' => [['title' => 'ok title', 'detail' => str_repeat('d', 2001)]]])->assertStatus(422);
        $this->api('POST', "/board/cards/{$id}/tickets", ['tickets' => array_fill(0, 11, ['title' => 'many'])])->assertStatus(422);
        $this->api('POST', '/board/cards/999/tickets', ['tickets' => []])->assertStatus(404);
        $this->withToken('wrong')->postJson("/api/board/cards/{$id}/tickets", ['tickets' => []])->assertStatus(401);
    }

    public function test_a_failed_live_check_can_propose_tickets_from_deploying(): void
    {
        $reviewer = User::factory()->create();
        [$ts, $id] = $this->waitingCard(User::factory()->create());
        $this->reply($reviewer, $ts, 'approve');
        $this->move($id, 'Deploying')->assertOk();
        $this->travel(1)->seconds();
        $this->tickets($id, ['Live: chat route broken'])->assertCreated();
        $this->move($id, 'Tickets proposed')->assertOk();
        $this->travel(1)->seconds();

        // The earlier deploy approval does not approve the tickets...
        $this->move($id, 'In progress')->assertStatus(409);
        // ...and after the fix, the deploy needs a fresh approve too.
        $this->reply($reviewer, $ts, 'approve');
        $this->move($id, 'In progress')->assertOk();
        $this->travel(5)->minutes();   // the fix run and its gates
        $this->move($id, 'Waiting approval')->assertOk();
        $this->move($id, 'Deploying')->assertStatus(409)->assertJsonPath('error', 'not_approved');
    }

    public function test_ticket_text_is_escaped_on_the_board(): void
    {
        [, $id] = $this->proposedCard(User::factory()->create(), ['<img src=x onerror=alert(1)> test']);
        $this->get('/board')->assertOk()->assertSee('&lt;img src=x onerror=alert(1)&gt; test', false)
            ->assertDontSee('<img src=x onerror', false);
    }

    public function test_board_page_shows_cards_escaped(): void
    {
        $ts = $this->request(User::factory()->create());
        $this->api('POST', '/board/cards', ['request_ts' => $ts, 'title' => '<script>x</script> title']);
        $this->get('/board')->assertOk()->assertSee('&lt;script&gt;x&lt;/script&gt; title', false)->assertDontSee('<script>x</script>', false);
    }
}

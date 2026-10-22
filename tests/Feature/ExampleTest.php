<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test: every public page of the Saltmarsh Hotel test site answers 200
 * for a visitor who is not signed in, and pages that need an account redirect.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_public_page_returns_a_successful_response(): void
    {
        foreach (['/', '/rooms', '/restaurant', '/contact', '/login', '/sitemap.xml'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_home_page_explains_the_ticket_loop(): void
    {
        $this->get('/')->assertOk()->assertSee('Any test failures generate proposed tickets which, if approved, get created and attended to by Claude Code, followed by testing again. This repeats until no failures are observed.');
    }

    public function test_the_channel_board_and_agent_api_moved_to_the_sdlc_app(): void
    {
        foreach (['/channel', '/channel/feed', '/board', '/api/agent/pending', '/api/slack/conversations.history'] as $path) {
            $this->get($path)->assertNotFound();
        }
        $this->post('/channel/messages', ['text' => 'hello'])->assertNotFound();
        $this->get('/')->assertSee('https://yonder-full-circle-sdlc.terminalvelocityai.tech/channel?project=yonder-test-site', false);
        $this->get('/sitemap.xml')->assertDontSee('/channel')->assertDontSee('/board');
    }
}

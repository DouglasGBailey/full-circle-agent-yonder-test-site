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
        foreach (['/', '/rooms', '/restaurant', '/contact', '/channel', '/channel/feed', '/board', '/login', '/sitemap.xml'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_posting_to_the_channel_needs_an_account(): void
    {
        $this->post('/channel/messages', ['text' => 'hello'])->assertRedirect('/login');
    }
}

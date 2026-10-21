<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Send the same session cookie on every following request, as a browser
     * does. Without it each test request starts a new session id, and state
     * keyed by the session id (the chat state) would not carry over.
     */
    protected function keepSession(?string $sessionId = null): string
    {
        $sessionId ??= Str::random(40);
        $this->withCredentials()->withCookie(config('session.cookie'), $sessionId);   // JSON requests send cookies only withCredentials()

        return $sessionId;
    }
}

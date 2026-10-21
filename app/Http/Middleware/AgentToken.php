<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects the agent API with one bearer token (FCA_API_TOKEN in .env).
 * With no token configured the API is switched off.
 */
class AgentToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.full_circle_agent.token');
        if (strlen($expected) < 32) {
            return response()->json(['ok' => false, 'error' => 'api_disabled'], 503);
        }
        $given = (string) $request->bearerToken();
        if ($given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'error' => 'invalid_auth'], 401);
        }

        return $next($request);
    }
}

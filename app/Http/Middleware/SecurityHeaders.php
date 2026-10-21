<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Security headers on every response (nginx adds HSTS in front as well). */
class SecurityHeaders
{
    /**
     * The one inline script on the site: it sets the theme before first paint
     * (dark by default, the visitor's saved choice otherwise), so there is no
     * flash. layouts/app.blade.php prints this constant and the CSP allows its
     * exact hash, so the two can never drift apart.
     */
    public const THEME_SCRIPT = "(function(){var t='dark';try{var s=localStorage.getItem('saltmarsh-theme');if(s==='light'||s==='dark'){t=s;}}catch(e){}document.documentElement.setAttribute('data-theme',t);})();";

    public static function themeScriptHash(): string
    {
        return 'sha256-'.base64_encode(hash('sha256', self::THEME_SCRIPT, true));
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;
        $h->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' '".self::themeScriptHash()."'",
            "style-src 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ]));
        $h->set('X-Frame-Options', 'DENY');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        $h->remove('X-Powered-By');

        return $response;
    }
}

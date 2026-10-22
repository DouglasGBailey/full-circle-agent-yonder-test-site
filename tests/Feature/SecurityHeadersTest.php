<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every page carries the security headers, and the CSP hash matches the inline theme script. */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_render_with_security_headers(): void
    {
        foreach (['/', '/rooms', '/restaurant', '/contact', '/login'] as $path) {
            $res = $this->get($path)->assertOk();
            $res->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeaderMissing('X-Powered-By');
            $csp = $res->headers->get('Content-Security-Policy');
            $this->assertStringContainsString("frame-ancestors 'none'", $csp, $path);
            $this->assertStringNotContainsString('unsafe-inline', $csp, $path);
        }
    }

    public function test_csp_hash_matches_the_inline_script_the_layout_prints(): void
    {
        $html = $this->get('/')->getContent();
        $this->assertSame(1, preg_match('#<script>(.*?)</script>#s', $html, $m));
        $this->assertSame(SecurityHeaders::THEME_SCRIPT, $m[1]);
        $expected = 'sha256-'.base64_encode(hash('sha256', $m[1], true));
        $this->assertStringContainsString("'{$expected}'", $this->get('/')->headers->get('Content-Security-Policy'));
        // No other inline scripts or inline styles (CSP would block them).
        $this->assertSame(0, preg_match('/\sstyle="/', $html));
        $this->assertSame(1, substr_count($html, '<script>'));
    }

    public function test_seo_and_footer(): void
    {
        $html = $this->get('/')->getContent();
        foreach (['<meta name="description"', '<link rel="canonical"', 'og:title', 'twitter:card', 'application/ld+json',
            '<meta name="author" content="VML Yonder">', '&copy; 2026 VML Yonder. All rights reserved.', '"name":"VML Yonder"',
            'role="switch"', 'image/svg+xml'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        // Removed from the footer at the owner's request (2026-10-21).
        $this->assertStringNotContainsString('Built with love and Claude Code', $html);
        $this->assertStringNotContainsString('Hero photo:', $html);
        // The company name is VML Yonder (owner's request, 2026-10-21).
        $this->assertStringNotContainsString('Terminal Velocity AI. All rights reserved.', $html);
        // Pilot test accounts come from Douglas Bailey (owner's request, 2026-10-22).
        $contact = $this->get('/contact')->getContent();
        $this->assertStringContainsString('ask Douglas Bailey for a test account.', $contact);
        $this->assertStringNotContainsString('Terminal Velocity AI', $contact);
        $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('Sitemap:', file_get_contents(public_path('robots.txt')));   // static file, served by the web server
        $this->get('/no-such-page')->assertNotFound();
    }
}

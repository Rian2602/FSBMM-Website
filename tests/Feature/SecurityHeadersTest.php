<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B / T2 "def-site blocking": the site must never be frameable inside a
 * third-party page and every response carries hardened headers. Verified on
 * the public routes (web group) and both Filament panels (which run their own
 * middleware stack — regression guard for the dual mounting).
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_web_route_sends_def_site_blocking_headers(): void
    {
        // robots.txt is a public web-group route rendered without the @vite
        // layout, so it stays testable even when the Vite manifest is missing
        // (local env without `npm run build`).
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; form-action 'self'")
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_admin_panel_login_page_gets_the_headers(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; form-action 'self'");
    }

    public function test_sba_panel_login_page_gets_the_headers(): void
    {
        $this->get('/panel-sba/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_full_public_page_render_gets_the_headers(): void
    {
        if (! file_exists(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Vite manifest missing — run `npm run build` locally.');
        }

        $this->get('/e-resource')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}

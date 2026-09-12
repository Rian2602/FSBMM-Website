<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defensive security headers applied to every public page AND both Filament
 * panels (mounted twice: once in the `web` route group via bootstrap/app.php,
 * once per PanelProvider->middleware() because Filament panels run their own
 * middleware stack instead of the `web` group).
 *
 * Phase B / T2 "def-site blocking": a third-party site ("def-site") must not
 * be able to frame this site inside a disguised page (clickjacking / anti
 * drive-by), leak referrers across origins, or get browsers to content-sniff
 * our responses. The CSP directive `form-action 'self'` also pins every HTML
 * form to this origin.
 *
 * (** executed: the plan snippet also mooted a full default-src CSP. That is
 * deliberately NOT shipped: the public site loads Google Fonts, runs a
 * dependency-free site.js plus Livewire/Filament inline bootstrap, and renders
 * staff-authored "trusted HTML" articles that may embed third-party frames
 * (e.g. YouTube). A restrictive default-src would break those surfaces for no
 * extra protection — `frame-ancestors 'none'` + X-Frame-Options DENY already
 * close the "def-site" embedding vector. **)
 */
class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'; form-action 'self'");
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}

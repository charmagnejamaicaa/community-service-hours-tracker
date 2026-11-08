<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Safe default headers on every /api response. */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/*')) {
            return $response;
        }

        $h = $response->headers;
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'no-referrer');
        $h->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        $h->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
        // Responses can contain tokens and personal data: browsers and proxies must not keep a copy.
        $h->set('Cache-Control', 'no-store, private');
        if ($request->isSecure() && config('service_hours.hsts')) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}

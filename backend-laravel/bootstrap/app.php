<?php

use App\Http\Middleware\AssignReferenceId;
use App\Http\Middleware\RequireHttps;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => RequireRole::class]);
        // This is a JSON API: there is no login page to redirect to, so a missing token must give a 401, not a redirect.
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->api(append: [AssignReferenceId::class]);
        // These two only act on /api/* (they check the path themselves).
        // RequireHttps must run AFTER Laravel's TrustProxies step, otherwise it never sees the proxy's
        // X-Forwarded-Proto header and refuses even the HTTPS requests that come through the gateway.
        $middleware->append(RequireHttps::class);
        $middleware->append(SecurityHeaders::class);
        // Behind the local HTTPS gateway (Caddy) the gateway's X-Forwarded-Proto header says the request was HTTPS.
        // Only the computer itself (loopback) is trusted to say so, so a remote client cannot fake the header.
        // (Not read from .env: this file runs before .env is loaded.)
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always answer /api/* errors as JSON, even when the client sends no Accept header.
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson());
        // Every error on /api/* uses the same format: { error: { code, message, details?, referenceId } }
        $exceptions->render(function (Throwable $e, Request $request) {
            return ApiExceptionRenderer::render($e, $request);
        });
    })->create();

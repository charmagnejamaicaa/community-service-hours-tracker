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
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->api(append: [AssignReferenceId::class]);
        // These two only act on /api/* (they check the path themselves).
        $middleware->prepend(RequireHttps::class);
        $middleware->append(SecurityHeaders::class);
        // Behind a local HTTPS proxy (Caddy) the proxy's X-Forwarded-Proto header says the request was HTTPS.
        if (env('TRUST_PROXIES')) {
            $middleware->trustProxies(at: '*');
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
         $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson());
        // Every error on /api/* uses the same format: { error: { code, message, details?, referenceId } }
        $exceptions->render(function (Throwable $e, Request $request) {
            return ApiExceptionRenderer::render($e, $request);
        });
    })->create();

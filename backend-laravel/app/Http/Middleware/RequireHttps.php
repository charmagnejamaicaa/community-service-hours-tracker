<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When REQUIRE_HTTPS=true, every /api request must arrive over HTTPS. Otherwise it is refused
 * (not redirected) so that credentials and tokens are never accepted over plain HTTP.
 */
final class RequireHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('service_hours.require_https') && $request->is('api/*') && ! $request->isSecure()) {
            return ApiError::response($request, 426, 'HTTPS_REQUIRED', 'Use https:// to reach this API.');
        }

        return $next($request);
    }
}

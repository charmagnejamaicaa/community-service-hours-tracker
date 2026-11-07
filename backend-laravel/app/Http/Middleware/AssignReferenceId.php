<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Gives every request a reference ID so errors can be matched to log lines. */
final class AssignReferenceId
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Reference-Id', ApiError::refId($request));

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\EventLogger;
use App\Support\ApiError;
use App\Support\Workflow;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based authorization. Runs AFTER `auth:sanctum`, so the user is already known from a valid token.
 * The role comes from the database record of that user, never from anything the client sends.
 *
 * Usage on a route: ->middleware('role:supervisor,coordinator')
 */
final class RequireRole
{
    public function __construct(private readonly EventLogger $logger)
    {
    }

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if ($user === null) {
            return ApiError::response($request, 401, 'UNAUTHENTICATED', 'Please sign in again.');
        }

        if (! in_array($user->role, $roles, true)) {
            $this->logger->warn('forbidden', [
                'referenceId' => ApiError::refId($request),
                'method' => $request->method(),
                'route' => $request->route()?->uri(),
                'userId' => $user->id,
                'role' => in_array($user->role, Workflow::ROLES, true) ? $user->role : 'unknown',
            ]);

            return ApiError::response($request, 403, 'FORBIDDEN', 'You are not allowed to do that.');
        }

        // A student account must be linked to a student record.
        if ($user->role === Workflow::STUDENT && $user->student_id === null) {
            return ApiError::response($request, 403, 'FORBIDDEN', 'This account is not linked to a student.');
        }

        return $next($request);
    }
}

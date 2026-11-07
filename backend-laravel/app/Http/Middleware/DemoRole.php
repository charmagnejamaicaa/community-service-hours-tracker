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
 * Week 13 only: the caller's role comes from the demo header X-Demo-Role
 * (student | supervisor | coordinator). Real authentication replaces this in Week 14.
 *
 * Usage on a route: ->middleware('demo.role:supervisor,coordinator')
 */
final class DemoRole
{
    public function __construct(private readonly EventLogger $logger)
    {
    }

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = strtolower((string) $request->header('X-Demo-Role', ''));

        if (! in_array($role, $roles, true)) {
            $this->logger->warn('forbidden', [
                'referenceId' => ApiError::refId($request),
                'method' => $request->method(),
                'route' => $request->route()?->uri(),
                // Only known role names are logged (never raw header text).
                'role' => in_array($role, Workflow::ROLES, true) ? $role : 'none',
            ]);

            return ApiError::response($request, 403, 'FORBIDDEN', 'This action needs the role: ' . implode(' or ', $roles) . '.');
        }

        $request->attributes->set('role', $role);

        return $next($request);
    }
}

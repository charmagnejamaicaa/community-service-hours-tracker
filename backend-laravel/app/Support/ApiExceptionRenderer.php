<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\EventLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/** Turns any error on an /api route into the standard error format (no internal details leaked). */
final class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        // Missing, invalid, tampered or expired token (Sanctum rejects all of them the same way).
        if ($e instanceof AuthenticationException) {
            return ApiError::response($request, 401, 'UNAUTHENTICATED', 'Please sign in again.');
        }
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return ApiError::response($request, 404, 'NOT_FOUND', 'Not found.');
        }
        if ($e instanceof AuthorizationException || ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 403)) {
            return ApiError::response($request, 403, 'FORBIDDEN', 'You are not allowed to do that.');
        }
        if ($e instanceof ValidationException) {
            return ApiError::validation($request, collect($e->errors())->flatten()->all());
        }
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 429) {
            $response = ApiError::response($request, 429, 'RATE_LIMITED', 'Too many attempts. Please wait and try again.');
            foreach ($e->getHeaders() as $name => $value) {
                $response->headers->set($name, (string) $value); // keeps Retry-After
            }

            return $response;
        }
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return ApiError::response($request, $e->getStatusCode(), 'REQUEST_ERROR', 'The request could not be processed.');
        }

        $fields = ['referenceId' => ApiError::refId($request), 'route' => $request->path(), 'reason' => $e::class];
        // The message can contain SQL or personal data, so it is logged only while debugging.
        if (config('app.debug')) {
            $fields['detail'] = $e->getMessage();
        }
        app(EventLogger::class)->error('unhandled_error', $fields);

        return ApiError::response($request, 500, 'SERVER_ERROR', 'Something went wrong. Please try again.');
    }
}

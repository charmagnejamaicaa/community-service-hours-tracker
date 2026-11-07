<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\EventLogger;
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

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return ApiError::response($request, 404, 'NOT_FOUND', 'Not found.');
        }
        if ($e instanceof ValidationException) {
            return ApiError::validation($request, collect($e->errors())->flatten()->all());
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

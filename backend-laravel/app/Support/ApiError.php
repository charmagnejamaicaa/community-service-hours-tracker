<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The one error format every endpoint uses:
 *   { "error": { "code", "message", "details"?, "referenceId" } }
 * The referenceId also appears in the logs, so an error can be matched to its log lines.
 */
final class ApiError
{
    public static function refId(Request $request): string
    {
        $id = $request->attributes->get('referenceId');
        if (! is_string($id)) {
            $id = 'req-' . bin2hex(random_bytes(4));
            $request->attributes->set('referenceId', $id);
        }

        return $id;
    }

    public static function response(Request $request, int $status, string $code, string $message, ?array $details = null): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $error['details'] = $details;
        }
        $error['referenceId'] = self::refId($request);

                // Set the header here too: on a 401 the sign-in check runs before AssignReferenceId, so the middleware never sees it.
        return response()->json(['error' => $error], $status, ['X-Reference-Id' => $error['referenceId']]);
    }

    /** @param list<string> $errors */
    public static function validation(Request $request, array $errors): JsonResponse
    {
        return self::response($request, 400, 'VALIDATION_ERROR', implode(' ', $errors), $errors);
    }

    /** "5" -> 5, "abc" -> null */
    public static function parseId(mixed $value): ?int
    {
        return is_string($value) && preg_match('/^[1-9][0-9]{0,9}$/', $value) === 1 ? (int) $value : null;
    }
}

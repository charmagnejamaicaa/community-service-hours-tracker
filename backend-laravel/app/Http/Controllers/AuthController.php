<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\EventLogger;
use App\Models\User;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/** Sign in, sign out, and "who am I". */
final class AuthController
{
    // A valid bcrypt hash of a random string. It is checked when the email is unknown, so a wrong
    // email and a wrong password take about the same time (no way to tell which accounts exist).
    private const DUMMY_HASH = '$2y$12$3.n07KnmAI8M1M.vmnzKfepLTw.mjrMey/RdnVm.V8n5itkspnDOK';

    private const TOKEN_NAME = 'flutter-web';

    public function __construct(private readonly EventLogger $logger)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $b = $request->json()->all();
        $email = $b['email'] ?? null;
        $password = $b['password'] ?? null;

        $errors = [];
        if (! is_string($email) || mb_strlen($email) > 254 || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'A valid email is required.';
        }
        if (! is_string($password) || $password === '' || strlen($password) > 200) {
            $errors[] = 'Password is required.';
        }
        if ($errors !== []) {
            return ApiError::validation($request, $errors);
        }

        $user = User::where('email', strtolower(trim($email)))->first();
        $passwordOk = Hash::check($password, $user?->password ?? self::DUMMY_HASH);

        if ($user === null || ! $passwordOk) {
            // The message is the same for "no such email" and "wrong password". The email is not logged.
            $this->logger->warn('login_failed', ['referenceId' => ApiError::refId($request)]);

            return ApiError::response($request, 401, 'INVALID_CREDENTIALS', 'Email or password is incorrect.');
        }

        // One active sign-in per user for this app: the old token stops working.
        $user->tokens()->where('name', self::TOKEN_NAME)->delete();
        $expiresAt = now()->addMinutes((int) config('service_hours.token_minutes'));
        $token = $user->createToken(self::TOKEN_NAME, [$user->role], $expiresAt);

        $this->logger->info('login_succeeded', [
            'referenceId' => ApiError::refId($request),
            'userId' => $user->id,
            'role' => $user->role,
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'tokenType' => 'Bearer',
            'expiresAt' => $expiresAt->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete(); // the token stops working immediately
        }
        $this->logger->info('logout', ['referenceId' => ApiError::refId($request), 'userId' => $request->user()->id]);

        return response()->json(['status' => 'signed_out']);
    }

    /** Only what the app needs (data minimization): no email, no password hash. */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'studentId' => $user->student_id === null ? null : (int) $user->student_id,
        ];
    }
}

<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

// All routes here start with /api (see bootstrap/app.php).
//
// Week 14 security:
//   auth:sanctum  = the request must carry a valid, unexpired Bearer token
//   role:...      = the signed-in user's role (from the database) must be one of the listed roles
// Only /health and /auth/login are public.

Route::get('/health', [ApiController::class, 'health']);

// Sign in. Limited to a few attempts per minute (see AppServiceProvider) to slow down password guessing.
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Progress: a student sees only their own (checked in the controller).
    Route::get('/students/{id}/progress', [ApiController::class, 'studentProgress'])
        ->middleware('role:student,coordinator,adviser');
    Route::get('/summaries/progress', [ApiController::class, 'progressSummary'])
        ->middleware('role:coordinator,adviser');

    Route::post('/jobs/run', [ApiController::class, 'runJob'])->middleware('role:coordinator');

    Route::get('/notifications/failed', [ApiController::class, 'failedNotifications'])->middleware('role:coordinator');
    Route::patch('/notifications/{id}/handled', [ApiController::class, 'markHandled'])->middleware('role:coordinator');

    // Student: own activities and submissions only (checked in the controller).
    Route::post('/activities', [WorkflowController::class, 'registerActivity'])->middleware('role:student');
    Route::post('/activities/{id}/submissions', [WorkflowController::class, 'submitHours'])->middleware('role:student');
    Route::patch('/submissions/{id}/resubmit', [WorkflowController::class, 'resubmit'])->middleware('role:student');

    Route::get('/submissions', [WorkflowController::class, 'listSubmissions'])->middleware('role:supervisor,coordinator');
    Route::patch('/submissions/{id}/verify', [WorkflowController::class, 'verify'])->middleware('role:supervisor');
    Route::patch('/submissions/{id}/approve', [WorkflowController::class, 'approve'])->middleware('role:coordinator');
    Route::patch('/submissions/{id}/correct', [WorkflowController::class, 'correct'])->middleware('role:coordinator');
});

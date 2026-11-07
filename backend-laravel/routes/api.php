<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

// All routes here start with /api (see bootstrap/app.php).
// Week 13: the role comes from the X-Demo-Role header. Real sign-in replaces demo.role in Week 14.

Route::get('/health', [ApiController::class, 'health']);
Route::get('/students/{id}/progress', [ApiController::class, 'studentProgress']);
Route::get('/summaries/progress', [ApiController::class, 'progressSummary']);

Route::post('/jobs/run', [ApiController::class, 'runJob'])->middleware('demo.role:coordinator');

Route::get('/notifications/failed', [ApiController::class, 'failedNotifications']);
Route::patch('/notifications/{id}/handled', [ApiController::class, 'markHandled']);

Route::post('/activities', [WorkflowController::class, 'registerActivity'])->middleware('demo.role:student');
Route::post('/activities/{id}/submissions', [WorkflowController::class, 'submitHours'])->middleware('demo.role:student');
Route::get('/submissions', [WorkflowController::class, 'listSubmissions'])->middleware('demo.role:supervisor,coordinator');
Route::patch('/submissions/{id}/verify', [WorkflowController::class, 'verify'])->middleware('demo.role:supervisor');
Route::patch('/submissions/{id}/approve', [WorkflowController::class, 'approve'])->middleware('demo.role:coordinator');
Route::patch('/submissions/{id}/correct', [WorkflowController::class, 'correct'])->middleware('demo.role:coordinator');
Route::patch('/submissions/{id}/resubmit', [WorkflowController::class, 'resubmit'])->middleware('demo.role:student');

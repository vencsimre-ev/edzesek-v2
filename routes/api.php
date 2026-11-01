<?php

use App\Http\Controllers\Api\V1\ApproveUserController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\LinkParticipantUserController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ParticipantController;
use App\Http\Controllers\Api\V1\TrainingSessionController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/training-sessions/sync-weekly', [TrainingSessionController::class, 'syncWeekly'])
    ->name('api.v1.training-sessions.sync-weekly');

Route::prefix('v1')->name('api.v1.')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show'])->name('me');

    Route::middleware(['verified', 'approved', 'admin'])->group(function () {
        Route::apiResource('training-sessions.attendances', AttendanceController::class)
            ->parameters(['training-sessions' => 'trainingSession'])->scoped()->only(['index', 'store', 'destroy']);
        Route::apiResource('training-sessions', TrainingSessionController::class)
            ->parameters(['training-sessions' => 'trainingSession'])
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('/training-sessions/{trainingSession}/complete', [TrainingSessionController::class, 'complete'])->name('training-sessions.complete');
        Route::post('/training-sessions/{trainingSession}/cancel', [TrainingSessionController::class, 'cancel'])->name('training-sessions.cancel');

        Route::apiResource('participants', ParticipantController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('/participants/{participant}/link-user', [LinkParticipantUserController::class, 'store'])
            ->name('participants.link-user');
    });

    Route::prefix('admin')->name('admin.')->middleware(['verified', 'approved', 'admin'])->group(function () {
        Route::apiResource('users', UserController::class)->only('index');
        Route::patch('/users/{user}/approve', [ApproveUserController::class, 'approve'])->name('users.approve');
    });
});

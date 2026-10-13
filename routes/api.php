<?php

use App\Http\Controllers\Api\V1\ApproveUserController;
use App\Http\Controllers\Api\V1\LinkParticipantUserController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ParticipantController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', MeController::class)->name('me');

    Route::middleware(['verified', 'approved', 'admin'])->group(function () {
        Route::apiResource('participants', ParticipantController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('/participants/{participant}/link-user', LinkParticipantUserController::class)
            ->name('participants.link-user');
    });

    Route::prefix('admin')->name('admin.')->middleware(['verified', 'approved', 'admin'])->group(function () {
        Route::apiResource('users', UserController::class)->only('index');
        Route::patch('/users/{user}/approve', ApproveUserController::class)->name('users.approve');
    });
});

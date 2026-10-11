<?php

use App\Http\Controllers\Web\ApprovalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/approval-pending', function (Request $request) {
    return $request->user()->isApproved()
        ? redirect()->route('home')
        : view('auth.pending');
})->middleware(['auth', 'verified'])->name('approval.pending');

Route::middleware(['auth', 'verified', 'approved'])->group(function () {
    Route::view('/home', 'home')->name('home');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::patch('/approvals/{user}', [ApprovalController::class, 'update'])->name('approvals.update');
    });
});

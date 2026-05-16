<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\RfqController;
use Illuminate\Support\Facades\Route;

// ── Auth ───────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ── Protected routes ───────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/', fn() => redirect()->route('rfq.index'));

    // RFQ (read from Odoo)
    Route::get('/rfq', [RfqController::class, 'index'])->name('rfq.index');
    Route::post('/rfq/refresh', [RfqController::class, 'refresh'])->name('rfq.refresh');
    Route::get('/rfq/{id}', [RfqController::class, 'show'])->name('rfq.show')->where('id', '[0-9]+');

    // Comparisons (stored locally)
    Route::get('/comparisons', [ComparisonController::class, 'index'])->name('comparisons.index');
    Route::post('/comparisons', [ComparisonController::class, 'store'])->name('comparisons.store');
    Route::get('/comparisons/{comparison}', [ComparisonController::class, 'show'])->name('comparisons.show');
    Route::post('/comparisons/{comparison}/approve', [ComparisonController::class, 'approve'])->name('comparisons.approve');
    Route::post('/comparisons/{comparison}/reject', [ComparisonController::class, 'reject'])->name('comparisons.reject');
});

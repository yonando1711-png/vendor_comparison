<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\MasterSupplierController;
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
    Route::get('/rfq-list', [RfqController::class, 'rfqList'])->name('rfq.list');
    Route::post('/rfq/refresh', [RfqController::class, 'refresh'])->name('rfq.refresh');
    Route::get('/rfq/{id}', [RfqController::class, 'show'])->name('rfq.show')->where('id', '[0-9]+');

    // Comparisons (stored locally)
    Route::get('/comparisons', [ComparisonController::class, 'index'])->name('comparisons.index');
    Route::post('/comparisons', [ComparisonController::class, 'store'])->name('comparisons.store');
    Route::get('/comparisons/{comparison}', [ComparisonController::class, 'show'])->name('comparisons.show');
    Route::get('/comparisons/{comparison}/edit', [ComparisonController::class, 'edit'])->name('comparisons.edit');
    Route::put('/comparisons/{comparison}', [ComparisonController::class, 'update'])->name('comparisons.update');
    Route::post('/comparisons/{comparison}/approve', [ComparisonController::class, 'approve'])->name('comparisons.approve');
    Route::post('/comparisons/{comparison}/reject', [ComparisonController::class, 'reject'])->name('comparisons.reject');
    Route::post('/comparisons/{comparison}/cancel', [ComparisonController::class, 'cancel'])->name('comparisons.cancel');
    Route::get('/comparisons/{comparison}/pdf', [ComparisonController::class, 'pdf'])->name('comparisons.pdf');
    Route::post('/comparisons/{comparison}/odoo-post', [ComparisonController::class, 'odooPost'])->name('comparisons.odoo-post');

    // Master Suppliers (local vendors not in Odoo)
    Route::get('/master-suppliers', [MasterSupplierController::class, 'index'])->name('master-suppliers.index');
    Route::post('/master-suppliers', [MasterSupplierController::class, 'store'])->name('master-suppliers.store');
    Route::put('/master-suppliers/{masterSupplier}', [MasterSupplierController::class, 'update'])->name('master-suppliers.update');
    Route::delete('/master-suppliers/{masterSupplier}', [MasterSupplierController::class, 'destroy'])->name('master-suppliers.destroy');

    // Admin: user management
    Route::get('/admin/users', [AdminController::class, 'index'])->name('admin.users');
    Route::post('/admin/users', [AdminController::class, 'store'])->name('admin.users.store');
    Route::put('/admin/users/{user}', [AdminController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [AdminController::class, 'destroy'])->name('admin.users.destroy');
});

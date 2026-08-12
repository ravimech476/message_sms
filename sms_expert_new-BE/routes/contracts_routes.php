<?php

/*
|--------------------------------------------------------------------------
| Contract Routes
|--------------------------------------------------------------------------
|
| These routes are automatically included in web.php
|
*/

use App\Http\Controllers\Admin\ContractController as AdminContractController;
use App\Http\Controllers\Customer\ContractController as CustomerContractController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CustomerMiddleware;

// Admin Contract Routes (protected by AdminMiddleware)
Route::middleware([AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/contracts', [AdminContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/create', [AdminContractController::class, 'create'])->name('contracts.create');
    Route::post('/contracts', [AdminContractController::class, 'store'])->name('contracts.store');
    Route::get('/contracts/{id}/edit', [AdminContractController::class, 'edit'])->name('contracts.edit');
    Route::put('/contracts/{id}', [AdminContractController::class, 'update'])->name('contracts.update');
    Route::delete('/contracts/{id}', [AdminContractController::class, 'destroy'])->name('contracts.destroy');
    Route::get('/contracts/{id}/signatures', [AdminContractController::class, 'signatures'])->name('contracts.signatures');
    Route::put('/contracts/{id}/toggle-status', [AdminContractController::class, 'toggleStatus'])->name('contracts.toggle-status');
    Route::get('/contracts/{id}/download', [AdminContractController::class, 'downloadFile'])->name('contracts.download');
});

// Customer Contract Routes (protected by CustomerMiddleware)
Route::middleware([CustomerMiddleware::class])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/contracts', [CustomerContractController::class, 'index'])->name('contracts.index');
    Route::post('/contracts/resign', [CustomerContractController::class, 'resign'])->name('contracts.resign'); // generic re-sign after profile change
    Route::get('/contracts/debug', [CustomerContractController::class, 'debug'])->name('contracts.debug'); // Temporary debug route
    Route::get('/contracts/{id}', [CustomerContractController::class, 'show'])->name('contracts.show');
    Route::post('/contracts/{id}/sign', [CustomerContractController::class, 'sign'])->name('contracts.sign');
    Route::get('/contracts/{id}/download', [CustomerContractController::class, 'downloadFile'])->name('contracts.download');
    Route::get('/contracts/{id}/view-file', [CustomerContractController::class, 'viewFile'])->name('contracts.view-file');
});

<?php

/*
|--------------------------------------------------------------------------
| User Margin Routes
|--------------------------------------------------------------------------
|
| Add these routes to your web.php file under the admin routes group
|
*/

// User Margin Management Routes
Route::prefix('admin/users/{user}')->name('admin.user.')->middleware(['auth', 'admin'])->group(function () {
    // Update user margin
    Route::put('/margin', [App\Http\Controllers\Admin\UserMarginController::class, 'update'])
        ->name('margin.update');
    
    // Get user margin
    Route::get('/margin', [App\Http\Controllers\Admin\UserMarginController::class, 'show'])
        ->name('margin.show');
    
    // Toggle margin active status
    Route::patch('/margin/toggle', [App\Http\Controllers\Admin\UserMarginController::class, 'toggle'])
        ->name('margin.toggle');
    
    // Delete/reset margin
    Route::delete('/margin', [App\Http\Controllers\Admin\UserMarginController::class, 'destroy'])
        ->name('margin.destroy');
});

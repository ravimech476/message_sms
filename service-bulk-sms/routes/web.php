<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\Http\Controllers\Admin\MessagesController;
use App\Http\Controllers\Admin\PracticesController;
use App\Http\Controllers\Receipts\ReceiptHandlerController;

Route::middleware('auth')->group(function(){
    Route::redirect('/', '/practices');
    Route::get('/practices', [PracticesController::class, 'index'])->name('practices');
    Route::post('/practices/update', [PracticesController::class, 'update'])->name('practices.update');
    Route::get('/messages', [MessagesController::class, 'index'])->name('messages');
});

Route::any('receipts', ReceiptHandlerController::class);

Route::namespace('\App\Http\Controllers')->group(function(){
    Route::auth([
        'register' => false,
        'verify' => false
    ]);
});


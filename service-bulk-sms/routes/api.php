<?php

use App\Http\Controllers\Api\MessagesController;
use App\Http\Controllers\Api\PracticesController;
use App\Http\Controllers\Api\ProviderController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('practices', PracticesController::class)->name('api.practices');
Route::get('providers', [ProviderController::class, 'get'])->name('api.providers');
Route::post('providers', [ProviderController::class, 'update'])->name('api.providers.update');
Route::post('messages', [MessagesController::class, 'send'])->name('api.messages.send');
Route::get('messages/{id}', [MessagesController::class, 'get'])->name('api.messages');

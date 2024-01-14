<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

// Auth::routes();
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/email', [AuthController::class, 'sendResetLinkEmail'])->name('api.password.email');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'userDetails']);
    Route::get('/logout', [UserController::class, 'logOut']);

    Route::get('/chat_create', [ChatController::class, 'index']);
    Route::post('/chat_store', [ChatController::class, 'store']);

    Route::get('/chat_show', [ChatController::class, 'show']);




    // Route::apiResource('chat', ChatController::class)->only(['index', 'store', 'show']);
});

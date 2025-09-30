<?php

use Phiki\Phast\Root;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TelegramAuthController;
use App\Http\Controllers\TelegramAccountController;

// Sanctum CSRF cookie route (already provided by Sanctum if using the sanctum middleware group)
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['csrf' => csrf_token()]);
});

// Register route
Route::post('/register', [AuthController::class, 'register']);

// Login route
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Protected user route
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Logout route
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    });

    Route::post('profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::post('profile/change-password', [AuthController::class, 'changePassword'])->name('profile.change-password');

    Route::post('sync-saved-messages', [AuthController::class, 'syncSavedMessages'])->name('saved.messages.sync');
    Route::get('get-synced-saved-messages', [AuthController::class, 'getSyncedSavedMessages'])->name('saved.messages.index');
    Route::post('sync-todo-list', [AuthController::class, 'syncToDoList'])->name('todo.list.sync');
    Route::get('get-synced-todo-list', [AuthController::class, 'getSyncedToDoList'])->name('todo.list.index');
});

Route::middleware('auth:sanctum')->group(function () {

    Route::group(['prefix' => 'telegram-accounts'], function () {
        Route::get('/', [TelegramAccountController::class, 'index'])->name('telegram-accounts.index');
        Route::post('session', [TelegramAccountController::class, 'checkSession'])->name('telegram-accounts.check-session');

        Route::post('/send-message', [TelegramAccountController::class, 'sendMessage'])->name('telegram-accounts.send-message');
        Route::get('/messages', [TelegramAccountController::class, 'pollMessages'])->name('telegram-accounts.messages');
    });
    Route::group(['prefix' => 'auth'], function () {
        Route::post('telegram/start-login', [TelegramAuthController::class, 'startLogin']);
        Route::post('telegram/check-login', [TelegramAuthController::class, 'checkLogin']);
        Route::post('telegram/submit-phone', [TelegramAuthController::class, 'submitPhone']);
        Route::post('telegram/submit-code', [TelegramAuthController::class, 'submitCode']);
        Route::post('telegram/submit-password', [TelegramAuthController::class, 'submitPassword']);
        Route::post('telegram/submit-signup', [TelegramAuthController::class, 'submitSignup']);
    });
});
Route::get('/account/test', [TelegramAccountController::class, 'testChatList'])->name('telegram-accounts.testChatList');
Route::get('/account/chat/{id}', [TelegramAccountController::class, 'testChatHistory'])->name('telegram.chat.history');

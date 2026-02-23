<?php

use App\Http\Controllers\DiscordWebhookController;
use App\Http\Controllers\Laraclaw\DashboardController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Laraclaw Webhook Routes
Route::prefix('laraclaw/webhooks')->group(function () {
    Route::post('telegram', TelegramWebhookController::class)->name('laraclaw.webhooks.telegram');
    Route::post('discord', DiscordWebhookController::class)->name('laraclaw.webhooks.discord');
});

// Laraclaw Dashboard Routes
Route::prefix('laraclaw')->name('laraclaw.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/conversations', [DashboardController::class, 'conversations'])->name('conversations');
    Route::get('/conversations/{conversation}', [DashboardController::class, 'showConversation'])->name('conversation');
    Route::get('/memories', [DashboardController::class, 'memories'])->name('memories');
    Route::get('/metrics', [DashboardController::class, 'metrics'])->name('metrics');
    Route::get('/chat', [DashboardController::class, 'chat'])->name('chat');
    Route::post('/chat', [DashboardController::class, 'sendMessage'])->name('chat.send');
    Route::post('/chat/stream', [DashboardController::class, 'streamMessage'])->name('chat.stream');
    Route::post('/chat/stream-vercel', [DashboardController::class, 'streamVercel'])->name('chat.stream.vercel');
    Route::get('/chat/new', [DashboardController::class, 'newChat'])->name('chat.new');
});

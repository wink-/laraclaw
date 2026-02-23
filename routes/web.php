<?php

use App\Http\Controllers\DiscordWebhookController;
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

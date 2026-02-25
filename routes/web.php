<?php

use App\Http\Controllers\DiscordWebhookController;
use App\Http\Controllers\Laraclaw\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Laraclaw Webhook Routes (no auth required - verified by signatures)
Route::middleware('throttle:laraclaw-webhooks')->prefix('laraclaw/webhooks')->group(function () {
    Route::post('telegram', TelegramWebhookController::class)->name('laraclaw.webhooks.telegram');
    Route::post('discord', DiscordWebhookController::class)->name('laraclaw.webhooks.discord');
    Route::get('whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('laraclaw.webhooks.whatsapp.verify');
    Route::post('whatsapp', [WhatsAppWebhookController::class, 'handle'])->name('laraclaw.webhooks.whatsapp.handle');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    // Breeze Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Redirect /dashboard to Laraclaw
    Route::get('/dashboard', fn () => redirect()->route('laraclaw.dashboard'))->name('dashboard');

    // Laraclaw streaming endpoint (called from Livewire)
    Route::post('laraclaw/chat/stream-vercel', [DashboardController::class, 'streamVercel'])
        ->middleware('throttle:laraclaw-api')
        ->name('laraclaw.chat.stream.vercel');

    // Laraclaw routes
    Route::prefix('laraclaw')->name('laraclaw.')->group(function () {
        Route::get('/', \App\Livewire\Laraclaw\Dashboard::class)->name('dashboard');
        Route::get('/chat', \App\Livewire\Laraclaw\Chat::class)->name('chat');
        Route::get('/conversations', \App\Livewire\Laraclaw\Conversations::class)->name('conversations');
        Route::get('/memories', \App\Livewire\Laraclaw\Memories::class)->name('memories');
    });
});

require __DIR__.'/auth.php';

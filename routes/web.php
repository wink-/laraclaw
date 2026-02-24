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
Route::prefix('laraclaw/webhooks')->group(function () {
    Route::post('telegram', TelegramWebhookController::class)->name('laraclaw.webhooks.telegram');
    Route::post('discord', DiscordWebhookController::class)->name('laraclaw.webhooks.discord');
    Route::get('whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('laraclaw.webhooks.whatsapp.verify');
    Route::post('whatsapp', [WhatsAppWebhookController::class, 'handle'])->name('laraclaw.webhooks.whatsapp.handle');
});

// Laraclaw Streaming endpoint (called from Livewire)
Route::post('laraclaw/chat/stream-vercel', [DashboardController::class, 'streamVercel'])->name('laraclaw.chat.stream.vercel');

// Authenticated Routes
Route::middleware('auth')->group(function () {
    // Breeze Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Redirect /dashboard to Laraclaw
    Route::get('/dashboard', fn () => redirect()->route('laraclaw.dashboard.live'))->name('dashboard');

    // Laraclaw Legacy Dashboard Routes
    Route::prefix('laraclaw')->name('laraclaw.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/conversations', [DashboardController::class, 'conversations'])->name('conversations');
        Route::get('/conversations/{conversation}', [DashboardController::class, 'showConversation'])->name('conversation');
        Route::get('/memories', [DashboardController::class, 'memories'])->name('memories');
        Route::get('/metrics', [DashboardController::class, 'metrics'])->name('metrics');
        Route::get('/chat', [DashboardController::class, 'chat'])->name('chat');
        Route::post('/chat', [DashboardController::class, 'sendMessage'])->name('chat.send');
        Route::post('/chat/stream', [DashboardController::class, 'streamMessage'])->name('chat.stream');
        Route::get('/chat/new', [DashboardController::class, 'newChat'])->name('chat.new');
    });

    // Laraclaw Livewire Dashboard Routes
    Route::prefix('laraclaw/live')->name('laraclaw.')->group(function () {
        Route::get('/', \App\Livewire\Laraclaw\Dashboard::class)->name('dashboard.live');
        Route::get('/chat', \App\Livewire\Laraclaw\Chat::class)->name('chat.live');
        Route::get('/conversations', \App\Livewire\Laraclaw\Conversations::class)->name('conversations.live');
        Route::get('/memories', \App\Livewire\Laraclaw\Memories::class)->name('memories.live');
    });
});

require __DIR__.'/auth.php';

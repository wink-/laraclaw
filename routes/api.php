<?php

use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\MemoryController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\SkillController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['laraclaw.api', 'throttle:laraclaw-api'])
    ->group(function () {
        Route::get('conversations', [ConversationController::class, 'index']);
        Route::post('conversations', [ConversationController::class, 'store']);
        Route::get('conversations/{conversation}', [ConversationController::class, 'show']);

        Route::get('conversations/{conversation}/messages', [MessageController::class, 'index']);
        Route::post('conversations/{conversation}/messages', [MessageController::class, 'store']);

        Route::get('memories', [MemoryController::class, 'index']);
        Route::post('memories', [MemoryController::class, 'store']);

        Route::get('skills', [SkillController::class, 'index']);
        Route::patch('skills', [SkillController::class, 'update']);
    });

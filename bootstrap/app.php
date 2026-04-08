<?php

use App\Http\Middleware\AuthBypass;
use App\Http\Middleware\LaraclawApiTokenAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'laraclaw/webhooks/telegram',
            'laraclaw/webhooks/discord',
            'laraclaw/webhooks/slack',
            'laraclaw/webhooks/whatsapp',
        ]);

        $middleware->alias([
            'auth' => AuthBypass::class,
            'laraclaw.api' => LaraclawApiTokenAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

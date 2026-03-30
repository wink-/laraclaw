<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthBypass
{
    /**
     * Handle an incoming request.
     *
     * When AUTH_DISABLED=true, auto-authenticates as the first user
     * so the app is accessible without login during development.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (env('AUTH_DISABLED', false)) {
            if (! Auth::check()) {
                $user = Auth::getProvider()->retrieveById(1)
                    ?? Auth::getProvider()->retrieveByCredentials(['email' => 'test@example.com']);

                if ($user) {
                    Auth::login($user);
                }
            }

            return $next($request);
        }

        return app(\Illuminate\Auth\Middleware\Authenticate::class)->handle($request, $next);
    }
}

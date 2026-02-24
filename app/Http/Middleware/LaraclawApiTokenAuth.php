<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LaraclawApiTokenAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (! $plainToken) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $token = ApiToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $token || ! $token->user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            return response()->json([
                'message' => 'API token expired.',
            ], 401);
        }

        $token->update(['last_used_at' => now()]);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}

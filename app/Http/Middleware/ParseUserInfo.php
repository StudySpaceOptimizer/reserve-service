<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ParseUserInfo
{
    public function handle(Request $request, Closure $next)
    {
        $userEmail = $request->header('X-User-Email');
        $userRole = $request->header('X-User-Role', 'user');

        if (!$userEmail) {
            return response()->json(['error' => 'X-User-Email header is missing'], 400);
        }

        Log::info('User email: ' . $userEmail);
        Log::info('User role: ' . $userRole);

        $request->merge([
            'user' => [
                'email' => $userEmail,
                'role' => $userRole,
            ],
        ]);

        return $next($request);
    }
}

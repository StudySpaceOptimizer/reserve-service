<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ParseUserInfo
{
    public function handle(Request $request, Closure $next)
    {
        $userInfoHeader = $request->header('X-User-Info');

        if ($userInfoHeader) {
            $userInfo = json_decode($userInfoHeader, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($userInfo['email'])) {
                $request->merge(['user' => ['email' => $userInfo['email']]]);
            } else {
                return response()->json(['error' => 'Invalid X-User-Info header format'], 400);
            }
        } else {
            return response()->json(['error' => 'X-User-Info header is missing'], 400);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckLmsAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return response()->json([
                'data'    => null,
                'code'    => 0,
                'message' => 'Unauthorized. Please login.',
            ], 401);
        }

        // Update last visited timestamp
        $request->user()->update(['last_visited_on' => now()]);

        return $next($request);
    }
}

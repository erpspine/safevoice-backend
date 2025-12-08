<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Check if user is a branch/company user (not admin)
        if ($request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. User access only.'
            ], 403);
        }

        // Check if user account is active and can login
        if (!$request->user()->canLogin()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active or verified'
            ], 403);
        }

        return $next($request);
    }
}

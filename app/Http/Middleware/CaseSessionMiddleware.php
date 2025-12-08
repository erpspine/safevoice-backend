<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use App\Models\CaseModel;

class CaseSessionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\JsonResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Get session token from request headers or parameters
        $sessionToken = $request->header('X-Session-Token')
            ?? $request->input('session_token')
            ?? $request->bearerToken();

        if (!$sessionToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Session token required',
                'code' => 'SESSION_TOKEN_MISSING'
            ], 401);
        }

        // Find case with valid session token (stored in database)
        $case = CaseModel::whereNotNull('session_token')
            ->where('session_expires_at', '>', now())
            ->get()
            ->filter(function ($case) use ($sessionToken) {
                return Hash::check($sessionToken, $case->session_token);
            })
            ->first();

        if (!$case) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired session token',
                'code' => 'SESSION_INVALID'
            ], 401);
        }

        // Verify the case ID in the route matches the session (if caseId is in route)
        $routeCaseId = $request->route('caseId');
        if ($routeCaseId && $routeCaseId !== $case->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Session token does not match the requested case',
                'code' => 'SESSION_CASE_MISMATCH'
            ], 403);
        }        // Add case and session data to request for use in controllers
        $sessionData = [
            'case_id' => $case->id,
            'authenticated_at' => now(),
            'expires_at' => $case->session_expires_at
        ];

        $request->merge([
            'authenticated_case' => $case,
            'session_data' => $sessionData,
            'session_token' => $sessionToken
        ]);

        return $next($request);
    }
}

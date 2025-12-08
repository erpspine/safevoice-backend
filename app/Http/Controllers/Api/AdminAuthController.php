<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\HasApiTokens;

class AdminAuthController extends Controller
{
    /**
     * Admin login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find user with admin privileges
        $user = User::where('email', $request->email)
            ->admins() // Use scope to only get admin users
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid admin credentials'
            ], 401);
        }

        // Check if account is locked
        if ($user->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is temporarily locked due to multiple failed login attempts'
            ], 423);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            $user->recordFailedLogin();

            return response()->json([
                'success' => false,
                'message' => 'Invalid admin credentials'
            ], 401);
        }

        // Check if user can login (active, verified, etc.)
        if (!$user->canLogin()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active or verified'
            ], 403);
        }

        // Generate token for admin
        $tokenName = 'admin-token-' . $user->id;
        $token = $user->createToken($tokenName, ['admin'])->plainTextToken;

        // Record successful login
        $user->recordLogin($request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Admin logged in successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'permissions' => $user->permissions,
                    'company_id' => $user->company_id,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration', null)
            ]
        ]);
    }

    /**
     * Admin logout
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Admin logged out successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Get authenticated admin user info
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'permissions' => $user->permissions,
                    'company_id' => $user->company_id,
                    'last_login_at' => $user->last_login_at,
                    'is_super_admin' => $user->isSuperAdmin(),
                    'company' => $user->company ? [
                        'id' => $user->company->id,
                        'name' => $user->company->name,
                    ] : null,
                ]
            ]
        ]);
    }

    /**
     * Refresh admin token
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            // Create new token
            $tokenName = 'admin-token-' . $user->id;
            $token = $user->createToken($tokenName, ['admin'])->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('sanctum.expiration', null)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed'
            ], 500);
        }
    }
}

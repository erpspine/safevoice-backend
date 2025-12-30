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

class CompanyAuthController extends Controller
{
    /**
     * Company login (for branch managers)
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

        // Find user with company privileges
        $user = User::where('email', $request->email)
            ->companies() // Use scope to only get company users (company admins)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid company credentials'
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
                'message' => 'Invalid company credentials'
            ], 401);
        }

        // Check if user can login (active, verified, etc.)
        if (!$user->canLogin()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active or verified'
            ], 403);
        }

        // Generate token for company user
        $tokenName = 'company-token-' . $user->id;
        $token = $user->createToken($tokenName, ['company'])->plainTextToken;

        // Record successful login
        $user->recordLogin($request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Company user logged in successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'permissions' => $user->permissions,
                    'company_id' => $user->company_id,
                    'branch_id' => $user->branch_id,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration', null)
            ]
        ]);
    }

    /**
     * Company logout
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Revoke ALL company tokens for this user (invalidate all sessions)
            $user->tokens()->where('name', 'like', 'company-token-%')->delete();

            return response()->json([
                'success' => true,
                'message' => 'Company user logged out successfully. All sessions have been invalidated.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Get authenticated company user info
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'company_admin') {
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
                    'branch_id' => $user->branch_id,
                    'last_login_at' => $user->last_login_at,
                    'company' => $user->company ? [
                        'id' => $user->company->id,
                        'name' => $user->company->name,
                    ] : null,
                    'branch' => $user->branch ? [
                        'id' => $user->branch->id,
                        'name' => $user->branch->name,
                    ] : null,
                ]
            ]
        ]);
    }

    /**
     * Refresh company token
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'company_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            // Create new token
            $tokenName = 'company-token-' . $user->id;
            $token = $user->createToken($tokenName, ['company'])->plainTextToken;

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

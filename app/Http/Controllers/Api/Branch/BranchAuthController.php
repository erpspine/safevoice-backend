<?php

namespace App\Http\Controllers\Api\Branch;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class BranchAuthController extends Controller
{
    /**
     * Branch admin login.
     */
    public function login(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find user by email
            $user = User::where('email', $request->email)->first();

            // Check if user exists
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check if user is a branch admin
            if ($user->role !== 'branch_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can login here.'
                ], 403);
            }

            // Check if user has a branch assigned
            if (!$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No branch assigned to this account. Please contact your company administrator.'
                ], 403);
            }

            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check if user is active
            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is inactive. Please contact your administrator.'
                ], 403);
            }

            // Load relationships
            $user->load([
                'company:id,name,email,contact',
                'branch:id,name,location,status'
            ]);

            // Check if branch is active
            if ($user->branch && !$user->branch->status) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your branch is currently inactive. Please contact your administrator.'
                ], 403);
            }

            // Revoke all existing tokens for this user
            $user->tokens()->delete();

            // Create new token
            $token = $user->createToken('branch-admin-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'status' => $user->status,
                        'company' => $user->company,
                        'branch' => $user->branch
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Logout branch admin.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Revoke ALL branch tokens for this user (invalidate all sessions)
            $user->tokens()->where('name', 'like', 'branch-token-%')->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout successful. All sessions have been invalidated.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get authenticated branch admin details.
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Ensure user is branch admin
            if ($user->role !== 'branch_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins allowed.'
                ], 403);
            }

            // Load relationships
            $user->load([
                'company:id,name,email,contact,logo,status',
                'branch:id,name,location,status'
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'company' => $user->company,
                    'branch' => $user->branch,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Refresh authentication token.
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Ensure user is branch admin
            if ($user->role !== 'branch_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins allowed.'
                ], 403);
            }

            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            // Create new token
            $token = $user->createToken('branch-admin-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}

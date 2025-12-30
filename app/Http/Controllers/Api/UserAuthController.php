<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserAuthController extends Controller
{
    /**
     * Company branch user login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'company_id' => 'sometimes|string|exists:companies,id', // Optional company verification
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find user with branch/company access
        $query = User::where('email', $request->email)
            ->branchUsers(); // Use scope to only get branch users

        // If company_id provided, verify user belongs to that company
        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        $user = $query->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid user credentials'
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
                'message' => 'Invalid user credentials'
            ], 401);
        }

        // Check if user can login (active, verified, etc.)
        if (!$user->canLogin()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active or verified'
            ], 403);
        }

        // Generate token for branch user
        $tokenName = 'user-token-' . $user->id;
        $abilities = $this->getUserAbilities($user);
        $token = $user->createToken($tokenName, $abilities)->plainTextToken;

        // Record successful login
        $user->recordLogin($request->ip());

        return response()->json([
            'success' => true,
            'message' => 'User logged in successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'employee_id' => $user->employee_id,
                    'phone' => $user->phone,
                    'company_id' => $user->company_id,
                    'branch_id' => $user->branch_id,
                    'department_id' => $user->department_id,
                    'permissions' => $user->permissions,
                    'company' => $user->company ? [
                        'id' => $user->company->id,
                        'name' => $user->company->name,
                    ] : null,
                    'branch' => $user->branch ? [
                        'id' => $user->branch->id,
                        'name' => $user->branch->name,
                        'location' => $user->branch->location,
                    ] : null,
                    'department' => $user->department ? [
                        'id' => $user->department->id,
                        'name' => $user->department->name,
                    ] : null,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration', null)
            ]
        ]);
    }

    /**
     * Company branch user logout
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Revoke ALL user tokens for this user (invalidate all sessions)
            $user->tokens()->where('name', 'like', 'user-token-%')->delete();

            return response()->json([
                'success' => true,
                'message' => 'User logged out successfully. All sessions have been invalidated.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Get authenticated user info
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'employee_id' => $user->employee_id,
                    'phone' => $user->phone,
                    'company_id' => $user->company_id,
                    'branch_id' => $user->branch_id,
                    'department_id' => $user->department_id,
                    'permissions' => $user->permissions,
                    'last_login_at' => $user->last_login_at,
                    'is_branch_manager' => $user->isBranchManager(),
                    'force_password_change' => $user->force_password_change,
                    'company' => $user->company ? [
                        'id' => $user->company->id,
                        'name' => $user->company->name,
                        'plan' => $user->company->plan,
                    ] : null,
                    'branch' => $user->branch ? [
                        'id' => $user->branch->id,
                        'name' => $user->branch->name,
                        'location' => $user->branch->location,
                        'phone' => $user->branch->phone,
                    ] : null,
                    'department' => $user->department ? [
                        'id' => $user->department->id,
                        'name' => $user->department->name,
                        'description' => $user->department->description,
                    ] : null,
                ]
            ]
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->new_password),
            'force_password_change' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $user->update($request->only(['name', 'phone']));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ]
            ]
        ]);
    }

    /**
     * Refresh user token
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            // Create new token
            $tokenName = 'user-token-' . $user->id;
            $abilities = $this->getUserAbilities($user);
            $token = $user->createToken($tokenName, $abilities)->plainTextToken;

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

    /**
     * Get user abilities based on role
     */
    private function getUserAbilities(User $user): array
    {
        $abilities = ['user'];

        switch ($user->role) {
            case User::ROLE_BRANCH_MANAGER:
                $abilities = array_merge($abilities, ['branch-manager', 'manage-branch-users', 'view-all-cases']);
                break;
            case User::ROLE_DEPARTMENT_HEAD:
                $abilities = array_merge($abilities, ['department-head', 'manage-department-users', 'view-department-cases']);
                break;
            case User::ROLE_INVESTIGATOR:
                $abilities = array_merge($abilities, ['investigator', 'manage-assigned-cases', 'create-reports']);
                break;
            case User::ROLE_USER:
                $abilities = array_merge($abilities, ['create-cases', 'view-own-cases']);
                break;
            case User::ROLE_VIEWER:
                $abilities = array_merge($abilities, ['view-only']);
                break;
        }

        return $abilities;
    }
}

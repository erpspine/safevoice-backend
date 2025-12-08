<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;

class InvitationController extends Controller
{
    /**
     * Verify invitation token
     */
    public function verifyToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $user = User::where('invitation_token', $request->token)
            ->where('invitation_expires_at', '>', Carbon::now())
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired invitation token.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token is valid.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'company' => $user->company?->name,
                    'branch' => $user->branch?->name,
                    'department' => $user->department?->name,
                ]
            ]
        ]);
    }

    /**
     * Accept invitation and set password
     */
    public function acceptInvitation(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'password' => ['required', 'string', Password::min(8)->letters()->mixedCase()->numbers()],
            'password_confirmation' => 'required|string|same:password',
        ]);

        // Start database transaction
        DB::beginTransaction();

        try {
            $user = User::where('invitation_token', $request->token)
                ->where('invitation_expires_at', '>', Carbon::now())
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired invitation token.',
                ], 400);
            }

            // Update user password and clear invitation token
            $user->update([
                'password' => Hash::make($request->password),
                'invitation_token' => null,
                'invitation_expires_at' => null,
                'is_verified' => true,
                'status' => 'active',
            ]);

            // Create authentication token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Commit the transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Password set successfully. You are now logged in.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'status' => $user->status,
                        'company' => $user->company?->name,
                        'branch' => $user->branch?->name,
                        'department' => $user->department?->name,
                        'permissions' => $user->permissions,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]
            ]);
        } catch (\Exception $e) {
            // Rollback the transaction
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to accept invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invitation details by token
     */
    public function getInvitationDetails(string $token): JsonResponse
    {
        $user = User::where('invitation_token', $token)
            ->where('invitation_expires_at', '>', Carbon::now())
            ->with(['company', 'branch', 'department'])
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired invitation token.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invitation details retrieved successfully.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'company' => $user->company ? [
                        'id' => $user->company->id,
                        'name' => $user->company->name,
                    ] : null,
                    'branch' => $user->branch ? [
                        'id' => $user->branch->id,
                        'name' => $user->branch->name,
                    ] : null,
                    'department' => $user->department ? [
                        'id' => $user->department->id,
                        'name' => $user->department->name,
                    ] : null,
                ],
                'expires_at' => $user->invitation_expires_at->toDateTimeString(),
            ]
        ]);
    }
}

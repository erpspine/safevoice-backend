<?php

namespace App\Http\Controllers\Api\Investigator;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CaseAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Log;

class InvestigatorProfileController extends Controller
{
    /**
     * View investigator profile details.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has investigator role
            if ($user->role !== 'investigator') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only investigators can view profile.'
                ], 403);
            }

            // Get case statistics
            $caseStats = [
                'total_assigned' => CaseAssignment::where('investigator_id', $user->id)->count(),
                'active_cases' => CaseAssignment::where('investigator_id', $user->id)
                    ->whereHas('case', function ($q) {
                        $q->whereIn('status', ['open', 'in_progress', 'under_investigation']);
                    })->count(),
                'closed_cases' => CaseAssignment::where('investigator_id', $user->id)
                    ->whereHas('case', function ($q) {
                        $q->where('status', 'closed');
                    })->count(),
                'pending_review' => CaseAssignment::where('investigator_id', $user->id)
                    ->whereHas('case', function ($q) {
                        $q->where('status', 'pending_review');
                    })->count(),
            ];

            // Get assigned companies
            $assignedCompanies = CaseAssignment::where('investigator_id', $user->id)
                ->where('status', 'active')
                ->with(['case.company:id,name,logo', 'case.branch:id,name'])
                ->get()
                ->pluck('case.company')
                ->unique('id')
                ->filter()
                ->values();

            // Add profile picture URL if exists
            if ($user->profile_picture) {
                $user->profile_picture_url = Storage::url($user->profile_picture);
            } else {
                $user->profile_picture_url = null;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'role' => $user->role,
                    'status' => $user->status,
                    'profile_picture' => $user->profile_picture,
                    'profile_picture_url' => $user->profile_picture_url,
                    'last_login' => $user->last_login,
                    'assigned_companies' => $assignedCompanies,
                    'case_statistics' => $caseStats,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update investigator profile information.
     */
    public function update(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = $request->user();

            // Check if user is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has investigator role
            if ($user->role !== 'investigator') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only investigators can update profile.'
                ], 403);
            }

            // Validate input
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
                'phone_number' => 'sometimes|nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'error' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Update user profile
            $user->update($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'role' => $user->role,
                    'updated_at' => $user->updated_at
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Upload investigator profile picture.
     */
    public function uploadProfilePicture(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = $request->user();

            // Check if user is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has investigator role
            if ($user->role !== 'investigator') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only investigators can upload profile pictures.'
                ], 403);
            }

            // Validate input
            $validator = Validator::make($request->all(), [
                'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'error' => $validator->errors()
                ], 422);
            }

            // Delete old profile picture if exists
            if ($user->profile_picture && Storage::exists($user->profile_picture)) {
                Storage::delete($user->profile_picture);
            }

            // Store new profile picture
            $file = $request->file('profile_picture');
            $timestamp = time();
            $extension = $file->getClientOriginalExtension();
            $filename = "user_{$user->id}_{$timestamp}.{$extension}";
            $path = $file->storeAs('public/user-profiles', $filename);

            // Update user profile picture path
            $user->update(['profile_picture' => $path]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profile picture uploaded successfully',
                'data' => [
                    'profile_picture' => $path,
                    'profile_picture_url' => Storage::url($path)
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete investigator profile picture.
     */
    public function deleteProfilePicture(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = $request->user();

            // Check if user is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has investigator role
            if ($user->role !== 'investigator') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only investigators can delete profile pictures.'
                ], 403);
            }

            // Check if profile picture exists
            if (!$user->profile_picture) {
                return response()->json([
                    'success' => false,
                    'message' => 'No profile picture to delete'
                ], 404);
            }

            // Delete profile picture from storage
            if (Storage::exists($user->profile_picture)) {
                Storage::delete($user->profile_picture);
            }

            // Update user profile picture to null
            $user->update(['profile_picture' => null]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profile picture deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete profile picture',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Change investigator password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = $request->user();

            // Check if user is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has investigator role
            if ($user->role !== 'investigator') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only investigators can change password.'
                ], 403);
            }

            // Validate input
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => [
                    'required',
                    'string',
                    'confirmed',
                    Password::min(8)
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                ],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'error' => $validator->errors()
                ], 422);
            }

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                    'error' => [
                        'current_password' => ['The current password is incorrect.']
                    ]
                ], 422);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}

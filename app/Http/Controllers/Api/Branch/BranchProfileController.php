<?php

namespace App\Http\Controllers\Api\Branch;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BranchProfileController extends Controller
{
    /**
     * View branch admin profile details.
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

            // Ensure user has branch admin role
            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can view profile.'
                ], 403);
            }

            // Load relationships
            $user->load([
                'company:id,name,email,contact,logo,status',
                'branch:id,name,location,status'
            ]);

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
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->status,
                    'profile_picture' => $user->profile_picture,
                    'profile_picture_url' => $user->profile_picture_url,
                    'company' => $user->company,
                    'branch' => $user->branch,
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
     * Update branch admin profile information.
     */
    public function update(Request $request): JsonResponse
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

            // Ensure user has branch admin role
            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can update profile.'
                ], 403);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
                'phone' => 'sometimes|nullable|string|max:20'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Update user information
            $updateData = [];

            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }

            if ($request->has('email')) {
                $updateData['email'] = $request->email;
            }

            if ($request->has('phone')) {
                $updateData['phone'] = $request->phone;
            }

            $user->update($updateData);

            DB::commit();

            // Refresh user data
            $user->refresh();

            // Load relationships
            $user->load([
                'company:id,name,email,contact,logo,status',
                'branch:id,name,location,status'
            ]);

            // Add profile picture URL if exists
            if ($user->profile_picture) {
                $user->profile_picture_url = Storage::url($user->profile_picture);
            } else {
                $user->profile_picture_url = null;
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->status,
                    'profile_picture' => $user->profile_picture,
                    'profile_picture_url' => $user->profile_picture_url,
                    'company' => $user->company,
                    'branch' => $user->branch
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
     * Change branch admin password.
     */
    public function changePassword(Request $request): JsonResponse
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

            // Ensure user has branch admin role
            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can change password.'
                ], 403);
            }

            // Validate request
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
                'new_password_confirmation' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 422);
            }

            // Check if new password is same as current
            if (Hash::check($request->new_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password must be different from current password'
                ], 422);
            }

            DB::beginTransaction();

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

    /**
     * Upload branch admin profile picture.
     */
    public function uploadProfilePicture(Request $request): JsonResponse
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

            // Ensure user has branch admin role
            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can upload profile picture.'
                ], 403);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'profile_picture' => 'required|image|mimes:jpg,jpeg,png|max:5120' // 5MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Delete old profile picture if exists
            if ($user->profile_picture && Storage::exists($user->profile_picture)) {
                Storage::delete($user->profile_picture);
            }

            // Store new profile picture
            $file = $request->file('profile_picture');
            $filename = 'user_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('public/user-profiles', $filename);

            // Update user record
            $user->update([
                'profile_picture' => $path
            ]);

            DB::commit();

            // Add full URL for profile picture
            $user->profile_picture_url = Storage::url($path);

            return response()->json([
                'success' => true,
                'message' => 'Profile picture uploaded successfully',
                'data' => [
                    'profile_picture' => $user->profile_picture,
                    'profile_picture_url' => $user->profile_picture_url
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
     * Delete branch admin profile picture.
     */
    public function deleteProfilePicture(Request $request): JsonResponse
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

            // Ensure user has branch admin role
            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can delete profile picture.'
                ], 403);
            }

            if (!$user->profile_picture) {
                return response()->json([
                    'success' => false,
                    'message' => 'No profile picture to delete'
                ], 404);
            }

            DB::beginTransaction();

            // Delete profile picture from storage
            if (Storage::exists($user->profile_picture)) {
                Storage::delete($user->profile_picture);
            }

            // Update user record
            $user->update([
                'profile_picture' => null
            ]);

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
}

<?php

namespace App\Http\Controllers\Api\Investigator;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class InvestigatorProfileController extends Controller
{
    /**
     * Get investigator profile
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user()->load([
                'investigatorAssignments.company:id,name,logo',
                'investigatorAssignments.company.branches:id,company_id,name'
            ]);

            // Get case statistics
            $caseStats = [
                'total_assigned' => $user->investigatorCaseAssignments()->count(),
                'active_cases' => $user->investigatorCaseAssignments()
                    ->whereHas('case', function ($q) {
                        $q->whereIn('status', ['open', 'in_progress', 'under_investigation']);
                    })->count(),
                'closed_cases' => $user->investigatorCaseAssignments()
                    ->whereHas('case', function ($q) {
                        $q->where('status', 'closed');
                    })->count(),
                'pending_review' => $user->investigatorCaseAssignments()
                    ->whereHas('case', function ($q) {
                        $q->where('status', 'pending_review');
                    })->count(),
            ];

            // Get assigned companies with detailed info
            $assignedCompanies = $user->investigatorAssignments->map(function ($assignment) {
                return [
                    'assignment_id' => $assignment->id,
                    'company' => [
                        'id' => $assignment->company->id,
                        'name' => $assignment->company->name,
                        'logo' => $assignment->company->logo,
                    ],
                    'branches' => $assignment->company->branches->map(function ($branch) {
                        return [
                            'id' => $branch->id,
                            'name' => $branch->name,
                        ];
                    }),
                    'assigned_at' => $assignment->created_at,
                ];
            });

            // Recent activity - get recent cases
            $recentCases = $user->investigatorCaseAssignments()
                ->with(['case:id,case_token,title,status,priority,created_at', 'case.company:id,name'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($assignment) {
                    return [
                        'case_id' => $assignment->case->id,
                        'case_token' => $assignment->case->case_token,
                        'title' => $assignment->case->title,
                        'status' => $assignment->case->status,
                        'priority' => $assignment->case->priority,
                        'company' => $assignment->case->company->name,
                        'assigned_at' => $assignment->created_at,
                        'case_created_at' => $assignment->case->created_at,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'profile' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone_number' => $user->phone_number,
                        'profile_picture' => $user->profile_picture,
                        'status' => $user->status,
                        'is_verified' => $user->is_verified,
                        'last_login' => $user->last_login,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ],
                    'assigned_companies' => $assignedCompanies,
                    'case_statistics' => $caseStats,
                    'recent_cases' => $recentCases,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Get investigator profile error', [
                'user_id' => $request->user()->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve profile information',
            ], 500);
        }
    }

    /**
     * Update investigator profile
     */
    public function update(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
                'phone_number' => 'nullable|string|max:20',
            ]);

            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
            ]);

            Log::info('Investigator profile updated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'changes' => $request->only(['name', 'email', 'phone_number'])
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'data' => [
                    'profile' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone_number' => $user->phone_number,
                        'profile_picture' => $user->profile_picture,
                        'updated_at' => $user->updated_at,
                    ]
                ]
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Investigator profile update error', [
                'user_id' => $request->user()->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update profile'
            ], 500);
        }
    }

    /**
     * Upload profile picture
     */
    public function uploadProfilePicture(Request $request)
    {
        try {
            $request->validate([
                'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $user = $request->user();

            // Delete old profile picture if exists
            if ($user->profile_picture) {
                $oldPicturePath = str_replace('/storage/', '', $user->profile_picture);
                if (Storage::disk('public')->exists($oldPicturePath)) {
                    Storage::disk('public')->delete($oldPicturePath);
                }
            }

            // Store new profile picture
            $file = $request->file('profile_picture');
            $filename = 'investigator_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('profile-pictures', $filename, 'public');

            $profilePictureUrl = '/storage/' . $path;

            $user->update(['profile_picture' => $profilePictureUrl]);

            Log::info('Investigator profile picture uploaded', [
                'user_id' => $user->id,
                'file_path' => $profilePictureUrl,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Profile picture uploaded successfully',
                'data' => [
                    'profile_picture' => $profilePictureUrl
                ]
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Investigator profile picture upload error', [
                'user_id' => $request->user()->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload profile picture'
            ], 500);
        }
    }

    /**
     * Delete profile picture
     */
    public function deleteProfilePicture(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->profile_picture) {
                $picturePath = str_replace('/storage/', '', $user->profile_picture);
                if (Storage::disk('public')->exists($picturePath)) {
                    Storage::disk('public')->delete($picturePath);
                }

                $user->update(['profile_picture' => null]);

                Log::info('Investigator profile picture deleted', [
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Profile picture deleted successfully'
                ], 200);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'No profile picture to delete'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Investigator profile picture deletion error', [
                'user_id' => $request->user()->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete profile picture'
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            $user = $request->user();

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Current password is incorrect',
                    'errors' => [
                        'current_password' => ['The current password is incorrect.']
                    ]
                ], 422);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            Log::info('Investigator password changed', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password changed successfully'
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Investigator password change error', [
                'user_id' => $request->user()->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to change password'
            ], 500);
        }
    }
}

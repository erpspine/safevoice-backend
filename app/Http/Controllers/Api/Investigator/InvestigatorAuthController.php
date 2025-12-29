<?php

namespace App\Http\Controllers\Api\Investigator;

use App\Http\Controllers\Controller;
use App\Models\CaseAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InvestigatorAuthController extends Controller
{
    /**
     * Login investigator
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)
                ->where('role', 'investigator')
                ->where('status', 'active')
                ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                Log::warning('Failed investigator login attempt', [
                    'email' => $request->email,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            // Check if user account is verified
            if (!$user->is_verified) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Account not verified. Please check your email for verification instructions.',
                ], 403);
            }

            // Check if user is suspended or inactive
            if ($user->status !== 'active') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Account is ' . $user->status . '. Please contact administrator.',
                ], 403);
            }

            // Create token
            $token = $user->createToken('investigator-auth-token', ['investigator'])->plainTextToken;

            // Update last login
            $user->update(['last_login' => now()]);

            // Get assigned cases with company information
            $assignments = CaseAssignment::where('investigator_id', $user->id)
                ->where('status', 'active')
                ->with(['case.company:id,name,logo', 'case.branch:id,name'])
                ->get();

            // Get unique companies from assignments
            $assignedCompanies = $assignments->pluck('case.company')
                ->unique('id')
                ->filter()
                ->map(function ($company) use ($assignments) {
                    $companyAssignments = $assignments->filter(function ($assignment) use ($company) {
                        return $assignment->case && $assignment->case->company_id === $company->id;
                    });

                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'logo' => $company->logo,
                        'active_cases_count' => $companyAssignments->count(),
                    ];
                })->values();

            Log::info('Successful investigator login', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'phone_number' => $user->phone_number,
                        'profile_picture' => $user->profile_picture,
                        'last_login' => $user->last_login,
                        'assigned_companies' => $assignedCompanies,
                        'assignments_count' => $assignedCompanies->count(),
                    ],
                    'token' => $token,
                    'expires_at' => now()->addDays(30)->toISOString(),
                ]
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Investigator login error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred during login',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get current investigator
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user()->load([
                'investigatorAssignments.case.company:id,name,logo',
                'investigatorAssignments.case.branch:id,company_id,name'
            ]);

            // Get case statistics
            $caseStats = [
                'assigned_cases' => $user->investigatorAssignments()->count(),
                'active_cases' => $user->investigatorAssignments()
                    ->whereHas('case', function ($q) {
                        $q->whereIn('status', ['open', 'in_progress', 'under_investigation']);
                    })->count(),
                'closed_cases' => $user->investigatorAssignments()
                    ->whereHas('case', function ($q) {
                        $q->where('status', 'closed');
                    })->count(),
            ];

            // Get assigned companies from case assignments
            $assignedCompanies = $user->investigatorAssignments
                ->pluck('case.company')
                ->unique('id')
                ->filter()
                ->map(function ($company) use ($user) {
                    $branches = $user->investigatorAssignments
                        ->where('case.company_id', $company->id)
                        ->pluck('case.branch')
                        ->unique('id')
                        ->filter()
                        ->values();

                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'logo' => $company->logo,
                        'branches' => $branches->map(function ($branch) {
                            return [
                                'id' => $branch->id,
                                'name' => $branch->name,
                            ];
                        })->values(),
                    ];
                })->values();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'phone_number' => $user->phone_number,
                        'profile_picture' => $user->profile_picture,
                        'status' => $user->status,
                        'is_verified' => $user->is_verified,
                        'last_login' => $user->last_login,
                        'created_at' => $user->created_at,
                    ],
                    'assigned_companies' => $assignedCompanies,
                    'case_statistics' => $caseStats,
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
     * Logout investigator
     */
    public function logout(Request $request)
    {
        try {
            // Log the logout attempt
            Log::info('Investigator logout', [
                'user_id' => $request->user()->id,
                'email' => $request->user()->email,
            ]);

            // Revoke the current token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Logout successful'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Investigator logout error', [
                'user_id' => $request->user()->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request)
    {
        try {
            $user = $request->user();

            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            // Create new token
            $token = $user->createToken('investigator-auth-token', ['investigator'])->plainTextToken;

            Log::info('Investigator token refreshed', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $token,
                    'expires_at' => now()->addDays(30)->toISOString(),
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Investigator token refresh error', [
                'user_id' => $request->user()->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Token refresh failed'
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

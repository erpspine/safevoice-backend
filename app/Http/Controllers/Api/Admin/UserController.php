<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Investigator;
use App\Mail\UserInvitation;
use App\Mail\AccountDeactivated;
use App\Mail\AccountActivated;
use App\Services\SmsService;
use App\Jobs\SendInvitationSms;
use App\Jobs\SendInvitationEmail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users with filters (optimized for performance).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Start with optimized query - select only needed fields
            $query = User::select([
                'id',
                'name',
                'email',
                'phone_number',
                'sms_invitation',
                'role',
                'status',
                'is_verified',
                'company_id',
                'branch_id',
                'employee_id',
                'created_at',
                'updated_at'
            ]);

            // Apply filters with indexes
            if ($request->has('company_id') && $request->company_id !== '') {
                $query->where('company_id', $request->company_id);
            }

            if ($request->has('branch_id') && $request->branch_id !== '') {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('role') && $request->role !== '') {
                $query->where('role', $request->role);
            }

            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            // Optimized search with proper indexing
            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%")
                        ->orWhere('employee_id', 'ILIKE', "%{$search}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            if (in_array($sortBy, ['name', 'email', 'role', 'status', 'created_at'])) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Get all users without pagination
            $users = $query->get();

            // Load relationships efficiently in batch
            $users->load([
                'company:id,name',
                'branch:id,name,location'
            ]);

            return response()->json([
                'success' => true,
                'data' => $users,
                'total' => $users->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new user with invitation.
     */
    public function store(Request $request): JsonResponse
    {

        // Determine validation rules based on user role
        $isAdminUser = in_array($request->role, ['super_admin', 'admin']);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone_number' => 'nullable|string|max:20',
            'sms_invitation' => 'nullable|boolean',
            'role' => 'required|in:super_admin,admin,company_admin,branch_admin,investigator',
            'status' => 'in:active,inactive,pending',
        ];

        // Add company-specific validation for non-admin users
        if (!$isAdminUser) {
            $rules['company_id'] = 'required|exists:companies,id';
            $rules['branch_id'] = 'nullable|exists:branches,id';
        }

        // Add recipient type validation for branch users only
        $isBranchUser = $request->branch_id && !$isAdminUser;
        if ($isBranchUser) {
            $rules['recipient_type'] = 'nullable|in:primary,alternative';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Custom validation: Prevent non-branch users from having recipient type
        if (!$isBranchUser && $request->filled('recipient_type')) {
            return response()->json([
                'success' => false,
                'message' => 'Recipient type is only allowed for branch users',
                'errors' => ['recipient_type' => ['Recipient type can only be set for users assigned to a branch']]
            ], 422);
        }

        // Start database transaction
        DB::beginTransaction();

        try {
            // Additional validations for company users
            if (!$isAdminUser) {
                // Verify company exists and is active
                $company = Company::where('id', $request->company_id)
                    ->where('status', true)
                    ->firstOrFail();

                // Verify branch belongs to company (if provided)
                if ($request->branch_id) {
                    Branch::where('id', $request->branch_id)
                        ->where('company_id', $request->company_id)
                        ->firstOrFail();
                }
            }

            // Generate invitation token (no password yet)
            $invitationToken = Str::random(64);
            $invitationUrl = config('app.frontend_url', 'http://localhost:3000') . '/accept-invitation?token=' . $invitationToken;

            // Create user data
            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'sms_invitation' => $request->sms_invitation ?? false,
                'role' => $request->role,
                'status' => $request->status ?? 'pending',
                'password' => null, // No password yet - user will create it
                'invitation_token' => $invitationToken,
                'invitation_expires_at' => now()->addDays(7),
                'is_verified' => false,
            ];

            // Add company-related fields for non-admin users
            if (!$isAdminUser) {
                $userData['company_id'] = $request->company_id;
                $userData['branch_id'] = $request->branch_id;

                // Add recipient type for branch users
                if ($request->branch_id && $request->has('recipient_type')) {
                    $userData['recipient_type'] = $request->recipient_type;
                }
            }

            $user = User::create($userData);

            // If user is an investigator, create the investigator record
            if ($user->role === 'investigator') {
                Investigator::create([
                    'user_id' => $user->id,
                    'status' => true,
                    'is_external' => false,
                ]);
            }

            // Commit the transaction first
            DB::commit();

            // Queue invitation email
            SendInvitationEmail::dispatch($user->id, $invitationUrl, $isAdminUser);
            $emailQueued = true;

            // Queue SMS invitation if requested and phone number provided
            $smsQueued = false;
            if ($user->sms_invitation && $user->phone_number) {
                SendInvitationSms::dispatch($user->id, $invitationUrl, $isAdminUser);
                $smsQueued = true;
            }

            return response()->json([
                'success' => true,
                'message' => 'User created successfully. Invitation sent with token link.',
                'data' => [
                    'user' => $user->load(['company:id,name', 'branch:id,name']),
                    'invitation_token' => $invitationToken,
                    'invitation_url' => $invitationUrl,
                    'invitation_expires_at' => $user->invitation_expires_at,
                    'email_invitation_queued' => $emailQueued,
                    'sms_invitation_queued' => $smsQueued,
                ]
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Rollback the transaction
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Company, branch, or department not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback the transaction
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user with relationships.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = User::with([
                'company:id,name,email,plan',
                'branch:id,name,location'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $user,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // Start database transaction
        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);
            $isAdminUser = in_array($user->role, ['super_admin', 'admin']);

            $rules = [
                'name' => 'sometimes|required|string|max:255',
                'email' => [
                    'sometimes',
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id)
                ],
                'phone_number' => 'sometimes|nullable|string|max:20',
                'sms_invitation' => 'sometimes|nullable|boolean',
                'role' => 'sometimes|required|in:super_admin,admin,company_admin,branch_admin,investigator',
                'status' => 'sometimes|in:active,inactive,pending',
            ];

            // Add company-specific validation for non-admin users
            if (!$isAdminUser && $request->has(['company_id', 'branch_id'])) {
                $rules['company_id'] = 'sometimes|required|exists:companies,id';
                $rules['branch_id'] = 'nullable|exists:branches,id';
            }

            // Add recipient type validation for branch users only
            $isBranchUser = ($user->branch_id || $request->branch_id) && !$isAdminUser;
            if ($isBranchUser) {
                $rules['recipient_type'] = 'sometimes|nullable|in:primary,alternative';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Custom validation: Prevent non-branch users from having recipient type
            if (!$isBranchUser && $request->filled('recipient_type')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recipient type is only allowed for branch users',
                    'errors' => ['recipient_type' => ['Recipient type can only be set for users assigned to a branch']]
                ], 422);
            }

            // Additional validations for company users if company fields are being updated
            if (!$isAdminUser && $request->has('company_id')) {
                Company::where('id', $request->company_id)
                    ->where('status', true)
                    ->firstOrFail();

                if ($request->branch_id) {
                    Branch::where('id', $request->branch_id)
                        ->where('company_id', $request->company_id)
                        ->firstOrFail();
                }
            }

            // Check if status is being changed to inactive - if so, invalidate all sessions
            $statusChangingToInactive = $request->has('status') &&
                $request->status === 'inactive' &&
                $user->status !== 'inactive';

            $user->update($request->all());

            // Invalidate all tokens if user is being deactivated
            if ($statusChangingToInactive) {
                $user->tokens()->delete();

                // Send account deactivation email notification
                try {
                    $reason = $request->get('deactivation_reason', null);
                    Mail::to($user->email)->send(new AccountDeactivated($user, $reason));
                } catch (\Exception $mailException) {
                    // Log the email error but don't fail the update
                    Log::warning('Failed to send account deactivation email', [
                        'user_id' => $user->id,
                        'error' => $mailException->getMessage()
                    ]);
                }
            }

            // Commit the transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully' . ($statusChangingToInactive ? '. All sessions have been invalidated.' : ''),
                'data' => [
                    'user' => $user->fresh()->load(['company:id,name', 'branch:id,name'])
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Rollback the transaction
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'User not found or invalid company/branch/department'
            ], 404);
        } catch (\Exception $e) {
            // Rollback the transaction
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified user (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            // Prevent deletion of the last super admin
            if ($user->role === 'super_admin') {
                $superAdminCount = User::where('role', 'super_admin')->where('id', '!=', $id)->count();
                if ($superAdminCount === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete the last super admin user'
                    ], 422);
                }
            }

            // Invalidate all tokens for this user before deletion
            $user->tokens()->delete();

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully. All sessions have been invalidated.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate a user account.
     */
    public function deactivate(Request $request, string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);

            // Prevent deactivation of the last super admin
            if ($user->role === 'super_admin') {
                $activeSuperAdminCount = User::where('role', 'super_admin')
                    ->where('id', '!=', $id)
                    ->where('status', 'active')
                    ->count();

                if ($activeSuperAdminCount === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot deactivate the last active super admin user'
                    ], 422);
                }
            }

            // Check if user is already inactive
            if ($user->status === 'inactive') {
                return response()->json([
                    'success' => false,
                    'message' => 'User account is already deactivated'
                ], 422);
            }

            // Update status to inactive
            $user->update(['status' => 'inactive']);

            // Invalidate all tokens
            $user->tokens()->delete();

            // Send deactivation email
            try {
                $reason = $request->get('reason', null);
                Mail::to($user->email)->send(new AccountDeactivated($user, $reason));
            } catch (\Exception $mailException) {
                Log::warning('Failed to send account deactivation email', [
                    'user_id' => $user->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User account deactivated successfully. All sessions have been invalidated.',
                'data' => [
                    'user' => $user->fresh()->load(['company:id,name', 'branch:id,name'])
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate a user account.
     */
    public function activate(string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);

            // Check if user is already active
            if ($user->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'User account is already active'
                ], 422);
            }

            // Update status to active
            $user->update(['status' => 'active']);

            // Send activation email notification
            try {
                Mail::to($user->email)->send(new AccountActivated($user));
            } catch (\Exception $mailException) {
                Log::warning('Failed to send account activation email', [
                    'user_id' => $user->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User account activated successfully',
                'data' => [
                    'user' => $user->fresh()->load(['company:id,name', 'branch:id,name'])
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resend invitation to a user.
     */
    public function resendInvitation(string $id): JsonResponse
    {
        // Start database transaction
        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);

            if ($user->is_verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'User has already accepted the invitation'
                ], 422);
            }

            // Generate new invitation token and extend expiry (no password yet)
            $invitationToken = Str::random(64);
            $invitationUrl = config('app.frontend_url', 'http://localhost:3000') . '/accept-invitation?token=' . $invitationToken;

            $user->update([
                'invitation_token' => $invitationToken,
                'invitation_expires_at' => now()->addDays(7),
            ]);

            // Determine if admin user
            $isAdminUser = in_array($user->role, ['super_admin', 'admin']);

            // Commit the transaction first
            DB::commit();

            // Queue invitation email
            SendInvitationEmail::dispatch($user->id, $invitationUrl, $isAdminUser);
            $emailQueued = true;

            // Queue SMS invitation if requested and phone number provided
            $smsQueued = false;
            if ($user->sms_invitation && $user->phone_number) {
                SendInvitationSms::dispatch($user->id, $invitationUrl, $isAdminUser);
                $smsQueued = true;
            }

            return response()->json([
                'success' => true,
                'message' => 'Invitation resent successfully. User can now create their password using the invitation link.',
                'data' => [
                    'invitation_token' => $invitationToken,
                    'invitation_url' => $invitationUrl,
                    'invitation_expires_at' => $user->invitation_expires_at,
                    'email_invitation_queued' => $emailQueued,
                    'sms_invitation_queued' => $smsQueued,
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Rollback the transaction
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback the transaction
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics dashboard.
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('status', 'active')->count(),
                'pending_users' => User::where('status', 'pending')->count(),
                'users_by_role' => User::selectRaw('role, count(*) as count')
                    ->groupBy('role')
                    ->pluck('count', 'role'),
                'users_by_company' => User::with('company:id,name')
                    ->whereNotNull('company_id')
                    ->get()
                    ->groupBy('company.name')
                    ->map->count(),
                'verified_users' => User::where('is_verified', true)->count(),
                'recent_users' => User::with(['company:id,name', 'branch:id,name'])
                    ->latest()
                    ->take(5)
                    ->get(['id', 'name', 'email', 'phone_number', 'role', 'status', 'company_id', 'branch_id', 'created_at']),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fast method to get users by company and branch (optimized for performance).
     */
    public function fastByCompanyBranch(Request $request): JsonResponse
    {
        try {
            $rules = [
                'company_id' => 'required|exists:companies,id',
                'branch_id' => 'nullable|exists:branches,id',
                'limit' => 'nullable|integer|min:1|max:100',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $limit = $request->get('limit', 50);

            // Ultra-fast query with minimal data and optimized indexes
            $query = User::select(['id', 'name', 'email', 'phone_number', 'role', 'employee_id'])
                ->where('company_id', $request->company_id)
                ->where('status', 'active')
                ->where('is_verified', true);

            if ($request->branch_id) {
                $query->where('branch_id', $request->branch_id);
            }

            $users = $query->orderBy('name')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully (fast mode)',
                'data' => $users,
                'total' => $users->count(),
                'limited' => $users->count() >= $limit
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get users for a specific company and branch with detailed filtering.
     */
    public function getByCompanyBranch(Request $request): JsonResponse
    {
        try {
            $rules = [
                'company_id' => 'required|exists:companies,id',
                'branch_id' => 'nullable|exists:branches,id',
                'role' => 'nullable|in:super_admin,admin,company_admin,branch_admin,investigator',
                'status' => 'nullable|in:active,inactive,pending,suspended',
                'is_verified' => 'nullable|boolean',
                'search' => 'nullable|string|max:255',
                'sort_by' => 'nullable|in:name,email,role,status,created_at',
                'sort_direction' => 'nullable|in:asc,desc',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Build query with company filter
            $query = User::with(['company:id,name', 'branch:id,name,location'])
                ->where('company_id', $request->company_id);

            // Apply branch filter if provided
            if ($request->has('branch_id') && $request->branch_id !== null) {
                $query->where('branch_id', $request->branch_id);

                // Verify branch belongs to the company
                $branch = Branch::where('id', $request->branch_id)
                    ->where('company_id', $request->company_id)
                    ->first();

                if (!$branch) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Branch does not belong to the specified company'
                    ], 422);
                }
            }

            // Apply additional filters
            if ($request->has('role') && $request->role !== '') {
                $query->where('role', $request->role);
            }

            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            if ($request->has('is_verified') && $request->is_verified !== null) {
                $query->where('is_verified', $request->is_verified);
            }

            // Apply search filter
            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%")
                        ->orWhere('employee_id', 'ILIKE', "%{$search}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Get company and branch info for response
            $company = Company::select('id', 'name')->find($request->company_id);
            $branch = null;
            if ($request->branch_id) {
                $branch = Branch::select('id', 'name', 'location')->find($request->branch_id);
            }

            // Get all users without pagination
            $users = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => [
                    'company' => $company,
                    'branch' => $branch,
                    'users' => $users,
                    'total' => $users->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Send invitation email to user.
     */
    private function sendInvitationEmail(User $user, string $invitationUrl, bool $isAdminUser): bool
    {
        try {
            Mail::to($user->email)->send(new UserInvitation($user, $invitationUrl, $isAdminUser));
            return true;
        } catch (\Exception $e) {
            // Log the error but don't fail the user creation
            Log::error('Failed to send invitation email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send invitation SMS to user.
     */
    private function sendInvitationSms(User $user, string $invitationUrl, bool $isAdminUser): bool
    {
        try {
            $smsService = new SmsService();

            // Determine company name
            $companyName = $user->company ? $user->company->name : 'SafeVoice';

            // Send SMS with the invitation URL
            $result = $smsService->sendInvitation(
                $user->phone_number,
                $user->name,
                $invitationUrl,
                $companyName
            );

            return $result['success'];
        } catch (\Exception $e) {
            // Log the error but don't fail the user creation
            Log::error('Failed to send invitation SMS', [
                'user_id' => $user->id,
                'phone_number' => $user->phone_number,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Public API to get users for a specific company (no authentication required).
     * Returns only active users with minimal information for frontend use.
     */
    public function publicByCompany(string $companyId): JsonResponse
    {
        try {
            // Verify company exists and is active
            $company = Company::where('id', $companyId)
                ->where('status', true)
                ->firstOrFail();

            // Get active users for the company
            $users = User::where('company_id', $companyId)
                ->where('status', 'active')
                ->where('is_verified', true)
                ->with(['branch:id,name'])
                ->select([
                    'id',
                    'name',
                    'email',
                    'phone_number',
                    'role',
                    'branch_id',
                    'employee_id'
                ])
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully.',
                'data' => [
                    'company' => [
                        'id' => $company->id,
                        'name' => $company->name,
                    ],
                    'users' => $users,
                    'total' => $users->count()
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found or inactive.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Public API to get users for a specific branch (no authentication required).
     * Returns only active users with minimal information for frontend use.
     */
    public function publicByBranch(string $companyId, string $branchId): JsonResponse
    {
        try {
            // Verify company exists and is active
            $company = Company::where('id', $companyId)
                ->where('status', true)
                ->firstOrFail();

            // Verify branch exists, is active, and belongs to the company
            $branch = Branch::where('id', $branchId)
                ->where('company_id', $companyId)
                ->where('status', true)
                ->firstOrFail();

            // Get active users for the specific branch
            $users = User::where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('status', 'active')
                ->select([
                    'id',
                    'name',
                    'email',
                    'phone_number',
                    'role',
                    'employee_id'
                ])
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Branch users retrieved successfully.',
                'data' => $users
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company or branch not found or inactive.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve branch users.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get only investigators from users.
     * Returns users with 'investigator' role.
     */
    public function investigators(Request $request): JsonResponse
    {
        try {
            // Start with query for investigator role only
            $query = User::where('role', 'investigator')
                ->select([
                    'id',
                    'name',
                    'email',
                    'phone_number',
                    'role',
                    'status',
                    'is_verified',
                    'company_id',
                    'branch_id',
                    'employee_id',
                    'created_at',
                    'updated_at'
                ]);

            // Apply filters
            if ($request->has('company_id') && $request->company_id !== '') {
                $query->where('company_id', $request->company_id);
            }

            if ($request->has('branch_id') && $request->branch_id !== '') {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            // Search functionality
            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%")
                        ->orWhere('employee_id', 'ILIKE', "%{$search}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            if (in_array($sortBy, ['name', 'email', 'status', 'created_at'])) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Get all investigators
            $investigators = $query->get();

            // Load relationships efficiently
            $investigators->load([
                'company:id,name',
                'branch:id,name,location'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Investigators retrieved successfully.',
                'data' => $investigators,
                'total' => $investigators->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve investigators', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve investigators.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get companies that a user is not allocated to.
     */
    public function availableCompanies(string $userId): JsonResponse
    {
        try {
            // Find the user
            $user = User::findOrFail($userId);

            // Get the user's current company_id (if they belong to one)
            $userCompanyId = $user->company_id;

            // Get all companies except the one the user is currently allocated to
            $query = Company::query();

            if ($userCompanyId) {
                $query->where('id', '!=', $userCompanyId);
            }

            $companies = $query->where('status', true)
                ->select([
                    'id',
                    'name',
                    'email',
                    'contact',
                    'plan',
                    'status',
                    'created_at'
                ])
                ->withCount(['users', 'branches'])
                ->orderBy('name', 'asc')
                ->get()
                ->map(function ($company) {
                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'email' => $company->email,
                        'contact' => $company->contact,
                        'plan' => $company->plan,
                        'total_users' => $company->users_count,
                        'total_branches' => $company->branches_count,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Available companies retrieved successfully.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'current_company_id' => $userCompanyId,
                        'recipient_type' => $user->recipient_type,
                    ],
                    'companies' => $companies,
                    'total' => $companies->count()
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve available companies for user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available companies.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}

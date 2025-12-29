<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUlids, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'branch_id',
        'phone_number',
        'sms_invitation',
        'role',
        'status',
        'phone',
        'employee_id',
        'is_verified',
        'permissions',
        'invitation_token',
        'invitation_expires_at',
        // Recipient type for branch users only
        'recipient_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'is_verified' => 'boolean',
            'force_password_change' => 'boolean',
            'permissions' => 'array',
            'sms_invitation' => 'boolean',
        ];
    }

    // User role constants
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_ADMIN = 'admin';
    const ROLE_COMPANY_ADMIN = 'company_admin';
    const ROLE_BRANCH_ADMIN = 'branch_admin';
    const ROLE_INVESTIGATOR = 'investigator';

    // User status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_PENDING = 'pending';

    /**
     * Get the company that the user belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the branch that the user belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }



    /**
     * Get the branches that this user manages.
     */
    public function managedBranches(): HasMany
    {
        return $this->hasMany(Branch::class, 'manager_id');
    }

    /**
     * Get the branch that this user manages (singular).
     */
    public function managedBranch(): HasOne
    {
        return $this->hasOne(Branch::class, 'manager_id');
    }

    /**
     * Get the departments that this user heads.
     */


    /**
     * Get the investigator profile for this user.
     */
    public function investigator(): HasOne
    {
        return $this->hasOne(Investigator::class);
    }

    /**
     * Get all case assignments made by this user.
     */
    public function madeAssignments(): HasMany
    {
        return $this->hasMany(CaseAssignment::class, 'assigned_by_user_id');
    }

    /**
     * Get all case assignments unassigned by this user.
     */
    public function unassignedAssignments(): HasMany
    {
        return $this->hasMany(CaseAssignment::class, 'unassigned_by_user_id');
    }

    /**
     * Get all case assignments for this investigator.
     */
    public function investigatorAssignments(): HasMany
    {
        return $this->hasMany(CaseAssignment::class, 'investigator_id');
    }

    /**
     * Get all cases assigned to this user (through assignee relationship).
     */
    public function assignedCases(): HasMany
    {
        return $this->hasMany(CaseModel::class, 'assigned_to');
    }

    /**
     * Get all case involvements where this user is an involved party.
     */
    public function caseInvolvements(): HasMany
    {
        return $this->hasMany(CaseInvolvedParty::class);
    }

    /**
     * Get cases where this user is involved as a witness.
     */
    public function witnessInCases(): HasMany
    {
        return $this->caseInvolvements()->witnesses();
    }

    /**
     * Get cases where this user is involved as a complainant.
     */
    public function complainantInCases(): HasMany
    {
        return $this->caseInvolvements()->complainants();
    }

    /**
     * Get cases where this user is involved as a victim.
     */
    public function victimInCases(): HasMany
    {
        return $this->caseInvolvements()->victims();
    }

    /**
     * Get all cases where this user has any involvement.
     */
    public function allInvolvedCases()
    {
        return $this->hasManyThrough(
            CaseModel::class,
            CaseInvolvedParty::class,
            'user_id',
            'id',
            'id',
            'case_id'
        );
    }

    /**
     * Get all attachments uploaded by this user.
     */
    public function uploadedAttachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'uploaded_by');
    }

    /**
     * Get public attachments uploaded by this user.
     */
    public function publicUploadedAttachments(): HasMany
    {
        return $this->uploadedAttachments()->public();
    }

    /**
     * Get internal attachments uploaded by this user.
     */
    public function internalUploadedAttachments(): HasMany
    {
        return $this->uploadedAttachments()->internal();
    }

    /**
     * Get image attachments uploaded by this user.
     */
    public function uploadedImages(): HasMany
    {
        return $this->uploadedAttachments()->images();
    }

    /**
     * Get document attachments uploaded by this user.
     */
    public function uploadedDocuments(): HasMany
    {
        return $this->uploadedAttachments()->documents();
    }

    /**
     * Get user's upload statistics.
     */
    public function getUploadStatsAttribute(): array
    {
        return [
            'total_uploads' => $this->uploadedAttachments()->count(),
            'total_size' => $this->uploadedAttachments()->sum('file_size'),
            'images' => $this->uploadedImages()->count(),
            'documents' => $this->uploadedDocuments()->count(),
            'public' => $this->publicUploadedAttachments()->count(),
            'internal' => $this->internalUploadedAttachments()->count(),
        ];
    }

    /**
     * Get all notifications sent to this user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    /**
     * Get unread notifications for this user.
     */
    public function unreadNotifications(): HasMany
    {
        return $this->notifications()->where('status', '!=', 'read');
    }

    /**
     * Get read notifications for this user.
     */
    public function readNotifications(): HasMany
    {
        return $this->notifications()->where('status', 'read');
    }

    /**
     * Get high priority notifications for this user.
     */
    public function highPriorityNotifications(): HasMany
    {
        return $this->notifications()->highPriority();
    }

    /**
     * Get recent notifications for this user.
     */
    public function recentNotifications($hours = 24): HasMany
    {
        return $this->notifications()->recent($hours);
    }

    /**
     * Get notification statistics for this user.
     */
    public function getNotificationStatsAttribute(): array
    {
        return [
            'total' => $this->notifications()->count(),
            'unread' => $this->unreadNotifications()->count(),
            'read' => $this->readNotifications()->count(),
            'high_priority' => $this->highPriorityNotifications()->count(),
            'email' => $this->notifications()->email()->count(),
            'sms' => $this->notifications()->sms()->count(),
            'push' => $this->notifications()->push()->count(),
        ];
    }

    // Authentication Helper Methods

    /**
     * Check if user is admin (super admin or admin)
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Check if user is company admin
     */
    public function isCompanyAdmin(): bool
    {
        return $this->role === self::ROLE_COMPANY_ADMIN;
    }

    /**
     * Check if user is branch admin
     */
    public function isBranchAdmin(): bool
    {
        return $this->role === self::ROLE_BRANCH_ADMIN;
    }

    /**
     * Check if user account is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if user account is locked
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Check if user can login
     */
    public function canLogin(): bool
    {
        return $this->isActive() &&
            !$this->isLocked() &&
            $this->is_verified;
    }

    /**
     * Record successful login
     */
    public function recordLogin(string $ipAddress): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
            'failed_login_attempts' => 0,
        ]);
    }

    /**
     * Record failed login attempt
     */
    public function recordFailedLogin(): void
    {
        $attempts = $this->failed_login_attempts + 1;
        $updates = ['failed_login_attempts' => $attempts];

        // Lock account after 5 failed attempts
        if ($attempts >= 5) {
            $updates['locked_until'] = now()->addMinutes(30);
        }

        $this->update($updates);
    }

    /**
     * Unlock user account
     */
    public function unlock(): void
    {
        $this->update([
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ]);
    }

    /**
     * Get user's full name and role
     */
    public function getFullNameWithRoleAttribute(): string
    {
        return $this->name . ' (' . ucwords(str_replace('_', ' ', $this->role)) . ')';
    }

    // Scopes for authentication

    /**
     * Scope to get only admin users
     */
    public function scopeAdmins($query)
    {
        return $query->whereIn('role', [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    /**
     * Scope to get only company users (company admins)
     */
    public function scopeCompanies($query)
    {
        return $query->where('role', self::ROLE_COMPANY_ADMIN);
    }

    /**
     * Scope to get only company branch users
     */
    public function scopeBranchUsers($query)
    {
        return $query->whereIn('role', [
            self::ROLE_BRANCH_ADMIN,
            self::ROLE_INVESTIGATOR
        ]);
    }

    /**
     * Scope to get only active users
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get verified users
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope to get users by company
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Get all involved parties for this user
     * Note: involved_parties.employee_id column actually stores user.id (primary key)
     */
    public function involvedParties(): HasMany
    {
        return $this->hasMany(CaseInvolvedParty::class, 'employee_id', 'id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CaseModel extends BaseModel
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cases';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'branch_id',
        'type',
        'status',
        'note',
        'attachment',
        'priority',
        'source',
        'created_by_type',
        'created_by_contact_json',
        'case_token',
        'title',
        'description',
        'assigned_to',
        'due_date',
        'resolved_at',
        'resolution_note',
        'location_description',
        'date_time_type',
        'date_occurred',
        'time_occurred',
        'general_timeframe',
        'company_relationship',
        'witness_info',
        'follow_up_required',
        'access_id',
        'access_password',
        'is_anonymous',
        'session_token',
        'case_close_classification',
        'case_closed_at',
        'closed_by',
        'session_expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_by_contact_json' => 'array',
        'attachment' => 'array',
        'witness_info' => 'array',
        'due_date' => 'date',
        'resolved_at' => 'datetime',
        'case_closed_at' => 'datetime',
        'follow_up_required' => 'boolean',
        'is_anonymous' => 'boolean',
        'date_occurred' => 'date',
        'time_occurred' => 'string',
        'priority' => 'integer',
        'session_expires_at' => 'datetime',
    ];

    /**
     * Boot method to generate case token.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($case) {
            if (empty($case->case_token)) {
                $case->case_token = 'CASE-' . strtoupper(Str::random(8));
            }
        });
    }

    /**
     * Get the company that owns the case.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the branch associated with the case.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }



    /**
     * Get the user assigned to the case.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who closed the case.
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Scope a query to filter by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by priority.
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to get open cases.
     */
    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', ['closed', 'resolved']);
    }

    /**
     * Scope a query to get assigned cases.
     */
    public function scopeAssigned($query)
    {
        return $query->whereNotNull('assigned_to');
    }

    /**
     * Scope a query to get unassigned cases.
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * Scope a query to get overdue cases.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', ['closed', 'resolved']);
    }

    /**
     * Get the case's display title.
     */
    public function getDisplayTitleAttribute(): string
    {
        return $this->title ?: $this->case_token;
    }

    /**
     * Check if the case is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date &&
            $this->due_date->isPast() &&
            !in_array($this->status, ['closed', 'resolved']);
    }

    /**
     * Check if the case is anonymous.
     */
    public function isAnonymous(): bool
    {
        return $this->created_by_type === 'anonymous';
    }

    /**
     * Get creator contact information.
     */
    public function getCreatorContactAttribute(): ?array
    {
        return $this->created_by_contact_json;
    }

    /**
     * Get all assignments for this case.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(CaseAssignment::class, 'case_id');
    }

    /**
     * Get all active assignments for this case.
     */
    public function activeAssignments(): HasMany
    {
        return $this->assignments()->active();
    }

    /**
     * Get all files attached to this case.
     */
    public function files(): HasMany
    {
        return $this->hasMany(CaseFile::class, 'case_id');
    }

    /**
     * Get evidence files only.
     */
    public function evidenceFiles(): HasMany
    {
        return $this->files()->byType('evidence');
    }

    /**
     * Get document files only.
     */
    public function documentFiles(): HasMany
    {
        return $this->files()->byType('document');
    }

    /**
     * Get confidential files only.
     */
    public function confidentialFiles(): HasMany
    {
        return $this->files()->confidential();
    }

    /**
     * Get the current primary investigator assignment.
     */
    public function primaryAssignment()
    {
        return $this->assignments()
            ->active()
            ->where('assignment_type', 'primary')
            ->first();
    }

    /**
     * Get all investigators assigned to this case.
     */
    public function investigators()
    {
        return $this->hasManyThrough(
            Investigator::class,
            CaseAssignment::class,
            'case_id',
            'id',
            'id',
            'investigator_id'
        )->where('case_assignments.status', 'active');
    }

    /**
     * Get all involved parties for this case.
     */
    public function involvedParties(): HasMany
    {
        return $this->hasMany(CaseInvolvedParty::class, 'case_id');
    }

    /**
     * Get all department assignments for this case.
     */
    public function caseDepartments(): HasMany
    {
        return $this->hasMany(CaseDepartment::class, 'case_id');
    }

    /**
     * Get all departments assigned to this case.
     */
    public function departments()
    {
        return $this->belongsToMany(
            Department::class,
            'case_departments',
            'case_id',
            'department_id'
        )->withPivot(['assigned_at', 'assigned_by', 'assignment_note'])
            ->withTimestamps()
            ->whereNull('case_departments.deleted_at');
    }

    /**
     * Get all category assignments for this case.
     */
    public function caseCategories(): HasMany
    {
        return $this->hasMany(CaseCategory::class, 'case_id');
    }

    /**
     * Get all incident categories assigned to this case (many-to-many).
     */
    public function incidentCategories()
    {
        return $this->belongsToMany(
            IncidentCategory::class,
            'case_categories',
            'case_id',
            'category_id'
        )->wherePivot('category_type', 'incident')
            ->withPivot(['assigned_at', 'assigned_by', 'assignment_note', 'category_type'])
            ->withTimestamps();
    }

    /**
     * Get all feedback categories assigned to this case (many-to-many).
     */
    public function feedbackCategories()
    {
        return $this->belongsToMany(
            FeedbackCategory::class,
            'case_categories',
            'case_id',
            'category_id'
        )->wherePivot('category_type', 'feedback')
            ->withPivot(['assigned_at', 'assigned_by', 'assignment_note', 'category_type'])
            ->withTimestamps();
    }

    /**
     * Get all additional parties for this case.
     */
    public function additionalParties(): HasMany
    {
        return $this->hasMany(CaseAdditionalParty::class, 'case_id');
    }

    /**
     * Get witnesses for this case.
     */
    public function witnesses(): HasMany
    {
        return $this->involvedParties()->witnesses();
    }

    /**
     * Get perpetrators for this case.
     */
    public function perpetrators(): HasMany
    {
        return $this->involvedParties()->perpetrators();
    }

    /**
     * Get complainants for this case.
     */
    public function complainants(): HasMany
    {
        return $this->involvedParties()->complainants();
    }

    /**
     * Get victims for this case.
     */
    public function victims(): HasMany
    {
        return $this->involvedParties()->victims();
    }

    /**
     * Get registered users involved in this case.
     */
    public function registeredInvolvedParties(): HasMany
    {
        return $this->involvedParties()->registeredUsers();
    }

    /**
     * Get external parties involved in this case.
     */
    public function externalInvolvedParties(): HasMany
    {
        return $this->involvedParties()->externalParties();
    }

    /**
     * Get all messages for this case.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CaseMessage::class, 'case_id');
    }

    /**
     * Get all public messages for this case.
     */
    public function publicMessages(): HasMany
    {
        return $this->messages()->public()->orderBy('created_at');
    }

    /**
     * Get all internal messages for this case.
     */
    public function internalMessages(): HasMany
    {
        return $this->messages()->internal()->orderBy('created_at');
    }

    /**
     * Get messages from reporters.
     */
    public function reporterMessages(): HasMany
    {
        return $this->messages()->fromReporter()->orderBy('created_at');
    }

    /**
     * Get messages from investigators.
     */
    public function investigatorMessages(): HasMany
    {
        return $this->messages()->fromInvestigator()->orderBy('created_at');
    }

    /**
     * Get system messages for this case.
     */
    public function systemMessages(): HasMany
    {
        return $this->messages()->fromSystem()->orderBy('created_at');
    }

    /**
     * Get unread messages for this case.
     */
    public function unreadMessages(): HasMany
    {
        return $this->messages()->unread()->orderBy('created_at');
    }

    /**
     * Get messages with attachments.
     */
    public function messagesWithAttachments(): HasMany
    {
        return $this->messages()->withAttachments()->orderBy('created_at');
    }

    /**
     * Get the latest message for this case.
     */
    public function latestMessage()
    {
        return $this->messages()->latest()->first();
    }

    /**
     * Get the latest public message for this case.
     */
    public function latestPublicMessage()
    {
        return $this->publicMessages()->latest()->first();
    }

    /**
     * Add a message to this case.
     */
    public function addMessage(
        string $message,
        string $senderType = 'system',
        string $visibility = 'public',
        $sender = null,
        array $options = []
    ): CaseMessage {
        $messageData = array_merge([
            'case_id' => $this->id,
            'message' => $message,
            'sender_type' => $senderType,
            'visibility' => $visibility,
            'sender_id' => $sender ? $sender->id : null,
        ], $options);

        return CaseMessage::create($messageData);
    }

    /**
     * Add a system message to this case.
     */
    public function addSystemMessage(string $message, string $visibility = 'internal'): CaseMessage
    {
        return $this->addMessage($message, 'system', $visibility, null, [
            'message_type' => 'system',
        ]);
    }

    /**
     * Get message statistics for this case.
     */
    public function getMessageStatsAttribute(): array
    {
        return [
            'total' => $this->messages()->count(),
            'public' => $this->publicMessages()->count(),
            'internal' => $this->internalMessages()->count(),
            'unread' => $this->unreadMessages()->count(),
            'with_attachments' => $this->messagesWithAttachments()->count(),
            'from_reporter' => $this->reporterMessages()->count(),
            'from_investigator' => $this->investigatorMessages()->count(),
            'from_system' => $this->systemMessages()->count(),
        ];
    }

    /**
     * Get all attachments for this case.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'case_id');
    }

    /**
     * Get all public attachments for this case.
     */
    public function publicAttachments(): HasMany
    {
        return $this->attachments()->public()->clean();
    }

    /**
     * Get all internal attachments for this case.
     */
    public function internalAttachments(): HasMany
    {
        return $this->attachments()->internal()->clean();
    }

    /**
     * Get image attachments for this case.
     */
    public function imageAttachments(): HasMany
    {
        return $this->attachments()->images()->clean();
    }

    /**
     * Get document attachments for this case.
     */
    public function documentAttachments(): HasMany
    {
        return $this->attachments()->documents()->clean();
    }

    /**
     * Get clean (safe) attachments for this case.
     */
    public function safeAttachments(): HasMany
    {
        return $this->attachments()->clean();
    }

    /**
     * Get attachments by specific uploader.
     */
    public function attachmentsByUser($userId): HasMany
    {
        return $this->attachments()->uploadedBy($userId);
    }

    /**
     * Get attachment statistics for this case.
     */
    public function getAttachmentStatsAttribute(): array
    {
        return [
            'total' => $this->attachments()->count(),
            'public' => $this->publicAttachments()->count(),
            'internal' => $this->internalAttachments()->count(),
            'images' => $this->imageAttachments()->count(),
            'documents' => $this->documentAttachments()->count(),
            'total_size' => $this->attachments()->sum('file_size'),
            'clean' => $this->safeAttachments()->count(),
            'pending_scan' => $this->attachments()->pendingScan()->count(),
        ];
    }

    /**
     * Get all notifications for this case.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'case_id');
    }

    /**
     * Get all threads for this case.
     */
    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class, 'case_id');
    }

    /**
     * Get pending notifications for this case.
     */
    public function pendingNotifications(): HasMany
    {
        return $this->notifications()->pending();
    }

    /**
     * Get sent notifications for this case.
     */
    public function sentNotifications(): HasMany
    {
        return $this->notifications()->sent();
    }

    /**
     * Get failed notifications for this case.
     */
    public function failedNotifications(): HasMany
    {
        return $this->notifications()->failed();
    }

    /**
     * Get email notifications for this case.
     */
    public function emailNotifications(): HasMany
    {
        return $this->notifications()->email();
    }

    /**
     * Get SMS notifications for this case.
     */
    public function smsNotifications(): HasMany
    {
        return $this->notifications()->sms();
    }

    /**
     * Get WhatsApp notifications for this case.
     */
    public function whatsappNotifications(): HasMany
    {
        return $this->notifications()->whatsapp();
    }

    /**
     * Get push notifications for this case.
     */
    public function pushNotifications(): HasMany
    {
        return $this->notifications()->push();
    }

    /**
     * Get high priority notifications for this case.
     */
    public function highPriorityNotifications(): HasMany
    {
        return $this->notifications()->highPriority();
    }

    /**
     * Get recent notifications for this case.
     */
    public function recentNotifications($hours = 24): HasMany
    {
        return $this->notifications()->recent($hours);
    }

    /**
     * Send notification for this case.
     */
    public function sendNotification(
        string $channel,
        array $recipients,
        string $type = 'case_update',
        array $additionalData = []
    ): array {
        return Notification::createCaseNotification(
            $this,
            $channel,
            $recipients,
            $type,
            $additionalData
        );
    }

    /**
     * Send email notification for this case.
     */
    public function sendEmailNotification(
        array $recipients,
        string $type = 'case_update',
        array $additionalData = []
    ): array {
        return $this->sendNotification('email', $recipients, $type, $additionalData);
    }

    /**
     * Send SMS notification for this case.
     */
    public function sendSmsNotification(
        array $recipients,
        string $type = 'case_update',
        array $additionalData = []
    ): array {
        return $this->sendNotification('sms', $recipients, $type, $additionalData);
    }

    /**
     * Send WhatsApp notification for this case.
     */
    public function sendWhatsAppNotification(
        array $recipients,
        string $type = 'case_update',
        array $additionalData = []
    ): array {
        return $this->sendNotification('whatsapp', $recipients, $type, $additionalData);
    }

    /**
     * Get notification statistics for this case.
     */
    public function getNotificationStatsAttribute(): array
    {
        return [
            'total' => $this->notifications()->count(),
            'pending' => $this->pendingNotifications()->count(),
            'sent' => $this->sentNotifications()->count(),
            'failed' => $this->failedNotifications()->count(),
            'email' => $this->emailNotifications()->count(),
            'sms' => $this->smsNotifications()->count(),
            'whatsapp' => $this->whatsappNotifications()->count(),
            'push' => $this->pushNotifications()->count(),
            'high_priority' => $this->highPriorityNotifications()->count(),
        ];
    }
}

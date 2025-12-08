<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseMessage extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'case_id',
        'thread_id',
        'sender_id',
        'sender_type',
        'message',
        'has_attachments',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'has_attachments' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Get the case that this message belongs to.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    /**
     * Get the thread that this message belongs to.
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'thread_id');
    }

    /**
     * Get the sender (polymorphic - could be User, Investigator, or system).
     */
    public function sender(): MorphTo
    {
        // For system and reporter types, return a morphTo with default values
        if (in_array($this->sender_type, ['system', 'reporter'])) {
            return $this->morphTo('sender', 'sender_type', 'sender_id')->withDefault([
                'name' => $this->getSenderDisplayName(),
                'email' => null
            ]);
        }

        // Handle all other types including User, user, admin, etc.
        return $this->morphTo('sender', 'sender_type', 'sender_id');
    }

    /**
     * Get the sender display name based on sender_type.
     */
    public function getSenderDisplayName(): string
    {
        return match ($this->sender_type) {
            'system' => 'System',
            'reporter' => 'Case Reporter',
            'investigator' => 'Investigator',
            'user' => 'User',
            'branch_admin' => 'Branch Admin',
            'company_admin' => 'Company Admin',
            'admin' => 'Admin',
            default => ucfirst($this->sender_type ?? 'Unknown')
        };
    }

    /**
     * Get the read records for this message.
     */
    public function readRecords(): HasMany
    {
        return $this->hasMany(MessageRead::class, 'message_id');
    }

    /**
     * Get the read record for this message.
     */
    public function readRecord(): HasOne
    {
        return $this->hasOne(MessageRead::class, 'message_id');
    }

    /**
     * Get all files attached to this message.
     */
    public function files(): HasMany
    {
        return $this->hasMany(CaseFile::class, 'case_message_id');
    }

    /**
     * Scope a query to filter by case.
     */
    public function scopeForCase($query, $caseId)
    {
        return $query->where('case_id', $caseId);
    }

    /**
     * Scope a query to filter by sender type.
     */
    public function scopeBySenderType($query, $senderType)
    {
        return $query->where('sender_type', $senderType);
    }

    /**
     * Scope a query to filter by visibility.
     */
    public function scopeByVisibility($query, $visibility)
    {
        return $query->where('visibility', $visibility);
    }

    /**
     * Scope a query to get public messages only.
     */
    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    /**
     * Scope a query to get internal messages only.
     */
    public function scopeInternal($query)
    {
        return $query->where('visibility', 'internal');
    }

    /**
     * Scope a query to get reporter messages.
     */
    public function scopeFromReporter($query)
    {
        return $query->where('sender_type', 'reporter');
    }

    /**
     * Scope a query to get investigator messages.
     */
    public function scopeFromInvestigator($query)
    {
        return $query->where('sender_type', 'investigator');
    }

    /**
     * Scope a query to get system messages.
     */
    public function scopeFromSystem($query)
    {
        return $query->where('sender_type', 'system');
    }

    /**
     * Scope a query to get messages with attachments.
     */
    public function scopeWithAttachments($query)
    {
        return $query->where('has_attachments', true);
    }

    /**
     * Scope a query to get unread messages.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope a query to get read messages.
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope a query to get high priority messages.
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }

    /**
     * Scope a query to get root messages (not replies).
     */
    public function scopeRootMessages($query)
    {
        return $query->whereNull('parent_message_id');
    }

    /**
     * Check if this message is public.
     */
    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    /**
     * Check if this message is internal.
     */
    public function isInternal(): bool
    {
        return $this->visibility === 'internal';
    }

    /**
     * Check if this message has attachments.
     */
    public function hasAttachments(): bool
    {
        return $this->has_attachments && !empty($this->attachments);
    }

    /**
     * Check if this message is from a reporter.
     */
    public function isFromReporter(): bool
    {
        return $this->sender_type === 'reporter';
    }

    /**
     * Check if this message is from an investigator.
     */
    public function isFromInvestigator(): bool
    {
        return $this->sender_type === 'investigator';
    }

    /**
     * Check if this message is from the system.
     */
    public function isFromSystem(): bool
    {
        return $this->sender_type === 'system';
    }

    /**
     * Check if this message is unread.
     */
    public function isUnread(): bool
    {
        return !$this->is_read;
    }

    /**
     * Check if this message is a reply.
     */
    public function isReply(): bool
    {
        return !is_null($this->parent_message_id);
    }

    /**
     * Mark this message as read.
     */
    public function markAsRead(User $user = null): bool
    {
        return $this->update([
            'is_read' => true,
            'read_at' => now(),
            'read_by_user_id' => $user ? $user->id : null,
        ]);
    }

    /**
     * Get the sender's display name.
     */
    public function getSenderNameAttribute(): string
    {
        if (in_array($this->sender_type, ['system', 'reporter'])) {
            return match ($this->sender_type) {
                'system' => 'System',
                'reporter' => 'Case Reporter',
                default => ucfirst($this->sender_type)
            };
        }

        try {
            $sender = $this->sender;
            if ($sender && isset($sender->name)) {
                return $sender->name;
            }
            if ($sender && isset($sender->display_name)) {
                return $sender->display_name;
            }
        } catch (\Exception $e) {
            // If polymorphic relationship fails, return display name
        }

        return $this->getSenderDisplayName();
    }

    /**
     * Get message type display name.
     */
    public function getMessageTypeDisplayAttribute(): string
    {
        return match ($this->message_type) {
            'update' => 'Case Update',
            'comment' => 'Comment',
            'attachment' => 'Attachment',
            'status_change' => 'Status Change',
            'assignment' => 'Assignment',
            'notification' => 'Notification',
            default => ucfirst($this->message_type ?? 'message'),
        };
    }

    /**
     * Get attachment count from related Attachment models.
     */
    public function getAttachmentCountAttribute(): int
    {
        return $this->messageAttachments()->count();
    }

    /**
     * Get attachments specifically for this message.
     */
    public function messageAttachments()
    {
        return $this->hasMany(Attachment::class, 'message_id');
    }

    /**
     * Get public attachments for this message.
     */
    public function publicMessageAttachments()
    {
        return $this->messageAttachments()->public()->clean();
    }

    /**
     * Get internal attachments for this message.
     */
    public function internalMessageAttachments()
    {
        return $this->messageAttachments()->internal()->clean();
    }

    /**
     * Check if message has any attachments.
     */
    public function hasMessageAttachments(): bool
    {
        return $this->messageAttachments()->exists();
    }
}

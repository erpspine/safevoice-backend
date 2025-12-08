<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Notification extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'branch_id',
        'case_id',
        'recipient_id',
        'user_id',
        'channel',
        'payload_json',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'retry_count',
        'error_message',
        'notification_type',
        'priority',
        'template_name',
        'subject',
        'message_preview',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payload_json' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'retry_count' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the branch this notification belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the case this notification is about.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    /**
     * Get the recipient this notification was sent to.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * Get the user this notification was sent to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to filter by branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope a query to filter by case.
     */
    public function scopeForCase($query, $caseId)
    {
        return $query->where('case_id', $caseId);
    }

    /**
     * Scope a query to filter by channel.
     */
    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to get pending notifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to get sent notifications.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope a query to get delivered notifications.
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Scope a query to get failed notifications.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to get email notifications.
     */
    public function scopeEmail($query)
    {
        return $query->where('channel', 'email');
    }

    /**
     * Scope a query to get SMS notifications.
     */
    public function scopeSms($query)
    {
        return $query->where('channel', 'sms');
    }

    /**
     * Scope a query to get WhatsApp notifications.
     */
    public function scopeWhatsapp($query)
    {
        return $query->where('channel', 'whatsapp');
    }

    /**
     * Scope a query to get push notifications.
     */
    public function scopePush($query)
    {
        return $query->where('channel', 'push');
    }

    /**
     * Scope a query to get high priority notifications.
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }

    /**
     * Scope a query to get notifications that need retry.
     */
    public function scopeNeedsRetry($query)
    {
        return $query->where('status', 'failed')
            ->where('retry_count', '<', 3)
            ->where('failed_at', '>', now()->subHours(24));
    }

    /**
     * Scope a query to get recent notifications.
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Check if notification is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if notification was sent.
     */
    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'read']);
    }

    /**
     * Check if notification was delivered.
     */
    public function isDelivered(): bool
    {
        return in_array($this->status, ['delivered', 'read']);
    }

    /**
     * Check if notification was read.
     */
    public function isRead(): bool
    {
        return $this->status === 'read';
    }

    /**
     * Check if notification failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if notification can be retried.
     */
    public function canRetry(): bool
    {
        return $this->isFailed() &&
            $this->retry_count < 3 &&
            $this->failed_at &&
            $this->failed_at->gt(now()->subHours(24));
    }

    /**
     * Mark notification as sent.
     */
    public function markAsSent(): bool
    {
        return $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark notification as delivered.
     */
    public function markAsDelivered(): bool
    {
        return $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(): bool
    {
        return $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    /**
     * Mark notification as failed.
     */
    public function markAsFailed(string $errorMessage = null): bool
    {
        return $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Get the channel display name.
     */
    public function getChannelDisplayAttribute(): string
    {
        return match ($this->channel) {
            'email' => 'Email',
            'sms' => 'SMS',
            'whatsapp' => 'WhatsApp',
            'push' => 'Push Notification',
            'slack' => 'Slack',
            'teams' => 'Microsoft Teams',
            default => ucfirst($this->channel),
        };
    }

    /**
     * Get the status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'sent' => 'Sent',
            'delivered' => 'Delivered',
            'read' => 'Read',
            'failed' => 'Failed',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get the priority display name.
     */
    public function getPriorityDisplayAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
            default => ucfirst($this->priority ?? 'normal'),
        };
    }

    /**
     * Get time since sent/created.
     */
    public function getTimeSinceAttribute(): string
    {
        $date = $this->sent_at ?: $this->created_at;
        return $date ? $date->diffForHumans() : 'Never';
    }

    /**
     * Get delivery time (time between sent and delivered).
     */
    public function getDeliveryTimeAttribute(): ?int
    {
        if (!$this->sent_at || !$this->delivered_at) {
            return null;
        }

        return $this->sent_at->diffInSeconds($this->delivered_at);
    }

    /**
     * Get formatted delivery time.
     */
    public function getFormattedDeliveryTimeAttribute(): ?string
    {
        $seconds = $this->delivery_time;

        if ($seconds === null) {
            return null;
        }

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intval($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return $remainingSeconds > 0 ? "{$minutes}m {$remainingSeconds}s" : "{$minutes}m";
    }

    /**
     * Get recipient information.
     */
    public function getRecipientInfoAttribute(): array
    {
        $payload = $this->payload_json ?? [];

        return [
            'name' => $payload['recipient_name'] ?? 'Unknown',
            'email' => $payload['recipient_email'] ?? null,
            'phone' => $payload['recipient_phone'] ?? null,
            'address' => $payload['recipient_address'] ?? null,
        ];
    }

    /**
     * Check if this is a high priority notification.
     */
    public function isHighPriority(): bool
    {
        return in_array($this->priority, ['high', 'urgent']);
    }

    /**
     * Create a notification for a case update.
     */
    public static function createCaseNotification(
        CaseModel $case,
        string $channel,
        array $recipients,
        string $type = 'case_update',
        array $additionalData = []
    ): array {
        $notifications = [];

        foreach ($recipients as $recipient) {
            $payload = array_merge([
                'case_token' => $case->case_token,
                'case_title' => $case->title,
                'case_status' => $case->status,
                'notification_type' => $type,
            ], $additionalData);

            if (is_array($recipient)) {
                $payload = array_merge($payload, $recipient);
            }

            $notification = static::create([
                'branch_id' => $case->branch_id,
                'case_id' => $case->id,
                'channel' => $channel,
                'payload_json' => $payload,
                'notification_type' => $type,
                'status' => 'pending',
                'priority' => $additionalData['priority'] ?? 'normal',
            ]);

            $notifications[] = $notification;
        }

        return $notifications;
    }
}

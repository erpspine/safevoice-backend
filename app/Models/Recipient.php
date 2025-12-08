<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipient extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'branch_id',
        'name',
        'email',
        'phone',
        'role_hint',
        'position',
        'department',
        'is_primary_contact',
        'notification_preferences',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary_contact' => 'boolean',
        'notification_preferences' => 'array',
        'status' => 'boolean',
    ];

    /**
     * Get the branch that owns the recipient.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the company through the branch relationship.
     */
    public function company(): BelongsTo
    {
        return $this->branch->company();
    }

    /**
     * Scope a query to only include active recipients.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope a query to filter by branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope a query to get primary contacts.
     */
    public function scopePrimaryContacts($query)
    {
        return $query->where('is_primary_contact', true);
    }

    /**
     * Scope a query to filter by role hint.
     */
    public function scopeByRole($query, $roleHint)
    {
        return $query->where('role_hint', $roleHint);
    }

    /**
     * Get the full contact information.
     */
    public function getFullContactAttribute(): string
    {
        $contact = $this->name;
        if ($this->email) {
            $contact .= ' (' . $this->email . ')';
        }
        if ($this->phone) {
            $contact .= ' - ' . $this->phone;
        }
        return $contact;
    }

    /**
     * Get all notifications sent to this recipient.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'recipient_id');
    }

    /**
     * Get pending notifications for this recipient.
     */
    public function pendingNotifications(): HasMany
    {
        return $this->notifications()->pending();
    }

    /**
     * Get sent notifications for this recipient.
     */
    public function sentNotifications(): HasMany
    {
        return $this->notifications()->sent();
    }

    /**
     * Get failed notifications for this recipient.
     */
    public function failedNotifications(): HasMany
    {
        return $this->notifications()->failed();
    }

    /**
     * Get recent notifications for this recipient.
     */
    public function recentNotifications($hours = 24): HasMany
    {
        return $this->notifications()->recent($hours);
    }

    /**
     * Get notification statistics for this recipient.
     */
    public function getNotificationStatsAttribute(): array
    {
        return [
            'total' => $this->notifications()->count(),
            'pending' => $this->pendingNotifications()->count(),
            'sent' => $this->sentNotifications()->count(),
            'failed' => $this->failedNotifications()->count(),
            'email' => $this->notifications()->email()->count(),
            'sms' => $this->notifications()->sms()->count(),
            'whatsapp' => $this->notifications()->whatsapp()->count(),
        ];
    }
}

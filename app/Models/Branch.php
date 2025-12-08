<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'location',
        'address',
        'phone',
        'email',
        'manager_id',
        'status',
        'contact_person',
        'contact_phone',
        'contact_email',
        'branch_code',
        'is_active',
        'activated_until',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean',
        'is_active' => 'boolean',
        'activated_until' => 'date',
    ];

    /**
     * Get the company that owns the branch.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the branch manager.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get the users assigned to this branch.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the recipients for this branch.
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(Recipient::class);
    }

    /**
     * Get the cases for this branch.
     */
    public function cases(): HasMany
    {
        return $this->hasMany(CaseModel::class);
    }

    /**
     * Scope a query to only include active branches.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope a query to filter by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Get the full address including location.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([$this->address, $this->location]);
        return implode(', ', $parts);
    }

    /**
     * Get all notifications for this branch.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'branch_id');
    }

    /**
     * Get the subscriptions that activated this branch.
     */
    public function subscriptions()
    {
        return $this->belongsToMany(Subscription::class, 'subscription_branch')
            ->withPivot(['activated_from', 'activated_until'])
            ->withTimestamps();
    }

    /**
     * Get the routing rules for this branch.
     */
    public function routingRules(): HasMany
    {
        return $this->hasMany(RoutingRule::class);
    }

    /**
     * Get pending notifications for this branch.
     */
    public function pendingNotifications(): HasMany
    {
        return $this->notifications()->pending();
    }

    /**
     * Get sent notifications for this branch.
     */
    public function sentNotifications(): HasMany
    {
        return $this->notifications()->sent();
    }

    /**
     * Get failed notifications for this branch.
     */
    public function failedNotifications(): HasMany
    {
        return $this->notifications()->failed();
    }

    /**
     * Get notifications by channel for this branch.
     */
    public function notificationsByChannel(string $channel): HasMany
    {
        return $this->notifications()->byChannel($channel);
    }

    /**
     * Get recent notifications for this branch.
     */
    public function recentNotifications($hours = 24): HasMany
    {
        return $this->notifications()->recent($hours);
    }

    /**
     * Get notification statistics for this branch.
     */
    public function getNotificationStatsAttribute(): array
    {
        return [
            'total' => $this->notifications()->count(),
            'pending' => $this->pendingNotifications()->count(),
            'sent' => $this->sentNotifications()->count(),
            'failed' => $this->failedNotifications()->count(),
            'email' => $this->notificationsByChannel('email')->count(),
            'sms' => $this->notificationsByChannel('sms')->count(),
            'whatsapp' => $this->notificationsByChannel('whatsapp')->count(),
            'push' => $this->notificationsByChannel('push')->count(),
        ];
    }
}

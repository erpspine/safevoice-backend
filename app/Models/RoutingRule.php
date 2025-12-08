<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoutingRule extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'type',
        'category_id',
        'department_id',
        'branch_id',
        'recipients_json',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'recipients_json' => 'array',
    ];

    /**
     * Valid routing rule types
     */
    const TYPE_INCIDENT = 'incident';
    const TYPE_FEEDBACK = 'feedback';
    const TYPE_GENERAL = 'general';
    const TYPE_DEPARTMENT = 'department';
    const TYPE_BRANCH = 'branch';
    const TYPE_CATEGORY = 'category';

    /**
     * Get valid types array
     */
    public static function getValidTypes(): array
    {
        return [
            self::TYPE_INCIDENT,
            self::TYPE_FEEDBACK,
            self::TYPE_GENERAL,
            self::TYPE_DEPARTMENT,
            self::TYPE_BRANCH,
            self::TYPE_CATEGORY,
        ];
    }

    /**
     * Get the company that owns the routing rule.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the category associated with the routing rule (optional).
     */
    public function incidentCategory(): BelongsTo
    {
        return $this->belongsTo(IncidentCategory::class, 'category_id');
    }

    /**
     * Get the feedback category associated with the routing rule (optional).
     */
    public function feedbackCategory(): BelongsTo
    {
        return $this->belongsTo(FeedbackCategory::class, 'category_id');
    }

    /**
     * Get the department associated with the routing rule (optional).
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the branch associated with the routing rule (optional).
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the recipients from JSON as collection of recipient IDs
     */
    public function getRecipientsAttribute()
    {
        $recipientIds = $this->recipients_json ?? [];
        if (empty($recipientIds)) {
            return collect();
        }

        return Recipient::whereIn('id', $recipientIds)->get();
    }

    /**
     * Set recipients JSON from array of recipient IDs or models
     */
    public function setRecipientsAttribute($recipients)
    {
        if (is_array($recipients)) {
            // If array of IDs or models, extract IDs
            $ids = collect($recipients)->map(function ($recipient) {
                return is_string($recipient) ? $recipient : $recipient->id;
            })->toArray();

            $this->attributes['recipients_json'] = json_encode($ids);
        } else {
            $this->attributes['recipients_json'] = $recipients;
        }
    }

    /**
     * Scope for routing rules by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for routing rules by company
     */
    public function scopeByCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope for routing rules that match specific criteria
     */
    public function scopeMatching($query, array $criteria)
    {
        $query->where('company_id', $criteria['company_id']);

        if (isset($criteria['type'])) {
            $query->where('type', $criteria['type']);
        }

        if (isset($criteria['category_id'])) {
            $query->where(function ($q) use ($criteria) {
                $q->where('category_id', $criteria['category_id'])
                    ->orWhereNull('category_id');
            });
        }

        if (isset($criteria['department_id'])) {
            $query->where(function ($q) use ($criteria) {
                $q->where('department_id', $criteria['department_id'])
                    ->orWhereNull('department_id');
            });
        }

        if (isset($criteria['branch_id'])) {
            $query->where(function ($q) use ($criteria) {
                $q->where('branch_id', $criteria['branch_id'])
                    ->orWhereNull('branch_id');
            });
        }

        return $query;
    }

    /**
     * Check if this rule matches the given criteria
     */
    public function matches(array $criteria): bool
    {
        // Must match company
        if ($this->company_id !== $criteria['company_id']) {
            return false;
        }

        // Check type match (if type is specified in rule)
        if ($this->type && isset($criteria['type']) && $this->type !== $criteria['type']) {
            return false;
        }

        // Check category match (if category is specified in rule)
        if ($this->category_id && isset($criteria['category_id']) && $this->category_id !== $criteria['category_id']) {
            return false;
        }

        // Check department match (if department is specified in rule)
        if ($this->department_id && isset($criteria['department_id']) && $this->department_id !== $criteria['department_id']) {
            return false;
        }

        // Check branch match (if branch is specified in rule)
        if ($this->branch_id && isset($criteria['branch_id']) && $this->branch_id !== $criteria['branch_id']) {
            return false;
        }

        return true;
    }

    /**
     * Get recipient IDs from the JSON field
     */
    public function getRecipientIds(): array
    {
        return $this->recipients_json ?? [];
    }

    /**
     * Add recipient ID to the routing rule
     */
    public function addRecipient(string $recipientId): void
    {
        $recipients = $this->getRecipientIds();
        if (!in_array($recipientId, $recipients)) {
            $recipients[] = $recipientId;
            $this->recipients_json = $recipients;
            $this->save();
        }
    }

    /**
     * Remove recipient ID from the routing rule
     */
    public function removeRecipient(string $recipientId): void
    {
        $recipients = $this->getRecipientIds();
        $recipients = array_filter($recipients, fn($id) => $id !== $recipientId);
        $this->recipients_json = array_values($recipients);
        $this->save();
    }
}

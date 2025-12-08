<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseInvolvedParty extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'case_id',
        'employee_id',
        'nature_of_involvement',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // No casting needed for simplified structure
    ];

    /**
     * Get the case that this involved party belongs to.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    /**
     * Get the user associated with this involved party.
     * Note: employee_id column actually stores user.id (primary key)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'id');
    }

    /**
     * Scope a query to filter by case.
     */
    public function scopeForCase($query, $caseId)
    {
        return $query->where('case_id', $caseId);
    }

    /**
     * Scope a query to filter by involvement type.
     */
    public function scopeByInvolvementType($query, $type)
    {
        return $query->where('involvement_type', $type);
    }

    /**
     * Scope a query to get witnesses only.
     */
    public function scopeWitnesses($query)
    {
        return $query->where('involvement_type', 'witness');
    }

    /**
     * Scope a query to get perpetrators only.
     */
    public function scopePerpetrators($query)
    {
        return $query->where('involvement_type', 'perpetrator');
    }

    /**
     * Scope a query to get complainants only.
     */
    public function scopeComplainants($query)
    {
        return $query->where('involvement_type', 'complainant');
    }

    /**
     * Scope a query to get victims only.
     */
    public function scopeVictims($query)
    {
        return $query->where('involvement_type', 'victim');
    }

    /**
     * Scope a query to filter by registered users.
     */
    public function scopeRegisteredUsers($query)
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * Scope a query to filter by external parties.
     */
    public function scopeExternalParties($query)
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope a query to filter by anonymous parties.
     */
    public function scopeAnonymous($query)
    {
        return $query->where('is_anonymous', true);
    }

    /**
     * Get the display name for this involved party.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->is_anonymous) {
            return 'Anonymous ' . ucfirst($this->involvement_type);
        }

        if ($this->user) {
            return $this->user->name;
        }

        return $this->name ?: 'Unnamed ' . ucfirst($this->involvement_type);
    }

    /**
     * Get the contact email for this involved party.
     */
    public function getContactEmailAttribute(): ?string
    {
        if ($this->is_anonymous) {
            return null;
        }

        if ($this->user) {
            return $this->user->email;
        }

        return $this->email;
    }

    /**
     * Get the contact phone for this involved party.
     */
    public function getContactPhoneAttribute(): ?string
    {
        if ($this->is_anonymous) {
            return null;
        }

        return $this->phone;
    }

    /**
     * Check if this party is a registered user.
     */
    public function isRegisteredUser(): bool
    {
        return !is_null($this->user_id);
    }

    /**
     * Check if this party is external (not a registered user).
     */
    public function isExternal(): bool
    {
        return is_null($this->user_id);
    }

    /**
     * Check if this party is anonymous.
     */
    public function isAnonymous(): bool
    {
        return $this->is_anonymous;
    }

    /**
     * Get involvement type display name.
     */
    public function getInvolvementTypeDisplayAttribute(): string
    {
        return match ($this->involvement_type) {
            'witness' => 'Witness',
            'perpetrator' => 'Perpetrator',
            'complainant' => 'Complainant',
            'victim' => 'Victim',
            'reporter' => 'Reporter',
            'other' => 'Other',
            default => ucfirst($this->involvement_type),
        };
    }
}

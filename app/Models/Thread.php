<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thread extends Model
{
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'case_id',
        'title',
        'description',
        'status',
        'created_by',
        'created_by_type',
        'investigator_id',
        'note',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the case that owns the thread.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    /**
     * Get all participants for this thread.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ThreadParticipant::class, 'thread_id');
    }

    /**
     * Get the thread participant.
     */
    public function participant(): HasOne
    {
        return $this->hasOne(ThreadParticipant::class, 'thread_id');
    }

    /**
     * Get all messages for this thread.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CaseMessage::class, 'thread_id');
    }

    /**
     * Get the user who created this thread.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the investigator assigned to this thread.
     */
    public function investigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigator_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseFile extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'case_id',
        'case_message_id',
        'original_name',
        'stored_name',
        'file_path',
        'mime_type',
        'file_size',
        'file_type',
        'description',
        'uploaded_by_type',
        'uploaded_by_id',
        'is_confidential',
        'processing_status',
        'metadata'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_confidential' => 'boolean',
        'metadata' => 'array',
        'file_size' => 'integer'
    ];

    /**
     * Get the case message that owns the file.
     */
    public function caseMessage(): BelongsTo
    {
        return $this->belongsTo(CaseMessage::class, 'case_message_id');
    }

    /**
     * Get the case that owns the file.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    /**
     * Get the uploader of the file (polymorphic relationship).
     */
    public function uploader()
    {
        return $this->morphTo('uploaded_by', 'uploaded_by_type', 'uploaded_by_id');
    }

    /**
     * Scope for files by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('file_type', $type);
    }

    /**
     * Scope for confidential files.
     */
    public function scopeConfidential($query)
    {
        return $query->where('is_confidential', true);
    }

    /**
     * Scope for files by processing status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('processing_status', $status);
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if file is an image.
     */
    public function getIsImageAttribute()
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if file is a video.
     */
    public function getIsVideoAttribute()
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Check if file is an audio.
     */
    public function getIsAudioAttribute()
    {
        return str_starts_with($this->mime_type, 'audio/');
    }
}

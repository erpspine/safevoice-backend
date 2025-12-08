<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Attachment extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'case_id',
        'message_id',
        'file_url',
        'file_path',
        'file_name',
        'original_name',
        'file_hash',
        'mime_type',
        'file_size',
        'visibility',
        'uploaded_by',
        'description',
        'is_image',
        'is_document',
        'scan_status',
        'scan_result',
        'download_count',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'file_size' => 'integer',
        'is_image' => 'boolean',
        'is_document' => 'boolean',
        'download_count' => 'integer',
        'metadata' => 'array',
        'scan_result' => 'array',
    ];

    /**
     * Get the case that this attachment belongs to.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    /**
     * Get the message that this attachment belongs to (optional).
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(CaseMessage::class, 'message_id');
    }

    /**
     * Get the user who uploaded this attachment.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope a query to filter by case.
     */
    public function scopeForCase($query, $caseId)
    {
        return $query->where('case_id', $caseId);
    }

    /**
     * Scope a query to filter by visibility.
     */
    public function scopeByVisibility($query, $visibility)
    {
        return $query->where('visibility', $visibility);
    }

    /**
     * Scope a query to get public attachments only.
     */
    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    /**
     * Scope a query to get internal attachments only.
     */
    public function scopeInternal($query)
    {
        return $query->where('visibility', 'internal');
    }

    /**
     * Scope a query to get image attachments.
     */
    public function scopeImages($query)
    {
        return $query->where('is_image', true);
    }

    /**
     * Scope a query to get document attachments.
     */
    public function scopeDocuments($query)
    {
        return $query->where('is_document', true);
    }

    /**
     * Scope a query to filter by MIME type.
     */
    public function scopeByMimeType($query, $mimeType)
    {
        return $query->where('mime_type', $mimeType);
    }

    /**
     * Scope a query to filter by uploader.
     */
    public function scopeUploadedBy($query, $userId)
    {
        return $query->where('uploaded_by', $userId);
    }

    /**
     * Scope a query to get attachments with scan status.
     */
    public function scopeByScanStatus($query, $status)
    {
        return $query->where('scan_status', $status);
    }

    /**
     * Scope a query to get clean (safe) attachments.
     */
    public function scopeClean($query)
    {
        return $query->where('scan_status', 'clean');
    }

    /**
     * Scope a query to get pending scan attachments.
     */
    public function scopePendingScan($query)
    {
        return $query->where('scan_status', 'pending');
    }

    /**
     * Check if this attachment is public.
     */
    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    /**
     * Check if this attachment is internal.
     */
    public function isInternal(): bool
    {
        return $this->visibility === 'internal';
    }

    /**
     * Check if this attachment is an image.
     */
    public function isImage(): bool
    {
        return $this->is_image;
    }

    /**
     * Check if this attachment is a document.
     */
    public function isDocument(): bool
    {
        return $this->is_document;
    }

    /**
     * Check if the file exists in storage.
     */
    public function fileExists(): bool
    {
        return $this->file_path && Storage::exists($this->file_path);
    }

    /**
     * Get the file size in a human readable format.
     */
    public function getHumanFileSizeAttribute(): string
    {
        $size = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Get the file extension from filename.
     */
    public function getFileExtensionAttribute(): string
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    /**
     * Get the display name for this attachment.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->original_name ?: $this->file_name;
    }

    /**
     * Check if the attachment is safe (passed virus scan).
     */
    public function isSafe(): bool
    {
        return $this->scan_status === 'clean';
    }

    /**
     * Check if the attachment is being scanned.
     */
    public function isScanning(): bool
    {
        return $this->scan_status === 'pending';
    }

    /**
     * Check if the attachment failed security scan.
     */
    public function isUnsafe(): bool
    {
        return $this->scan_status === 'infected';
    }

    /**
     * Increment download counter.
     */
    public function incrementDownload(): bool
    {
        return $this->increment('download_count');
    }

    /**
     * Get download URL if file is accessible.
     */
    public function getDownloadUrlAttribute(): ?string
    {
        if (!$this->fileExists() || !$this->isSafe()) {
            return null;
        }

        return route('attachments.download', $this->id);
    }

    /**
     * Get thumbnail URL for images.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->isImage() || !$this->isSafe()) {
            return null;
        }

        return route('attachments.thumbnail', $this->id);
    }

    /**
     * Determine file type category from MIME type.
     */
    public function getFileTypeCategoryAttribute(): string
    {
        $mimeType = $this->mime_type;

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        if (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ])) {
            return 'document';
        }

        if (str_starts_with($mimeType, 'text/')) {
            return 'text';
        }

        return 'other';
    }

    /**
     * Get icon name for file type.
     */
    public function getFileIconAttribute(): string
    {
        return match ($this->file_type_category) {
            'image' => 'image',
            'video' => 'video',
            'audio' => 'audio',
            'document' => 'file-text',
            'text' => 'file-text',
            default => 'file',
        };
    }

    /**
     * Check if file type is allowed.
     */
    public static function isAllowedMimeType(string $mimeType): bool
    {
        $allowedTypes = [
            // Images
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // Text
            'text/plain',
            'text/csv',
            // Archives
            'application/zip',
            'application/x-rar-compressed',
        ];

        return in_array($mimeType, $allowedTypes);
    }

    /**
     * Get maximum allowed file size in bytes.
     */
    public static function getMaxFileSize(): int
    {
        return 50 * 1024 * 1024; // 50MB
    }
}

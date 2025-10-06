<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessagingAttachment extends Model
{
    protected $table = 'messaging_attachments';

    protected $fillable = [
        'message_id',
        'attachment_id',
        'name',
        'mime_type',
        'extension',
        'size',
        'is_inline',
        'hash',
        'url',
        'storage_path',
        'base64_data',
        'scanned',
        'scan_results',
        'is_safe',
    ];

    protected $casts = [
        'size' => 'integer',
        'is_inline' => 'boolean',
        'scanned' => 'boolean',
        'is_safe' => 'boolean',
        'scan_results' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    public function message(): BelongsTo
    {
        return $this->belongsTo(MessagingMessage::class, 'message_id');
    }

    // ==================== SCOPES ====================

    public function scopeScanned($query)
    {
        return $query->where('scanned', true);
    }

    public function scopeUnscanned($query)
    {
        return $query->where('scanned', false);
    }

    public function scopeSafe($query)
    {
        return $query->where('is_safe', true);
    }

    public function scopeUnsafe($query)
    {
        return $query->where('is_safe', false);
    }

    public function scopeInline($query)
    {
        return $query->where('is_inline', true);
    }

    public function scopeNotInline($query)
    {
        return $query->where('is_inline', false);
    }

    public function scopeByType($query, string $mimeType)
    {
        return $query->where('mime_type', 'like', $mimeType . '%');
    }

    // ==================== HELPER METHODS ====================

    public function getSizeInKB(): float
    {
        return round($this->size / 1024, 2);
    }

    public function getSizeInMB(): float
    {
        return round($this->size / (1024 * 1024), 2);
    }

    public function getFormattedSize(): string
    {
        if ($this->size < 1024) {
            return $this->size . ' B';
        } elseif ($this->size < 1024 * 1024) {
            return $this->getSizeInKB() . ' KB';
        } else {
            return $this->getSizeInMB() . ' MB';
        }
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPDF(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isDocument(): bool
    {
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
        ];

        return in_array($this->mime_type, $documentTypes);
    }

    public function markAsScanned(array $results): void
    {
        $this->update([
            'scanned' => true,
            'scan_results' => $results,
            'is_safe' => $results['is_safe'] ?? true,
        ]);
    }

    public function markAsUnsafe(string $reason): void
    {
        $this->update([
            'scanned' => true,
            'is_safe' => false,
            'scan_results' => [
                'threat_detected' => true,
                'reason' => $reason,
                'scanned_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function getDownloadUrl(): ?string
    {
        return $this->url ?? ($this->storage_path ? url('storage/' . $this->storage_path) : null);
    }
}

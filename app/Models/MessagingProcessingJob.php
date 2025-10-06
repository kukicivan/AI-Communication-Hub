<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessagingProcessingJob extends Model
{
    protected $table = 'messaging_processing_jobs';

    protected $fillable = [
        'message_id',
        'thread_id',
        'job_type',
        'payload',
        'status',
        'error_message',
        'attempts',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    public function message(): BelongsTo
    {
        return $this->belongsTo(MessagingMessage::class, 'message_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }

    // ==================== SCOPES ====================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('job_type', $type);
    }

    // ==================== HELPER METHODS ====================

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'processed_at' => now(),
        ]);
    }

    public function retry(): void
    {
        if ($this->attempts >= 3) {
            $this->markAsFailed('Maximum retry attempts reached');
            return;
        }

        $this->update([
            'status' => 'pending',
            'error_message' => null,
        ]);
    }

    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->attempts < 3;
    }

    public function isStale(int $minutes = 30): bool
    {
        return $this->status === 'processing'
            && $this->updated_at->diffInMinutes(now()) > $minutes;
    }
}

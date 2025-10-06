<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessagingSyncLog extends Model
{
    protected $table = 'messaging_sync_logs';

    protected $fillable = [
        'channel_id',
        'started_at',
        'completed_at',
        'messages_fetched',
        'messages_processed',
        'messages_failed',
        'status',
        'summary',
        'errors',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'errors' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    public function channel(): BelongsTo
    {
        return $this->belongsTo(MessagingChannel::class, 'channel_id');
    }

    // ==================== SCOPES ====================

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('started_at', '>=', now()->subHours($hours));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('started_at', today());
    }

    // ==================== HELPER METHODS ====================

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(array $errors): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'errors' => $errors,
        ]);
    }

    public function getDuration(): ?int
    {
        if (!$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }

    public function getFormattedDuration(): ?string
    {
        $duration = $this->getDuration();

        if ($duration === null) {
            return null;
        }

        if ($duration < 60) {
            return $duration . 's';
        } elseif ($duration < 3600) {
            return round($duration / 60, 1) . 'm';
        } else {
            return round($duration / 3600, 1) . 'h';
        }
    }

    public function getSuccessRate(): float
    {
        if ($this->messages_fetched === 0) {
            return 0;
        }

        return round(($this->messages_processed / $this->messages_fetched) * 100, 2);
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors) || $this->messages_failed > 0;
    }

    public function incrementFetched(int $count = 1): void
    {
        $this->increment('messages_fetched', $count);
    }

    public function incrementProcessed(int $count = 1): void
    {
        $this->increment('messages_processed', $count);
    }

    public function incrementFailed(int $count = 1): void
    {
        $this->increment('messages_failed', $count);
    }

    public function addError(string $error): void
    {
        $errors = $this->errors ?? [];
        $errors[] = [
            'error' => $error,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->update(['errors' => $errors]);
    }
}

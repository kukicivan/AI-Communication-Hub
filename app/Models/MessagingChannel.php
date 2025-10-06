<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class MessagingChannel extends Model
{
    protected $table = 'messaging_channels';

    protected $fillable = [
        'channel_type',
        'channel_id',
        'name',
        'configuration',
        'is_active',
        'last_sync_at',
        'history_id',
        'last_history_sync_at',
        'health_status',
    ];

    protected $casts = [
        'configuration' => 'array',
        'health_status' => 'array',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
        'last_history_sync_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    public function threads(): HasMany
    {
        return $this->hasMany(MessageThread::class, 'channel_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessagingMessage::class, 'channel_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(MessagingSyncLog::class, 'channel_id');
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('channel_type', $type);
    }

    // ==================== HELPER METHODS ====================

    public function getLastSyncTime(): ?Carbon
    {
        return $this->last_sync_at;
    }

    public function updateLastSync(): void
    {
        $this->update(['last_sync_at' => now()]);
    }

    public function isHealthy(): bool
    {
        return ($this->health_status['status'] ?? 'unknown') === 'healthy';
    }

    public function markAsHealthy(): void
    {
        $this->update([
            'health_status' => [
                'status' => 'healthy',
                'checked_at' => now()->toIso8601String(),
            ]
        ]);
    }

    public function markAsUnhealthy(string $error): void
    {
        $this->update([
            'health_status' => [
                'status' => 'unhealthy',
                'error' => $error,
                'checked_at' => now()->toIso8601String(),
            ]
        ]);
    }
}

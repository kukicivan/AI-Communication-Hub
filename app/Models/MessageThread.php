<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MessageThread extends Model
{
    protected $table = 'message_threads';

    protected $fillable = [
        'channel_id',
        'thread_id',
        'subject',
        'participants',
        'last_message_at',
        'message_count',
        'is_unread',
        'has_starred_messages',
        'is_important',
        'is_in_inbox',
        'is_in_chats',
        'is_in_spam',
        'is_in_trash',
        'is_in_priority_inbox',
        'permalink',
        'labels',
        'ai_analysis',
        'ai_status',
        'ai_processed_at',
    ];

    protected $casts = [
        'participants' => 'array',
        'labels' => 'array',
        'ai_analysis' => 'array',
        'last_message_at' => 'datetime',
        'ai_processed_at' => 'datetime',
        'is_unread' => 'boolean',
        'has_starred_messages' => 'boolean',
        'is_important' => 'boolean',
        'is_in_inbox' => 'boolean',
        'is_in_chats' => 'boolean',
        'is_in_spam' => 'boolean',
        'is_in_trash' => 'boolean',
        'is_in_priority_inbox' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    public function channel(): BelongsTo
    {
        return $this->belongsTo(MessagingChannel::class, 'channel_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessagingMessage::class, 'thread_id', 'thread_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(MessagingLabel::class, 'thread_label', 'thread_id', 'label_id')
            ->withTimestamps();
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(MessagingProcessingJob::class, 'thread_id');
    }

    // ==================== SCOPES ====================

    public function scopeUnread($query)
    {
        return $query->where('is_unread', true);
    }

    public function scopeImportant($query)
    {
        return $query->where('is_important', true);
    }

    public function scopeStarred($query)
    {
        return $query->where('has_starred_messages', true);
    }

    public function scopeInInbox($query)
    {
        return $query->where('is_in_inbox', true);
    }

    public function scopeNotInTrash($query)
    {
        return $query->where('is_in_trash', false);
    }

    public function scopeNotSpam($query)
    {
        return $query->where('is_in_spam', false);
    }

    public function scopeNeedAiProcessing($query)
    {
        return $query->where('ai_status', 'pending');
    }

    public function scopeAiProcessed($query)
    {
        return $query->where('ai_status', 'completed');
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('last_message_at', '>=', now()->subDays($days));
    }

    // ==================== HELPER METHODS ====================

    public function hasNewMessages(): bool
    {
        return $this->is_unread || $this->messages()->where('is_unread', true)->exists();
    }

    public function getUnreadCount(): int
    {
        return $this->messages()->where('is_unread', true)->count();
    }

    public function markAsRead(): void
    {
        $this->update(['is_unread' => false]);
        $this->messages()->update(['is_unread' => false]);
    }

    public function markAsUnread(): void
    {
        $this->update(['is_unread' => true]);
    }

    public function markAsImportant(): void
    {
        $this->update(['is_important' => true]);
    }

    public function markAsUnimportant(): void
    {
        $this->update(['is_important' => false]);
    }

    public function moveToTrash(): void
    {
        $this->update([
            'is_in_trash' => true,
            'is_in_inbox' => false,
        ]);
    }

    public function moveToInbox(): void
    {
        $this->update([
            'is_in_trash' => false,
            'is_in_inbox' => true,
        ]);
    }

    public function markAsProcessed(array $aiAnalysis): void
    {
        $this->update([
            'ai_analysis' => $aiAnalysis,
            'ai_status' => 'completed',
            'ai_processed_at' => now(),
        ]);
    }

    public function markAiProcessingStarted(): void
    {
        $this->update(['ai_status' => 'processing']);
    }

    public function markAiProcessingFailed(string $error): void
    {
        $this->update([
            'ai_status' => 'failed',
            'ai_analysis' => [
                'error' => $error,
                'failed_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function getParticipantEmails(): array
    {
        return array_column($this->participants, 'email');
    }

    public function getParticipantNames(): array
    {
        return array_column($this->participants, 'name');
    }

    public function hasLabel(string $labelName): bool
    {
        if ($this->labels && is_array($this->labels)) {
            return collect($this->labels)->contains('name', $labelName);
        }
        return $this->labels()->where('name', $labelName)->exists();
    }

    public function getAiSentiment(): ?string
    {
        return $this->ai_analysis['sentiment']['tone'] ?? null;
    }

    public function getAiCategory(): ?string
    {
        return $this->ai_analysis['classification']['category'] ?? null;
    }

    public function getAiSummary(): ?string
    {
        return $this->ai_analysis['summary'] ?? null;
    }

    public function getAiActionItems(): array
    {
        return $this->ai_analysis['actionItems'] ?? [];
    }
}

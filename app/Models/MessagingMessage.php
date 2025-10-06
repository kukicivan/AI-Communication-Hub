<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MessagingMessage extends Model
{
    protected $table = 'messaging_messages';

    protected $fillable = [
        'message_id',
        'channel_id',
        'thread_id',
        'message_number',
        'parent_message_id',
        'message_timestamp',
        'received_date',
        'sender',
        'recipients',
        'content_text',
        'content_html',
        'content_snippet',
        'content_raw',
        'attachment_count',
        'reactions',
        'metadata',
        'is_draft',
        'is_unread',
        'is_starred',
        'is_in_trash',
        'is_in_inbox',
        'is_in_chats',
        'is_spam',
        'priority',
        'status',
        'ai_analysis',
        'ai_status',
        'ai_processed_at',
        'synced_at',
    ];

    protected $casts = [
        'sender' => 'array',
        'recipients' => 'array',
        'reactions' => 'array',
        'metadata' => 'array',
        'ai_analysis' => 'array',
        'message_timestamp' => 'datetime',
        'received_date' => 'datetime',
        'ai_processed_at' => 'datetime',
        'synced_at' => 'datetime',
        'is_draft' => 'boolean',
        'is_unread' => 'boolean',
        'is_starred' => 'boolean',
        'is_in_trash' => 'boolean',
        'is_in_inbox' => 'boolean',
        'is_in_chats' => 'boolean',
        'is_spam' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    public function channel(): BelongsTo
    {
        return $this->belongsTo(MessagingChannel::class, 'channel_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'thread_id', 'thread_id');
    }

    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(MessagingMessage::class, 'parent_message_id', 'message_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(MessagingMessage::class, 'parent_message_id', 'message_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessagingAttachment::class, 'message_id');
    }

    public function headers(): HasOne
    {
        return $this->hasOne(MessagingHeader::class, 'message_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(MessagingLabel::class, 'message_label', 'message_id', 'label_id')
            ->withTimestamps();
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(MessagingProcessingJob::class, 'message_id');
    }

    // ==================== SCOPES ====================

    public function scopeUnread($query)
    {
        return $query->where('is_unread', true);
    }

    public function scopeStarred($query)
    {
        return $query->where('is_starred', true);
    }

    public function scopeImportant($query)
    {
        return $query->where('priority', 'high');
    }

    public function scopeInInbox($query)
    {
        return $query->where('is_in_inbox', true);
    }

    public function scopeNotDraft($query)
    {
        return $query->where('is_draft', false);
    }

    public function scopeNotTrash($query)
    {
        return $query->where('is_in_trash', false);
    }

    public function scopeNotSpam($query)
    {
        return $query->where('is_spam', false);
    }

    public function scopeByThread($query, string $threadId)
    {
        return $query->where('thread_id', $threadId);
    }

    public function scopeOrderByThread($query)
    {
        return $query->orderBy('thread_id')->orderBy('message_number');
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('message_timestamp', '>=', now()->subDays($days));
    }

    public function scopeHasAttachments($query)
    {
        return $query->where('attachment_count', '>', 0);
    }

    public function scopeNeedAiProcessing($query)
    {
        return $query->where('ai_status', 'pending')
            ->where('is_draft', false)
            ->where('is_spam', false);
    }

    // ==================== HELPER METHODS ====================

    public function getSenderEmail(): string
    {
        return $this->sender['email'] ?? '';
    }

    public function getSenderName(): string
    {
        return $this->sender['name'] ?? $this->getSenderEmail();
    }

    public function getRecipientEmails(): array
    {
        $emails = [];
        foreach (['to', 'cc', 'bcc'] as $type) {
            if (isset($this->recipients[$type])) {
                $emails = array_merge($emails, array_column($this->recipients[$type], 'email'));
            }
        }
        return $emails;
    }

    public function getToEmails(): array
    {
        return array_column($this->recipients['to'] ?? [], 'email');
    }

    public function getCcEmails(): array
    {
        return array_column($this->recipients['cc'] ?? [], 'email');
    }

    public function getBccEmails(): array
    {
        return array_column($this->recipients['bcc'] ?? [], 'email');
    }

    public function getSubject(): string
    {
        return $this->metadata['subject'] ?? '(No Subject)';
    }

    public function hasAttachments(): bool
    {
        return $this->attachment_count > 0;
    }

    public function isReply(): bool
    {
        return !empty($this->parent_message_id);
    }

    public function isHighPriority(): bool
    {
        return $this->priority === 'high';
    }

    public function markAsRead(): void
    {
        $this->update(['is_unread' => false]);
    }

    public function markAsUnread(): void
    {
        $this->update(['is_unread' => true]);
    }

    public function star(): void
    {
        $this->update(['is_starred' => true]);
    }

    public function unstar(): void
    {
        $this->update(['is_starred' => false]);
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

    public function markAsProcessed(): void
    {
        $this->update(['status' => 'processed']);
    }

    public function markAsArchived(): void
    {
        $this->update(['status' => 'archived']);
    }

    public function markAsError(string $error): void
    {
        $this->update([
            'status' => 'error',
            'ai_analysis' => [
                'error' => $error,
                'failed_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function updateAiAnalysis(array $analysis): void
    {
        $this->update([
            'ai_analysis' => $analysis,
            'ai_status' => 'completed',
            'ai_processed_at' => now(),
            'status' => 'processed',
        ]);
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

    public function getSnippet(int $length = 200): string
    {
        return $this->content_snippet ?? substr($this->content_text, 0, $length);
    }

    public function searchContent(string $query): bool
    {
        return str_contains(strtolower($this->content_text), strtolower($query));
    }
}

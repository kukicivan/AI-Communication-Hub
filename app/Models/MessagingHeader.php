<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessagingHeader extends Model
{
    protected $table = 'messaging_headers';

    protected $fillable = [
        'message_id',
        'message_id_header',
        'in_reply_to',
        'references',
        'list_unsubscribe',
        'return_path',
        'delivered_to',
        'received_spf',
        'authentication_results',
        'dkim_signature',
        'x_mailer',
        'x_priority',
        'importance',
        'x_spam_score',
        'x_spam_status',
        'custom_headers',
    ];

    protected $casts = [
        'references' => 'array',
        'custom_headers' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    public function message(): BelongsTo
    {
        return $this->belongsTo(MessagingMessage::class, 'message_id');
    }

    // ==================== HELPER METHODS ====================

    public function getMessageIdFromHeader(): ?string
    {
        return $this->message_id_header;
    }

    public function getParentMessageId(): ?string
    {
        return $this->in_reply_to;
    }

    public function getReferenceChain(): array
    {
        return $this->references ?? [];
    }

    public function hasUnsubscribeLink(): bool
    {
        return !empty($this->list_unsubscribe);
    }

    public function getUnsubscribeLink(): ?string
    {
        if (!$this->hasUnsubscribeLink()) {
            return null;
        }

        // Parse list-unsubscribe header (can be URL or email)
        preg_match('/<(.+?)>/', $this->list_unsubscribe, $matches);
        return $matches[1] ?? null;
    }

    public function isPriorityHigh(): bool
    {
        return $this->x_priority === '1'
            || $this->x_priority === '2'
            || strtolower($this->importance ?? '') === 'high';
    }

    public function isPriorityLow(): bool
    {
        return $this->x_priority === '4'
            || $this->x_priority === '5'
            || strtolower($this->importance ?? '') === 'low';
    }

    public function getSpamScore(): ?float
    {
        if (empty($this->x_spam_score)) {
            return null;
        }

        // Parse score (format varies: "5.2", "score=5.2", etc.)
        preg_match('/[\d.]+/', $this->x_spam_score, $matches);
        return isset($matches[0]) ? (float)$matches[0] : null;
    }

    public function isLikelySpam(): bool
    {
        $score = $this->getSpamScore();
        return $score && $score > 5.0;
    }

    public function hasPassedSpf(): bool
    {
        return str_contains(strtolower($this->received_spf ?? ''), 'pass');
    }

    public function hasPassedDkim(): bool
    {
        return !empty($this->dkim_signature);
    }

    public function isAuthenticated(): bool
    {
        return $this->hasPassedSpf() && $this->hasPassedDkim();
    }

    public function getMailerClient(): ?string
    {
        return $this->x_mailer;
    }

    public function getCustomHeader(string $headerName): ?string
    {
        return $this->custom_headers[$headerName] ?? null;
    }
}

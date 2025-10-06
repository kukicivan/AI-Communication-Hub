<?php

namespace App\Services\Messaging\DTOs;

use Carbon\Carbon;

/**
 * Message DTO - Unified message struktura za sve kanale
 *
 * Predstavlja standardizovanu poruku nezavisno od izvora
 * (Gmail, Slack, Teams, Discord, itd.)
 */
class Message
{
    /**
     * Jedinstveni ID poruke (iz source sistema)
     */
    public string $id;

    /**
     * Timestamp kada je poruka kreirana/poslata
     */
    public Carbon $timestamp;

    /**
     * Channel ID iz kog poruka dolazi (npr. "gmail-primary", "slack-general")
     */
    public string $channel;

    /**
     * Sub-channel ili thread ID (opciono)
     */
    public ?string $subChannel = null;

    /**
     * Pošiljalac poruke
     */
    public Participant $sender;

    /**
     * Lista primalaca
     *
     * @var Participant[]
     */
    public array $recipients = [];

    /**
     * Sadržaj poruke (text, HTML, attachments)
     */
    public MessageContent $content;

    /**
     * Metadata o poruci (subject, priority, labels, itd.)
     */
    public array $metadata = [];

    /**
     * Thread ID za grupisanje poruka u konverzacije
     */
    public ?string $threadId = null;

    /**
     * Parent message ID (za reply-ove)
     */
    public ?string $parentMessageId = null;

    /**
     * Kreiranje Message instance sa validacijom
     *
     * @param array $data
     * @return self
     */
    public static function create(array $data): self
    {
        $message = new self();

        $message->id = $data['id'] ?? throw new \InvalidArgumentException('Message ID is required');
        $message->timestamp = $data['timestamp'] instanceof Carbon
            ? $data['timestamp']
            : Carbon::parse($data['timestamp'] ?? now());

        $message->channel = $data['channel'] ?? throw new \InvalidArgumentException('Channel is required');
        $message->subChannel = $data['subChannel'] ?? $data['sub_channel'] ?? null;

        $message->sender = $data['sender'] instanceof Participant
            ? $data['sender']
            : Participant::create($data['sender'] ?? []);

        $message->recipients = array_map(
            fn($r) => $r instanceof Participant ? $r : Participant::create($r),
            $data['recipients'] ?? []
        );

        $message->content = $data['content'] instanceof MessageContent
            ? $data['content']
            : MessageContent::create($data['content'] ?? []);

        $message->metadata = $data['metadata'] ?? [];
        $message->threadId = $data['threadId'] ?? $data['thread_id'] ?? null;
        $message->parentMessageId = $data['parentMessageId'] ?? $data['parent_message_id'] ?? null;

        return $message;
    }

    /**
     * Konverzija u array (za database storage)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'timestamp' => $this->timestamp->toIso8601String(),
            'channel' => $this->channel,
            'sub_channel' => $this->subChannel,
            'sender' => $this->sender->toArray(),
            'recipients' => array_map(fn($r) => $r->toArray(), $this->recipients),
            'content' => $this->content->toArray(),
            'metadata' => $this->metadata,
            'thread_id' => $this->threadId,
            'parent_message_id' => $this->parentMessageId,
        ];
    }

    /**
     * Konverzija u JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Kreiranje instance iz array-a
     */
    public static function fromArray(array $data): self
    {
        return self::create($data);
    }

    /**
     * Kreiranje instance iz JSON-a
     */
    public static function fromJson(string $json): self
    {
        return self::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Da li poruka ima subject (email-like poruke)
     */
    public function hasSubject(): bool
    {
        return !empty($this->metadata['subject']);
    }

    /**
     * Dohvatanje subject-a
     */
    public function getSubject(): ?string
    {
        return $this->metadata['subject'] ?? null;
    }

    /**
     * Da li je poruka pročitana
     */
    public function isRead(): bool
    {
        return $this->metadata['read'] ?? false;
    }

    /**
     * Da li je poruka označena (starred/important)
     */
    public function isStarred(): bool
    {
        return $this->metadata['starred'] ?? false;
    }

    /**
     * Prioritet poruke
     */
    public function getPriority(): string
    {
        return $this->metadata['priority'] ?? 'normal';
    }

    /**
     * Da li je poruka urgent
     */
    public function isUrgent(): bool
    {
        return $this->getPriority() === 'urgent';
    }

    /**
     * Labele/tagovi poruke
     */
    public function getLabels(): array
    {
        return $this->metadata['labels'] ?? [];
    }

    /**
     * Da li poruka ima attachment-e
     */
    public function hasAttachments(): bool
    {
        return !empty($this->content->attachments);
    }

    /**
     * Broj attachment-a
     */
    public function getAttachmentCount(): int
    {
        return count($this->content->attachments);
    }

    /**
     * Plain text preview (prvih 200 karaktera)
     */
    public function getPreview(int $length = 200): string
    {
        $text = $this->content->text;
        return mb_strlen($text) > $length
            ? mb_substr($text, 0, $length) . '...'
            : $text;
    }

    /**
     * Da li poruka pripada thread-u
     */
    public function isThreaded(): bool
    {
        return !empty($this->threadId);
    }

    /**
     * Da li je ovo reply (ima parent message)
     */
    public function isReply(): bool
    {
        return !empty($this->parentMessageId);
    }

    /**
     * Magic method za JSON serialization
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Magic method za string conversion
     */
    public function __toString(): string
    {
        $subject = $this->getSubject();
        return $subject
            ? "{$this->sender->name}: {$subject}"
            : "{$this->sender->name}: {$this->getPreview(50)}";
    }
}

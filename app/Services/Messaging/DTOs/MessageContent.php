<?php

namespace App\Services\Messaging\DTOs;

/**
 * MessageContent DTO - Sadržaj poruke
 *
 * Enkapsulira različite tipove sadržaja: text, HTML, attachments, reactions
 */
class MessageContent
{
    /**
     * Plain text verzija sadržaja
     */
    public string $text = '';

    /**
     * HTML verzija sadržaja (opciono)
     */
    public ?string $html = null;

    /**
     * Lista attachment-a
     *
     * @var Attachment[]
     */
    public array $attachments = [];

    /**
     * Reactions na poruku (emoji + count)
     *
     * Format: ['👍' => ['count' => 5, 'users' => ['user1', 'user2']], ...]
     */
    public array $reactions = [];

    /**
     * Formatiranje (bold, italic, mentions, links)
     */
    public array $formatting = [];

    /**
     * Dodatni metadata o sadržaju
     */
    public array $metadata = [];

    /**
     * Kreiranje MessageContent instance
     */
    public static function create(array $data): self
    {
        $content = new self();

        $content->text = $data['text'] ?? '';
        $content->html = $data['html'] ?? null;

        $content->attachments = array_map(
            fn($a) => $a instanceof Attachment ? $a : Attachment::create($a),
            $data['attachments'] ?? []
        );

        $content->reactions = $data['reactions'] ?? [];
        $content->formatting = $data['formatting'] ?? [];
        $content->metadata = $data['metadata'] ?? [];

        return $content;
    }

    /**
     * Konverzija u array
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'html' => $this->html,
            'attachments' => array_map(fn($a) => $a->toArray(), $this->attachments),
            'reactions' => $this->reactions,
            'formatting' => $this->formatting,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Da li sadržaj ima HTML
     */
    public function hasHtml(): bool
    {
        return !empty($this->html);
    }

    /**
     * Da li sadržaj ima attachment-e
     */
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    /**
     * Broj attachment-a
     */
    public function getAttachmentCount(): int
    {
        return count($this->attachments);
    }

    /**
     * Da li sadržaj ima reactions
     */
    public function hasReactions(): bool
    {
        return !empty($this->reactions);
    }

    /**
     * Ukupan broj reakcija
     */
    public function getTotalReactionCount(): int
    {
        return array_sum(array_column($this->reactions, 'count'));
    }

    /**
     * Dohvatanje attachment-a po ID-u
     */
    public function getAttachment(string $id): ?Attachment
    {
        foreach ($this->attachments as $attachment) {
            if ($attachment->id === $id) {
                return $attachment;
            }
        }
        return null;
    }

    /**
     * Attachment-i po tipu (images, documents, etc.)
     */
    public function getAttachmentsByType(string $type): array
    {
        return array_filter(
            $this->attachments,
            fn(Attachment $a) => str_starts_with($a->type, $type)
        );
    }

    /**
     * Samo image attachment-i
     */
    public function getImages(): array
    {
        return $this->getAttachmentsByType('image/');
    }

    /**
     * Samo document attachment-i
     */
    public function getDocuments(): array
    {
        return array_filter(
            $this->attachments,
            fn(Attachment $a) => in_array($a->type, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
        );
    }

    /**
     * Ukupna veličina svih attachment-a (u bytes)
     */
    public function getTotalAttachmentSize(): int
    {
        return array_sum(array_map(fn(Attachment $a) => $a->size, $this->attachments));
    }

    /**
     * Formatirana veličina attachment-a (human readable)
     */
    public function getFormattedAttachmentSize(): string
    {
        $bytes = $this->getTotalAttachmentSize();

        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Da li sadržaj sadrži mentions (@username)
     */
    public function hasMentions(): bool
    {
        return !empty($this->formatting['mentions']);
    }

    /**
     * Lista mentioned user-a
     */
    public function getMentions(): array
    {
        return $this->formatting['mentions'] ?? [];
    }

    /**
     * Da li sadržaj sadrži linkove
     */
    public function hasLinks(): bool
    {
        return !empty($this->formatting['links']);
    }

    /**
     * Lista linkova
     */
    public function getLinks(): array
    {
        return $this->formatting['links'] ?? [];
    }

    /**
     * Plain text preview (bez HTML tagova, ograničena dužina)
     */
    public function getPreview(int $length = 200): string
    {
        $text = $this->text;
        return mb_strlen($text) > $length
            ? mb_substr($text, 0, $length) . '...'
            : $text;
    }

    /**
     * Da li je sadržaj prazan
     */
    public function isEmpty(): bool
    {
        return empty($this->text) &&
            empty($this->html) &&
            empty($this->attachments);
    }

    /**
     * Word count
     */
    public function getWordCount(): int
    {
        return str_word_count($this->text);
    }

    /**
     * Character count
     */
    public function getCharacterCount(): int
    {
        return mb_strlen($this->text);
    }

    /**
     * JSON serialization
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
        return $this->getPreview(100);
    }
}

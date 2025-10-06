<?php

namespace App\Services\Messaging\DTOs;

/**
 * Attachment DTO - Prilog poruke (file, image, document)
 */
class Attachment
{
    public string $id;
    public string $name;
    public string $type; // MIME type
    public int $size; // bytes
    public ?string $url = null;
    public ?string $base64Data = null;
    public array $metadata = [];

    public static function create(array $data): self
    {
        $attachment = new self();

        $attachment->id = $data['id'] ?? uniqid('att_', true);
        $attachment->name = $data['name'] ?? 'unknown';
        $attachment->type = $data['type'] ?? 'application/octet-stream';
        $attachment->size = $data['size'] ?? 0;
        $attachment->url = $data['url'] ?? null;
        $attachment->base64Data = $data['base64Data'] ?? $data['base64_data'] ?? null;
        $attachment->metadata = $data['metadata'] ?? [];

        return $attachment;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'size' => $this->size,
            'url' => $this->url,
            'base64_data' => $this->base64Data,
            'metadata' => $this->metadata,
        ];
    }

    public function isImage(): bool
    {
        return str_starts_with($this->type, 'image/');
    }

    public function isDocument(): bool
    {
        return str_starts_with($this->type, 'application/');
    }

    public function getExtension(): string
    {
        return pathinfo($this->name, PATHINFO_EXTENSION);
    }

    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(($this->size ? log($this->size) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $size = $this->size / (1 << (10 * $pow));
        return round($size, 2) . ' ' . $units[$pow];
    }

    public function __toString(): string
    {
        return "{$this->name} ({$this->getFormattedSize()})";
    }
}

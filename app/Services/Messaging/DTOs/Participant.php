<?php

namespace App\Services\Messaging\DTOs;

/**
 * Participant DTO - Predstavlja učesnika u komunikaciji
 *
 * Može biti pošiljalac ili primalac poruke
 */
class Participant
{
    /**
     * Jedinstveni ID učesnika (obično email ili user ID)
     */
    public string $id;

    /**
     * Ime učesnika
     */
    public string $name;

    /**
     * Email adresa (opciono za non-email channels)
     */
    public ?string $email = null;

    /**
     * Avatar URL (opciono)
     */
    public ?string $avatar = null;

    /**
     * Rola učesnika (admin, moderator, member, itd.)
     */
    public ?string $role = null;

    /**
     * Dodatni metadata
     */
    public array $metadata = [];

    /**
     * Kreiranje Participant instance sa validacijom
     */
    public static function create(array $data): self
    {
        $participant = new self();

        $participant->id = $data['id'] ?? $data['email'] ?? throw new \InvalidArgumentException('Participant ID or email is required');
        $participant->name = $data['name'] ?? $participant->id;
        $participant->email = $data['email'] ?? null;
        $participant->avatar = $data['avatar'] ?? null;
        $participant->role = $data['role'] ?? null;
        $participant->metadata = $data['metadata'] ?? [];

        return $participant;
    }

    /**
     * Kreiranje iz email string-a (npr. "John Doe <john@example.com>")
     */
    public static function fromEmailString(string $emailStr): self
    {
        $participant = new self();

        // Match: "Name" <email@domain.com>
        if (preg_match('/^(.+?)\s*<(.+)>$/', $emailStr, $matches)) {
            $participant->name = trim($matches[1], '" ');
            $participant->email = trim($matches[2]);
            $participant->id = $participant->email;
        } else {
            // Just email without name
            $participant->email = trim($emailStr);
            $participant->name = $participant->email;
            $participant->id = $participant->email;
        }

        return $participant;
    }

    /**
     * Konverzija u array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'role' => $this->role,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Konverzija u email string format
     */
    public function toEmailString(): string
    {
        if ($this->email) {
            return $this->name !== $this->email
                ? "\"{$this->name}\" <{$this->email}>"
                : $this->email;
        }
        return $this->name;
    }

    /**
     * Da li učesnik ima email
     */
    public function hasEmail(): bool
    {
        return !empty($this->email);
    }

    /**
     * Da li je učesnik admin/moderator
     */
    public function isPrivileged(): bool
    {
        return in_array($this->role, ['admin', 'moderator', 'owner']);
    }

    /**
     * Display name sa fallback-om
     */
    public function getDisplayName(): string
    {
        return $this->name ?: $this->email ?: $this->id;
    }

    /**
     * Inicijali za avatar placeholder
     */
    public function getInitials(): string
    {
        $words = explode(' ', $this->name);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($this->name, 0, 2));
    }

    /**
     * Magic method za string conversion
     */
    public function __toString(): string
    {
        return $this->toEmailString();
    }

    /**
     * JSON serialization
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

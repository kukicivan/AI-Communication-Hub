<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MessagingLabel extends Model
{
    protected $table = 'messaging_labels';

    protected $fillable = [
        'label_id',
        'name',
        'type',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(MessagingMessage::class, 'message_label', 'label_id', 'message_id')
            ->withTimestamps();
    }

    public function threads(): BelongsToMany
    {
        return $this->belongsToMany(MessageThread::class, 'thread_label', 'label_id', 'thread_id')
            ->withTimestamps();
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem($query)
    {
        return $query->where('type', 'system');
    }

    public function scopeUser($query)
    {
        return $query->where('type', 'user');
    }

    // ==================== HELPER METHODS ====================

    public function isSystemLabel(): bool
    {
        return $this->type === 'system';
    }

    public function isUserLabel(): bool
    {
        return $this->type === 'user';
    }

    public function getMessageCount(): int
    {
        return $this->messages()->count();
    }

    public function getThreadCount(): int
    {
        return $this->threads()->count();
    }

    public function getUnreadMessageCount(): int
    {
        return $this->messages()->where('is_unread', true)->count();
    }

    public static function findOrCreateByName(string $name, string $type = 'user'): self
    {
        $labelId = strtoupper(str_replace(' ', '_', $name));

        return self::firstOrCreate(
            ['label_id' => $labelId],
            [
                'name' => $name,
                'type' => $type,
                'is_active' => true,
            ]
        );
    }

    public static function getSystemLabel(string $name): ?self
    {
        return self::where('type', 'system')
            ->where('name', $name)
            ->first();
    }
}

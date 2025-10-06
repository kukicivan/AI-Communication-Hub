<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChannelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->channel_type,
            'channel_id' => $this->channel_id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'last_sync_at' => $this->last_sync_at?->toIso8601String(),
            'health_status' => [
                'status' => $this->health_status['status'] ?? 'unknown',
                'checked_at' => $this->health_status['checked_at'] ?? null,
                'error' => $this->health_status['error'] ?? null,
            ],
            'statistics' => $this->when(
                $request->has('include_stats'),
                function () {
                    return [
                        'total_threads' => $this->threads()->count(),
                        'total_messages' => $this->messages()->count(),
                        'unread_messages' => $this->messages()->where('is_unread', true)->count(),
                    ];
                }
            ),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

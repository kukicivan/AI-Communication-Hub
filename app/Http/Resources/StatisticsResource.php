<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class StatisticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'threads' => [
                'total' => $this->resource['total_threads'] ?? 0,
                'unread' => $this->resource['unread_threads'] ?? 0,
                'starred' => $this->resource['starred_threads'] ?? 0,
                'important' => $this->resource['important_threads'] ?? 0,
            ],
            'messages' => [
                'total' => $this->resource['total_messages'] ?? 0,
                'unread' => $this->resource['unread_messages'] ?? 0,
                'starred' => $this->resource['starred_messages'] ?? 0,
                'with_attachments' => $this->resource['messages_with_attachments'] ?? 0,
            ],
            'attachments' => [
                'total' => $this->resource['total_attachments'] ?? 0,
            ],
            'labels' => [
                'total' => $this->resource['total_labels'] ?? 0,
            ],
            'ai' => [
                'pending' => $this->resource['pending_ai_processing'] ?? 0,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

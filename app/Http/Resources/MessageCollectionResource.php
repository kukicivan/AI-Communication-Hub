<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Request;

class MessageCollectionResource extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->collection->count(),
                'unread_count' => $this->collection->where('is_unread', true)->count(),
                'starred_count' => $this->collection->where('is_starred', true)->count(),
                'with_attachments' => $this->collection->where('attachment_count', '>', 0)->count(),
                'high_priority' => $this->collection->where('priority', 'high')->count(),
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

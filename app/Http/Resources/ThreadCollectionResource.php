<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Request;

class ThreadCollectionResource extends ResourceCollection
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
                'starred_count' => $this->collection->where('has_starred_messages', true)->count(),
                'important_count' => $this->collection->where('is_important', true)->count(),
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

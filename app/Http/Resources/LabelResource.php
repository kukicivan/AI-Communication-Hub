<?php

Namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label_id' => $this->label_id,
            'name' => $this->name,
            'type' => $this->type,
            'color' => $this->color,
            'is_active' => $this->is_active,
            'is_system' => $this->isSystemLabel(),

            // Counts (when requested)
            'message_count' => $this->when(
                $request->has('include_counts'),
                fn() => $this->getMessageCount()
            ),
            'thread_count' => $this->when(
                $request->has('include_counts'),
                fn() => $this->getThreadCount()
            ),
            'unread_count' => $this->when(
                $request->has('include_counts'),
                fn() => $this->getUnreadMessageCount()
            ),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

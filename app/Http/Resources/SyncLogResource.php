<?php

Namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->channel_id,
            'status' => $this->status,

            // Timestamps
            'started_at' => $this->started_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'duration' => $this->getDuration(),
            'duration_formatted' => $this->getFormattedDuration(),

            // Statistics
            'messages_fetched' => $this->messages_fetched,
            'messages_processed' => $this->messages_processed,
            'messages_failed' => $this->messages_failed,
            'success_rate' => $this->getSuccessRate(),

            // Errors
            'has_errors' => $this->hasErrors(),
            'errors' => $this->when(
                $this->hasErrors(),
                $this->errors
            ),

            // Channel info
            'channel' => new ChannelResource($this->whenLoaded('channel')),
        ];
    }
}

<?php

Namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcessingJobResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message_id' => $this->message_id,
            'thread_id' => $this->thread_id,
            'job_type' => $this->job_type,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'can_retry' => $this->canRetry(),
            'is_stale' => $this->isStale(),

            // Payload (sanitized)
            'payload' => $this->when(
                !empty($this->payload),
                function () {
                    // Remove sensitive data from payload
                    $payload = $this->payload;
                    unset($payload['api_key'], $payload['secrets']);
                    return $payload;
                }
            ),

            // Error info
            'error_message' => $this->when(
                $this->status === 'failed',
                $this->error_message
            ),

            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),

            // Related data
            'message' => new MessageResource($this->whenLoaded('message')),
            'thread' => new ThreadResource($this->whenLoaded('thread')),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThreadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'thread_id' => $this->thread_id,
            'subject' => $this->subject,
            'snippet' => $this->getSnippet(),

            // Participants
            'participants' => $this->participants,
            'participant_count' => count($this->participants),
            'participant_emails' => $this->getParticipantEmails(),

            // Counts
            'message_count' => $this->message_count,
            'unread_count' => $this->getUnreadCount(),
            'attachment_count' => $this->messages()->sum('attachment_count'),

            // Timestamps
            'last_message_at' => $this->last_message_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),

            // Status flags
            'is_unread' => $this->is_unread,
            'is_starred' => $this->has_starred_messages,
            'is_important' => $this->is_important,
            'is_in_inbox' => $this->is_in_inbox,
            'is_in_trash' => $this->is_in_trash,
            'is_in_spam' => $this->is_in_spam,

            // Links
            'permalink' => $this->permalink,

            // Labels
            'labels' => LabelResource::collection($this->whenLoaded('labels')),
            'label_names' => $this->when(
                !$this->relationLoaded('labels') && !empty($this->labels),
                fn() => collect($this->labels)->pluck('name')->toArray()
            ),

            // AI Analysis
            'ai_analysis' => $this->when(
                $this->ai_status === 'completed' && !empty($this->ai_analysis),
                function () {
                    return [
                        'status' => $this->ai_status,
                        'processed_at' => $this->ai_processed_at?->toIso8601String(),
                        'sentiment' => $this->ai_analysis['sentiment'] ?? null,
                        'classification' => $this->ai_analysis['classification'] ?? null,
                        'summary' => $this->ai_analysis['summary'] ?? null,
                        'key_points' => $this->ai_analysis['keyPoints'] ?? [],
                        'action_items' => $this->ai_analysis['actionItems'] ?? [],
                    ];
                }
            ),

            // Messages (when loaded)
            'messages' => MessageResource::collection($this->whenLoaded('messages')),

            // Channel info
            'channel' => new ChannelResource($this->whenLoaded('channel')),
        ];
    }

    /**
     * Get thread snippet from first message
     */
    private function getSnippet(): ?string
    {
        if ($this->relationLoaded('messages')) {
            $firstMessage = $this->messages->first();
            return $firstMessage?->getSnippet(150);
        }
        return null;
    }

    /**
     * Additional metadata
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Clean UTF-8 for JSON encoding
     */
    public static function cleanUtf8(string $str): string
    {
        return mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
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
            'message_number' => $this->message_number,

            // Timestamps
            'timestamp' => $this->message_timestamp->toIso8601String(),
            'received_at' => $this->received_date?->toIso8601String(),
            'synced_at' => $this->synced_at?->toIso8601String(),

            // Participants
            'sender' => [
                'email' => $this->getSenderEmail(),
                'name' => $this->getSenderName(),
                'raw' => $this->sender['raw'] ?? null,
            ],
            'recipients' => [
                'to' => $this->formatRecipientList($this->recipients['to'] ?? []),
                'cc' => $this->formatRecipientList($this->recipients['cc'] ?? []),
                'bcc' => $this->formatRecipientList($this->recipients['bcc'] ?? []),
                'reply_to' => $this->when(
                    !empty($this->recipients['replyTo']),
                    $this->formatRecipientList($this->recipients['replyTo'] ?? [])
                ),
            ],

            // Content
            'content' => [
                'text' => $this->content_text,
                'html' => $this->when(
                    $request->input('include_html', false),
                    $this->content_html
                ),
                'snippet' => $this->getSnippet(200),
                'has_html' => !empty($this->content_html),
            ],

            // Subject
            'subject' => $this->getSubject(),

            // Attachments
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'attachment_count' => $this->attachment_count,
            'has_attachments' => $this->hasAttachments(),

            // Reactions
            'reactions' => $this->when(
                !empty($this->reactions),
                $this->reactions
            ),

            // Status flags
            'is_draft' => $this->is_draft,
            'is_unread' => $this->is_unread,
            'is_starred' => $this->is_starred,
            'is_in_trash' => $this->is_in_trash,
            'is_in_inbox' => $this->is_in_inbox,
            'is_spam' => $this->is_spam,
            'is_reply' => $this->isReply(),

            // Priority
            'priority' => $this->priority,
            'is_high_priority' => $this->isHighPriority(),

            // Labels
            'labels' => LabelResource::collection($this->whenLoaded('labels')),

            // Headers (when loaded)
            'headers' => new HeaderResource($this->whenLoaded('headers')),

            // Thread info (when loaded)
            'thread' => $this->when(
                $this->relationLoaded('thread'),
                function () {
                    return [
                        'id' => $this->thread->id,
                        'thread_id' => $this->thread->thread_id,
                        'subject' => $this->thread->subject,
                        'message_count' => $this->thread->message_count,
                    ];
                }
            ),

            // Reply chain
            'parent_message_id' => $this->parent_message_id,
            'reply_count' => $this->when(
                $this->relationLoaded('replies'),
                $this->replies->count()
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
                        'entities' => $this->ai_analysis['entities'] ?? [],
                    ];
                }
            ),

            // Processing status
            'status' => $this->status,
            'ai_status' => $this->ai_status,
        ];
    }

    /**
     * Format recipient list
     */
    private function formatRecipientList(array $recipients): array
    {
        return collect($recipients)->map(function ($recipient) {
            return [
                'email' => $recipient['email'] ?? '',
                'name' => $recipient['name'] ?? $recipient['email'] ?? '',
            ];
        })->toArray();
    }
}

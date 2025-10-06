<?php

namespace App\Services;

use App\Models\MessagingChannel;
use App\Models\MessageThread;
use App\Models\MessagingMessage;
use App\Models\MessagingAttachment;
use App\Models\MessagingHeader;
use App\Models\MessagingLabel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MessagePersistenceService
 *
 * Handles all database operations for saving messages, threads, attachments,
 * headers, and labels. Ensures data integrity through transactions.
 */
class MessagePersistenceService
{
    /**
     * Persist a complete message with all related data
     *
     * @param array $messageData Message data from adapter (IMessage format)
     * @param MessagingChannel $channel The channel this message belongs to
     * @return MessagingMessage|null
     * @throws Throwable
     */
    public function persistMessage(array $messageData, MessagingChannel $channel): ?MessagingMessage
    {
        // Sanitize incoming message data to avoid malformed UTF-8 in DB
        $messageData = $this->sanitizeMessageData($messageData);

        DB::beginTransaction();

        try {
            // 1. Create or update thread
            $thread = $this->createOrUpdateThread($messageData, $channel);

            // 2. Check if message already exists (prevent duplicates)
            $existingMessage = MessagingMessage::where('message_id', $messageData['id'])->first();

            if ($existingMessage) {
                Log::info('Message already exists, updating', [
                    'message_id' => $messageData['id']
                ]);

                $message = $this->updateExistingMessage($existingMessage, $messageData, $channel, $thread);
            } else {
                $message = $this->createNewMessage($messageData, $channel, $thread);
            }

            // 3. Persist attachments if present
            if (!empty($messageData['content']['attachments'])) {
                $this->persistAttachments($message, $messageData['content']['attachments']);
            }

            // 4. Persist headers if present
            if (!empty($messageData['metadata']['headers'])) {
                $this->persistHeaders($message, $messageData['metadata']['headers']);
            }

            // 5. Persist labels if present
            if (!empty($messageData['metadata']['labels'])) {
                $this->persistLabels($message, $thread, $messageData['metadata']['labels']);
            }

            // 6. Update thread statistics
            $this->updateThreadStatistics($thread);
            // TODO: Messaging Message table missing attachment_count = 1 (same as on thread)
            DB::commit();

            Log::info('Message persisted successfully', [
                'message_id' => $message->message_id,
                'thread_id' => $thread->thread_id
            ]);

            return $message;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to persist message', [
                'message_id' => $messageData['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Create or update thread
     */
    public function createOrUpdateThread(array $messageData, MessagingChannel $channel): MessageThread
    {
        $threadData = $messageData['thread'] ?? [];
        $threadId = $messageData['threadId'];

        // Extract participants from message
        $participants = $this->extractParticipants($messageData);

        $thread = MessageThread::updateOrCreate(
            [
                'thread_id' => $threadId,
                'channel_id' => $channel->id,
            ],
            [
                'subject' => $threadData['subject'] ?? $messageData['metadata']['subject'] ?? '(No Subject)',
                'participants' => $participants,
                'last_message_at' => Carbon::parse($messageData['timestamp']),
                'is_unread' => $threadData['isUnread'] ?? $messageData['metadata']['isUnread'] ?? false,
                'has_starred_messages' => $threadData['hasStarredMessages'] ?? $messageData['metadata']['isStarred'] ?? false,
                'is_important' => $threadData['isImportant'] ?? false,
                'is_in_inbox' => $threadData['isInInbox'] ?? $messageData['metadata']['isInInbox'] ?? true,
                'is_in_chats' => $threadData['isInChats'] ?? $messageData['metadata']['isInChats'] ?? false,
                'is_in_spam' => $threadData['isInSpam'] ?? $messageData['metadata']['isSpam'] ?? false,
                'is_in_trash' => $threadData['isInTrash'] ?? $messageData['metadata']['isInTrash'] ?? false,
                'is_in_priority_inbox' => $threadData['isInPriorityInbox'] ?? false,
                'permalink' => $threadData['permalink'] ?? null,
                'labels' => $threadData['labels'] ?? [],
            ]
        );

        return $thread;
    }

    /**
     * Extract participants from message data
     */
    public function extractParticipants(array $messageData): array
    {
        $participants = [];

        // Add sender
        if (!empty($messageData['sender'])) {
            $participants[] = [
                'email' => $messageData['sender']['email'],
                'name' => $messageData['sender']['name'] ?? $messageData['sender']['email'],
                'role' => 'sender',
            ];
        }

        // Add recipients (to, cc, bcc)
        $recipients = $messageData['recipients'] ?? [];

        foreach (['to', 'cc', 'bcc'] as $type) {
            if (!empty($recipients[$type])) {
                foreach ($recipients[$type] as $recipient) {
                    $participants[] = [
                        'email' => $recipient['email'],
                        'name' => $recipient['name'] ?? $recipient['email'],
                        'role' => $type,
                    ];
                }
            }
        }

        // Remove duplicates (same email)
        $unique = [];
        foreach ($participants as $participant) {
            $email = $participant['email'];
            if (!isset($unique[$email])) {
                $unique[$email] = $participant;
            }
        }

        return array_values($unique);
    }

    /**
     * Create new message
     */
    private function createNewMessage(array $messageData, MessagingChannel $channel, MessageThread $thread): MessagingMessage
    {
        $message = MessagingMessage::create([
            'message_id' => $messageData['id'],
            'channel_id' => $channel->id,
            'thread_id' => $messageData['threadId'],
            'message_number' => $messageData['messageNumber'] ?? 1,
            'parent_message_id' => $messageData['parentMessageId'] ?? null,
            'message_timestamp' => Carbon::parse($messageData['timestamp']),
            'received_date' => isset($messageData['receivedDate'])
                ? Carbon::parse($messageData['receivedDate'])
                : Carbon::parse($messageData['timestamp']),
            'sender' => $messageData['sender'] ?? [],
            'recipients' => $messageData['recipients'] ?? [],
            'content_text' => $messageData['content']['text'] ?? '',
            'content_html' => $messageData['content']['html'] ?? null,
            'content_snippet' => $messageData['content']['snippet'] ?? null,
            'content_raw' => $messageData['content']['rawContent'] ?? null,
            'attachment_count' => count($messageData['content']['attachments'] ?? []),
            'reactions' => $messageData['content']['reactions'] ?? [],
            'metadata' => $messageData['metadata'] ?? [],
            'is_draft' => $messageData['metadata']['isDraft'] ?? false,
            'is_unread' => $messageData['metadata']['isUnread'] ?? false,
            'is_starred' => $messageData['metadata']['isStarred'] ?? false,
            'is_in_trash' => $messageData['metadata']['isInTrash'] ?? false,
            'is_in_inbox' => $messageData['metadata']['isInInbox'] ?? true,
            'is_in_chats' => $messageData['metadata']['isInChats'] ?? false,
            'is_spam' => $messageData['metadata']['isSpam'] ?? false,
            'priority' => $messageData['metadata']['priority'] ?? 'normal',
            'status' => 'new',
            'ai_status' => 'pending',
            'synced_at' => isset($messageData['syncedAt'])
                ? Carbon::parse($messageData['syncedAt'])
                : now(),
        ]);

        return $message;
    }

    /**
     * Update existing message
     */
    private function updateExistingMessage(
        MessagingMessage $message,
        array            $messageData,
        MessagingChannel $channel,
        MessageThread    $thread
    ): MessagingMessage
    {
        $message->update([
            'channel_id' => $channel->id,
            'thread_id' => $messageData['threadId'],
            'message_number' => $messageData['messageNumber'] ?? $message->message_number,
            'is_unread' => $messageData['metadata']['isUnread'] ?? $message->is_unread,
            'is_starred' => $messageData['metadata']['isStarred'] ?? $message->is_starred,
            'is_in_trash' => $messageData['metadata']['isInTrash'] ?? $message->is_in_trash,
            'is_in_inbox' => $messageData['metadata']['isInInbox'] ?? $message->is_in_inbox,
            'metadata' => array_merge($message->metadata ?? [], $messageData['metadata'] ?? []),
            'synced_at' => now(),
        ]);

        return $message;
    }

    /**
     * Persist attachments
     */
    public function persistAttachments(MessagingMessage $message, array $attachments): void
    {
        foreach ($attachments as $attachmentData) {
            $this->persistAttachment($message, $attachmentData);
        }
    }

    /**
     * Persist single attachment
     */
    public function persistAttachment(MessagingMessage $message, array $attachmentData): MessagingAttachment
    {
        $attachmentId = $attachmentData['id'] ?? uniqid('att_');

        $attachment = MessagingAttachment::updateOrCreate(
            [
                'message_id' => $message->id,
                'attachment_id' => $attachmentId,
            ],
            [
                'name' => $attachmentData['name'] ?? 'unnamed',
                'mime_type' => $attachmentData['mimeType'] ?? 'application/octet-stream',
                'extension' => $this->extractExtension($attachmentData['name'] ?? ''),
                'size' => $attachmentData['size'] ?? 0,
                'is_inline' => $attachmentData['isInline'] ?? false,
                'hash' => $attachmentData['hash'] ?? null,
                'url' => $attachmentData['url'] ?? null,
                'storage_path' => null, // Will be set when file is downloaded
                'scanned' => false,
                'is_safe' => true, // Default to safe, scan later
            ]
        );

        return $attachment;
    }

    /**
     * Persist email headers
     */
    public function persistHeaders(MessagingMessage $message, array $headersData): void
    {
        MessagingHeader::updateOrCreate(
            ['message_id' => $message->id],
            [
                'message_id_header' => $headersData['messageId'] ?? null,
                'in_reply_to' => $headersData['inReplyTo'] ?? null,
                'references' => $headersData['references'] ?? [],
                'list_unsubscribe' => $headersData['listUnsubscribe'] ?? null,
                'return_path' => $headersData['returnPath'] ?? null,
                'delivered_to' => $headersData['deliveredTo'] ?? null,
                'received_spf' => $headersData['receivedSpf'] ?? null,
                'authentication_results' => $headersData['authentication'] ?? null,
                'dkim_signature' => $headersData['custom']['DKIM-Signature'] ?? null,
                'x_mailer' => $headersData['custom']['X-Mailer'] ?? null,
                'x_priority' => $headersData['custom']['X-Priority'] ?? null,
                'importance' => $headersData['custom']['Importance'] ?? null,
                'x_spam_score' => $headersData['custom']['X-Spam-Score'] ?? null,
                'x_spam_status' => $headersData['custom']['X-Spam-Status'] ?? null,
                'custom_headers' => $headersData['custom'] ?? [],
            ]
        );
    }

    /**
     * Persist labels (both message and thread labels)
     */
    public function persistLabels(MessagingMessage $message, MessageThread $thread, array $labelsData): void
    {
        $labelIds = [];

        foreach ($labelsData as $labelData) {
            if (is_string($labelData)) {
                // Legacy format: just label name
                $label = MessagingLabel::findOrCreateByName($labelData);
            } else {
                // New format: label object
                $label = MessagingLabel::updateOrCreate(
                    ['label_id' => $labelData['id']],
                    [
                        'name' => $labelData['name'],
                        'type' => $labelData['type'] ?? 'user',
                        'color' => $labelData['color'] ?? null,
                        'is_active' => true,
                    ]
                );
            }

            $labelIds[] = $label->id;
        }

        // Sync labels with message
        $message->labels()->syncWithoutDetaching($labelIds);

        // Sync labels with thread
        $thread->labels()->syncWithoutDetaching($labelIds);
    }

    /**
     * Update thread statistics
     */
    private function updateThreadStatistics(MessageThread $thread): void
    {
        $messageCount = MessagingMessage::where('thread_id', $thread->thread_id)->count();
        $hasStarred = MessagingMessage::where('thread_id', $thread->thread_id)
            ->where('is_starred', true)
            ->exists();
        $hasUnread = MessagingMessage::where('thread_id', $thread->thread_id)
            ->where('is_unread', true)
            ->exists();
        $lastMessageAt = MessagingMessage::where('thread_id', $thread->thread_id)
            ->max('message_timestamp');

        $thread->update([
            'message_count' => $messageCount,
            'has_starred_messages' => $hasStarred,
            'is_unread' => $hasUnread,
            'last_message_at' => $lastMessageAt ? Carbon::parse($lastMessageAt) : $thread->last_message_at,
        ]);
    }

    /**
     * Extract file extension from filename
     */
    private function extractExtension(string $filename): ?string
    {
        $parts = explode('.', $filename);
        return count($parts) > 1 ? strtolower(end($parts)) : null;
    }

    /**
     * Batch persist messages
     *
     * @param array $messages Array of message data
     * @param MessagingChannel $channel
     * @return array ['success' => int, 'failed' => int, 'errors' => array]
     */
    public function bulkPersistMessages(array $messages, MessagingChannel $channel): array
    {
        $stats = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($messages as $index => $messageData) {
            try {
                $message = $this->persistMessage($messageData, $channel);

                if ($message) {
                    $stats['success']++;
                } else {
                    $stats['failed']++;
                    $stats['errors'][] = [
                        'index' => $index,
                        'message_id' => $messageData['id'] ?? 'unknown',
                        'error' => 'Failed to persist message (returned null)',
                    ];
                }
            } catch (\Exception $e) {
                $stats['failed']++;
                $stats['errors'][] = [
                    'index' => $index,
                    'message_id' => $messageData['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];

                Log::error('Batch persist failed for message', [
                    'index' => $index,
                    'message_id' => $messageData['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Bulk persist completed', $stats);

        return $stats;
    }

    /**
     * Delete old messages (cleanup)
     *
     * @param int $days Messages older than this will be deleted
     * @param bool $excludeStarred Keep starred messages
     * @return int Number of messages deleted
     */
    public function deleteOldMessages(int $days = 90, bool $excludeStarred = true): int
    {
        $query = MessagingMessage::where('message_timestamp', '<', now()->subDays($days))
            ->where('is_in_trash', true);

        if ($excludeStarred) {
            $query->where('is_starred', false);
        }

        $count = $query->count();
        $query->delete();

        Log::info('Deleted old messages', [
            'count' => $count,
            'older_than_days' => $days,
            'excluded_starred' => $excludeStarred,
        ]);

        return $count;
    }

    /**
     * Get persistence statistics
     */
    public function getStatistics(MessagingChannel $channel): array
    {
        return [
            'total_threads' => MessageThread::where('channel_id', $channel->id)->count(),
            'total_messages' => MessagingMessage::where('channel_id', $channel->id)->count(),
            'unread_messages' => MessagingMessage::where('channel_id', $channel->id)
                ->where('is_unread', true)
                ->count(),
            'starred_messages' => MessagingMessage::where('channel_id', $channel->id)
                ->where('is_starred', true)
                ->count(),
            'messages_with_attachments' => MessagingMessage::where('channel_id', $channel->id)
                ->where('attachment_count', '>', 0)
                ->count(),
            'total_attachments' => MessagingAttachment::whereHas('message', function ($q) use ($channel) {
                $q->where('channel_id', $channel->id);
            })->count(),
            'total_labels' => MessagingLabel::active()->count(),
            'pending_ai_processing' => MessagingMessage::where('channel_id', $channel->id)
                ->where('ai_status', 'pending')
                ->count(),
        ];
    }

    /**
     * Fix orphaned messages (messages without threads)
     */
    public function fixOrphanedMessages(): int
    {
        $orphanedMessages = MessagingMessage::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('message_threads')
                ->whereColumn('message_threads.thread_id', 'messaging_messages.thread_id');
        })->get();

        $fixed = 0;

        foreach ($orphanedMessages as $message) {
            try {
                $channel = MessagingChannel::find($message->channel_id);
                if ($channel) {
                    // Reconstruct message data for thread creation
                    $messageData = [
                        'id' => $message->message_id,
                        'threadId' => $message->thread_id,
                        'timestamp' => $message->message_timestamp->toIso8601String(),
                        'sender' => $message->sender,
                        'recipients' => $message->recipients,
                        'metadata' => $message->metadata,
                        'thread' => [
                            'subject' => $message->getSubject(),
                            'isUnread' => $message->is_unread,
                        ],
                    ];

                    $this->createOrUpdateThread($messageData, $channel);
                    $fixed++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to fix orphaned message', [
                    'message_id' => $message->message_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Fixed orphaned messages', ['count' => $fixed]);

        return $fixed;
    }

    /**
     * Rebuild thread participant lists
     */
    public function rebuildThreadParticipants(MessageThread $thread): void
    {
        $messages = MessagingMessage::where('thread_id', $thread->thread_id)->get();

        $allParticipants = [];

        foreach ($messages as $message) {
            $messageData = [
                'sender' => $message->sender,
                'recipients' => $message->recipients,
            ];

            $participants = $this->extractParticipants($messageData);

            foreach ($participants as $participant) {
                $email = $participant['email'];
                if (!isset($allParticipants[$email])) {
                    $allParticipants[$email] = $participant;
                }
            }
        }

        $thread->update([
            'participants' => array_values($allParticipants),
        ]);
    }

    /**
     * Mark message as duplicate
     */
    public function isDuplicate(string $messageId): bool
    {
        return MessagingMessage::where('message_id', $messageId)->exists();
    }

    /**
     * Get or create channel
     */
    public function getOrCreateChannel(string $channelType, string $channelId, array $config = []): MessagingChannel
    {
        return MessagingChannel::firstOrCreate(
            [
                'channel_type' => $channelType,
                'channel_id' => $channelId,
            ],
            [
                'name' => $channelId,
                'configuration' => $config,
                'is_active' => true,
            ]
        );
    }

    /**
     * Recursively sanitize message data values to valid UTF-8 strings.
     */
    private function sanitizeMessageData(array $data): array
    {
        // Ensure objects are converted to arrays so recursive walk works
        $encoded = json_encode($data);
        if ($encoded === false) {
            // Fallback: try to coerce to array using json decode roundtrip
            $data = json_decode(json_encode($data), true) ?? (array)$data;
        } else {
            $data = json_decode($encoded, true) ?? $data;
        }

        // Walk recursively and sanitize string values
        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) {
                // If not valid UTF-8, try to remove invalid bytes
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $value = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
                    if ($value === false) {
                        $value = '';
                    }
                }

                // Remove C0 control characters (except tab, newline, CR)
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);

                // Trim long binary garbage
                $value = trim($value);
            }
        });

        return $data;
    }
}

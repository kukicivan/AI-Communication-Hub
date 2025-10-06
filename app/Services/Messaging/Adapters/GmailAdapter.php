<?php

namespace App\Services\Messaging\Adapters;

use App\Http\Resources\ThreadResource;
use App\Interfaces\MessageAdapterInterface;
use App\Services\Messaging\MessageParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GmailAdapter implements MessageAdapterInterface
{
    private string $appScriptUrl;
    private ?string $apiKey;
    private string $channelId;
    private bool $connected = false;
    private ?Carbon $lastSync = null;
    private int $timeout;
    private MessageParser $parser;

    public function __construct(array $config)
    {
        $this->appScriptUrl = $config['app_script_url'] ?? '';
        $this->apiKey = $config['api_key'] ?? null;
        $this->channelId = $config['channel_id'] ?? 'gmail-primary';
        $this->timeout = $config['timeout'] ?? 30;
        $this->parser = new MessageParser();

        if (empty($this->appScriptUrl)) {
            throw new \InvalidArgumentException('Gmail Apps Script URL is required');
        }
    }

    public function getChannelType(): string
    {
        return 'gmail-primary';
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function connect(): bool
    {
        $this->connected = true;
        return true;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Get messages with automatic pagination (unlimited)
     * Makes multiple AppScript requests with 100ms delay between each
     * Now with complete Gmail API field mapping
     */
    public function receiveMessages(?Carbon $since = null, int $limit = 100000): array
    {
        if (!$this->connected) {
            return [
                'success' => false,
                'error' => 'Gmail adapter not connected'
            ];
        }

        $query = $this->buildQuery($since);
        $allThreads = [];
        $threadsPerRequest = 10;
        $startIndex = 0;

        try {
            while (count($allThreads) < $limit) {
                Log::info("Fetching Gmail threads", [
                    'startIndex' => $startIndex,
                    'collected' => count($allThreads)
                ]);

                $response = $this->callAppsScript([
                    'action' => 'getEmailsAsThreads',
                    'query' => $query,
                    'apiKey' => $this->apiKey,
                    'maxResults' => $threadsPerRequest,
                    'includeBody' => false,      // Don't include HTML body (save bandwidth)
                    'includeHeaders' => true,    // ✅ INCLUDE HEADERS (critical for AI)
                    'includeAttachments' => true, // ✅ INCLUDE ATTACHMENT METADATA
                    'startIndex' => $startIndex
                ]);

                if (!is_array($response) || empty($response['threads'] ?? [])) {
                    break;
                }

                $threads = $response['threads'];

                foreach ($threads as $thread) {
                    $allThreads[] = $thread;

                    if (count($allThreads) >= $limit) {
                        break 2;
                    }
                }

                if (count($threads) < $threadsPerRequest) {
                    break;
                }

                $startIndex += $threadsPerRequest;
                usleep(100000); // 100ms delay
            }

            Log::info("Collected total threads: " . count($allThreads));

        } catch (\Exception $e) {
            Log::error('Failed to fetch messages', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        $messages = $this->convertThreadsToMessages($allThreads);
        $this->lastSync = Carbon::now();

        return [
            'success' => true,
            'messages' => $messages,
            'historyId' => $response['historyId'] ?? null,
            'meta' => [
                'total_threads' => count($allThreads),
                'total_messages' => count($messages),
                'query' => $query,
                'synced_at' => $this->lastSync->toIso8601String()
            ]
        ];
    }

    /**
     * Receive messages via Gmail History API
     */
    public function receiveMessagesViaHistory(string $historyId): array
    {
        if (!$this->connected) {
            return [
                'success' => false,
                'error' => 'Gmail adapter not connected'
            ];
        }

        try {
            $response = $this->callAppsScript([
                'action' => 'getHistoryChanges',
                'historyId' => $historyId,
                'apiKey' => $this->apiKey,
                'includeBody' => false,
                'includeHeaders' => true,
                'includeAttachments' => true
            ]);

            if (!is_array($response)) {
                throw new \Exception('Invalid history response');
            }

            $threads = $response['threads'] ?? [];
            $messages = $this->convertThreadsToMessages($threads);
            $this->lastSync = Carbon::now();

            return [
                'success' => true,
                'messages' => $messages,
                'historyId' => $response['historyId'] ?? null,
                'meta' => [
                    'total_threads' => count($threads),
                    'total_messages' => count($messages),
                    'sync_method' => 'history',
                    'synced_at' => $this->lastSync->toIso8601String()
                ]
            ];

        } catch (\Exception $e) {
            Log::error('History sync failed', [
                'historyId' => $historyId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function validateConfiguration(): bool
    {
        return !empty($this->appScriptUrl) && filter_var($this->appScriptUrl, FILTER_VALIDATE_URL);
    }

    public function getLastSync(): ?Carbon
    {
        return $this->lastSync;
    }

    /**
     * Build Gmail search query
     */
    private function buildQuery(?Carbon $since): string
    {
        $query = '';

        // If $since is provided, only get messages after that date
        if ($since) {
            $query = 'after:' . $since->format('Y/m/d');
        }

        // Optionally add: is:unread, in:inbox, etc.
        // For full sync, leave empty to get everything

        return $query;
    }

    /**
     * Call AppScript with error handling and JSON cleanup
     */
    private function callAppsScript(array $payload): array
    {
        $response = Http::timeout($this->timeout)
            ->post($this->appScriptUrl, $payload);

        if (!$response->successful()) {
            throw new \Exception("AppScript error: {$response->status()}");
        }

        $body = $response->body();

        // Clean up invalid UTF-8 sequences (emoji issues)
        $body = preg_replace('/\\\\u(d[89ab][0-9a-f]{2})/i', '', $body);
        $body = ThreadResource::cleanUtf8($body);

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON: ' . json_last_error_msg());
        }

        // Check if AppScript returned an error
        if (isset($data['success']) && $data['success'] === false) {
            throw new \Exception($data['error'] ?? 'Unknown AppScript error');
        }

        return $data['data'] ?? [];
    }

    /**
     * Convert threads to messages with COMPLETE Gmail API field mapping
     */
    private function convertThreadsToMessages(array $threads): array
    {
        $messages = [];

        foreach ($threads as $threadData) {
            $thread = $threadData['thread'] ?? [];
            $threadMessages = $threadData['messages'] ?? [];

            foreach ($threadMessages as $index => $emailMessage) {
                $messages[] = $this->convertSingleMessage($emailMessage, $thread, $index + 1);
            }
        }

        return $messages;
    }

    /**
     * Convert single Gmail message to IMessage format
     * Maps ALL Gmail API fields to our interface
     */
    private function convertSingleMessage(array $emailMessage, array $thread, int $messageNumber): array
    {
        return [
            // ==================== CORE IDENTIFIERS ====================
            'id' => $emailMessage['id'] ?? uniqid('gmail_'),
            'threadId' => $emailMessage['threadId'] ?? $thread['id'] ?? null,
            'messageNumber' => $messageNumber,

            // ==================== TIMESTAMPS ====================
            'timestamp' => $this->parseDate($emailMessage['timestamp'] ?? null)->toIso8601String(),
            'receivedDate' => $this->parseDate($emailMessage['receivedDate'] ?? $emailMessage['timestamp'] ?? null)->toIso8601String(),

            // ==================== CHANNEL INFO ====================
            'channel' => [
                'type' => 'gmail',
                'id' => $emailMessage['channel']['id'] ?? $this->channelId,
                'name' => $emailMessage['channel']['name'] ?? $this->channelId
            ],

            // ==================== PARTICIPANTS ====================
            'sender' => $this->parseSender($emailMessage['sender'] ?? []),
            'recipients' => $this->parseRecipients($emailMessage['recipients'] ?? []),

            // ==================== CONTENT ====================
            'content' => [
                'text' => $emailMessage['content']['text'] ?? '',
                'html' => $emailMessage['content']['html'] ?? null,
                'snippet' => $emailMessage['content']['snippet'] ?? '',
                'attachments' => $this->parseAttachments($emailMessage['content']['attachments'] ?? []),
                'attachmentCount' => $emailMessage['content']['attachmentCount'] ?? 0,
                'reactions' => $this->parseReactions($emailMessage['content']['reactions'] ?? []),
            ],

            // ==================== METADATA ====================
            'metadata' => [
                'subject' => $emailMessage['metadata']['subject'] ?? '(No Subject)',
                'priority' => $emailMessage['metadata']['priority'] ?? 'normal',
                'labels' => $this->parseLabels($thread['labels'] ?? []),

                // Gmail specific states
                'isDraft' => $emailMessage['metadata']['isDraft'] ?? false,
                'isUnread' => $emailMessage['metadata']['isUnread'] ?? false,
                'isStarred' => $emailMessage['metadata']['isStarred'] ?? false,
                'isImportant' => $thread['isImportant'] ?? false,
                'isInTrash' => $emailMessage['metadata']['isInTrash'] ?? false,
                'isInInbox' => $emailMessage['metadata']['isInInbox'] ?? true,
                'isInChats' => $emailMessage['metadata']['isInChats'] ?? false,
                'isSpam' => $thread['isInSpam'] ?? false,

                // Headers
                'headers' => $this->parseHeaders($emailMessage['metadata']['headers'] ?? []),
            ],

            // ==================== THREAD CONTEXT ====================
            'thread' => [
                'id' => $thread['id'] ?? null,
                'subject' => $thread['subject'] ?? '',
                'messageCount' => $thread['messageCount'] ?? 1,
                'isUnread' => $thread['isUnread'] ?? false,
                'hasStarredMessages' => $thread['hasStarredMessages'] ?? false,
                'isImportant' => $thread['isImportant'] ?? false,
                'lastMessageDate' => $this->parseDate($thread['lastMessageDate'] ?? null)->toIso8601String(),
                'permalink' => $thread['permalink'] ?? null,
                'labels' => $this->parseLabels($thread['labels'] ?? []),

                // Additional thread flags
                'isInInbox' => $thread['isInInbox'] ?? true,
                'isInChats' => $thread['isInChats'] ?? false,
                'isInSpam' => $thread['isInSpam'] ?? false,
                'isInTrash' => $thread['isInTrash'] ?? false,
                'isInPriorityInbox' => $thread['isInPriorityInbox'] ?? false,
            ],

            // Parent message for replies (from In-Reply-To header)
            'parentMessageId' => $emailMessage['metadata']['headers']['inReplyTo'] ?? null,

            // ==================== SYSTEM FIELDS ====================
            'createdAt' => Carbon::now()->toIso8601String(),
            'updatedAt' => Carbon::now()->toIso8601String(),
            'syncedAt' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Parse sender with name extraction
     */
    private function parseSender($sender): array
    {
        if (is_array($sender) && isset($sender['email'])) {
            return [
                'email' => $sender['email'],
                'name' => $sender['name'] ?? $sender['email'],
                'raw' => $sender['raw'] ?? $sender['email'],
                'id' => $sender['email']
            ];
        }

        // Fallback: parse from string
        return $this->parseEmailAddress($sender);
    }

    /**
     * Parse recipients (to, cc, bcc, replyTo)
     */
    private function parseRecipients($recipients): array
    {
        return [
            'to' => $this->parseEmailList($recipients['to'] ?? []),
            'cc' => $this->parseEmailList($recipients['cc'] ?? []),
            'bcc' => $this->parseEmailList($recipients['bcc'] ?? []),
            'replyTo' => isset($recipients['replyTo']) && !empty($recipients['replyTo'])
                ? $this->parseEmailList($recipients['replyTo'])
                : null,
        ];
    }

    /**
     * Parse list of email addresses
     */
    private function parseEmailList($emails): array
    {
        if (empty($emails)) {
            return [];
        }

        if (is_string($emails)) {
            $emails = explode(',', $emails);
        }

        return array_map(function($email) {
            if (is_array($email) && isset($email['email'])) {
                return [
                    'email' => $email['email'],
                    'name' => $email['name'] ?? $email['email'],
                    'raw' => $email['raw'] ?? $email['email']
                ];
            }
            return $this->parseEmailAddress(trim($email));
        }, $emails);
    }

    /**
     * Parse labels with type detection
     */
    private function parseLabels($labels): array
    {
        if (empty($labels)) {
            return [];
        }

        return array_map(function($label) {
            if (is_array($label) && isset($label['name'])) {
                return [
                    'id' => $label['id'] ?? strtoupper(str_replace(' ', '_', $label['name'])),
                    'name' => $label['name'],
                    'type' => $label['type'] ?? $this->getLabelType($label['name'])
                ];
            }

            // Legacy format: just string
            if (is_string($label)) {
                return [
                    'id' => strtoupper(str_replace(' ', '_', $label)),
                    'name' => $label,
                    'type' => $this->getLabelType($label)
                ];
            }

            return $label;
        }, $labels);
    }

    /**
     * Determine if label is system or user label
     */
    private function getLabelType(string $labelName): string
    {
        $systemLabels = [
            'INBOX', 'SENT', 'DRAFT', 'SPAM', 'TRASH',
            'IMPORTANT', 'STARRED', 'UNREAD', 'CHAT',
            'CATEGORY_PERSONAL', 'CATEGORY_SOCIAL',
            'CATEGORY_PROMOTIONS', 'CATEGORY_UPDATES',
            'CATEGORY_FORUMS'
        ];

        return in_array(strtoupper($labelName), $systemLabels) ? 'system' : 'user';
    }

    /**
     * Parse headers with all important fields
     */
    private function parseHeaders($headers): array
    {
        if (empty($headers)) {
            return [];
        }

        return [
            'messageId' => $headers['messageId'] ?? null,
            'inReplyTo' => $headers['inReplyTo'] ?? null,
            'references' => $headers['references'] ?? [],
            'listUnsubscribe' => $headers['listUnsubscribe'] ?? null,
            'returnPath' => $headers['returnPath'] ?? null,
            'deliveredTo' => $headers['deliveredTo'] ?? null,
            'receivedSpf' => $headers['receivedSpf'] ?? null,
            'authentication' => $headers['authentication'] ?? null,
            'custom' => $headers['custom'] ?? []
        ];
    }

    /**
     * Parse attachments with complete metadata
     */
    private function parseAttachments($attachments): array
    {
        if (empty($attachments)) {
            return [];
        }

        return array_map(function($attachment) {
            return [
                'id' => $attachment['id'] ?? uniqid('att_'),
                'name' => $attachment['name'] ?? 'unnamed',
                'mimeType' => $attachment['mimeType'] ?? 'application/octet-stream',
                'size' => $attachment['size'] ?? 0,
                'isInline' => $attachment['isInline'] ?? false,
                'hash' => $attachment['hash'] ?? null,
                'url' => $attachment['url'] ?? null,
            ];
        }, $attachments);
    }

    /**
     * Parse reactions (stars in Gmail)
     */
    private function parseReactions($reactions): array
    {
        if (empty($reactions)) {
            return [];
        }

        return array_map(function($reaction) {
            return [
                'type' => $reaction['type'] ?? 'star',
                'timestamp' => $reaction['timestamp'] ?? Carbon::now()->toIso8601String()
            ];
        }, $reactions);
    }

    /**
     * Parse email address from string format "Name <email@example.com>"
     */
    private function parseEmailAddress(string $emailStr): array
    {
        if (empty($emailStr)) {
            return [
                'email' => '',
                'name' => '',
                'raw' => '',
                'id' => ''
            ];
        }

        if (preg_match('/^(.+?)\s*<(.+)>$/', $emailStr, $matches)) {
            $name = trim($matches[1], '" ');
            $email = trim($matches[2]);

            return [
                'email' => $email,
                'name' => $name,
                'raw' => $emailStr,
                'id' => $email
            ];
        }

        $email = trim($emailStr);
        return [
            'email' => $email,
            'name' => $email,
            'raw' => $emailStr,
            'id' => $email
        ];
    }

    /**
     * Parse email addresses from comma-separated string
     */
    private function parseEmailAddresses(string $emailsStr): array
    {
        if (empty($emailsStr)) {
            return [];
        }

        return array_map(
            fn($email) => $this->parseEmailAddress(trim($email)),
            explode(',', $emailsStr)
        );
    }

    /**
     * Parse date with fallback to now
     */
    private function parseDate(?string $dateStr): Carbon
    {
        try {
            return $dateStr ? Carbon::parse($dateStr) : Carbon::now();
        } catch (\Exception $e) {
            Log::warning('Failed to parse date', [
                'date' => $dateStr,
                'error' => $e->getMessage()
            ]);
            return Carbon::now();
        }
    }

    /**
     * Get single thread by ID
     */
    public function getThread(string $threadId): ?array
    {
        if (!$this->connected) {
            return null;
        }

        try {
            $response = $this->callAppsScript([
                'action' => 'getSingleThread',
                'threadId' => $threadId,
                'apiKey' => $this->apiKey,
                'includeBody' => false,
                'includeHeaders' => true,
                'includeAttachments' => true
            ]);

            if (empty($response)) {
                return null;
            }

            $messages = $this->convertThreadsToMessages([$response]);
            return $messages;

        } catch (\Exception $e) {
            Log::error('Failed to get thread', [
                'threadId' => $threadId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Mark thread as read
     */
    public function markAsRead(string $threadId): bool
    {
        if (!$this->connected) {
            return false;
        }

        try {
            $this->callAppsScript([
                'action' => 'markAsRead',
                'threadId' => $threadId,
                'apiKey' => $this->apiKey
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to mark as read', [
                'threadId' => $threadId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Mark thread as unread
     */
    public function markAsUnread(string $threadId): bool
    {
        if (!$this->connected) {
            return false;
        }

        try {
            $this->callAppsScript([
                'action' => 'markAsUnread',
                'threadId' => $threadId,
                'apiKey' => $this->apiKey
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to mark as unread', [
                'threadId' => $threadId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get health status
     */
    public function getHealthStatus(): array
    {
        try {
            // Try to connect and fetch account info
            $this->connect();

            return [
                'status' => 'healthy',
                'connected' => $this->isConnected(),
                'lastSync' => $this->lastSync?->toIso8601String(),
                'channelId' => $this->channelId,
                'appScriptUrl' => $this->appScriptUrl
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

<?php

namespace App\Services\Messaging;

use App\Interfaces\MessageAdapterInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * MessageService - Registry za messaging adaptere
 */
class MessageService
{
    /**
     * @var array<string, MessageAdapterInterface>
     */
    private array $adapters = [];

    /**
     * Registruj adapter
     */
    public function registerAdapter(MessageAdapterInterface $adapter): void
    {
        if (!$adapter->validateConfiguration()) {
            throw new \Exception("Invalid adapter config: {$adapter->getChannelType()}");
        }

        $channelId = $adapter->getChannelId();

        if (!$adapter->connect()) {
            throw new \Exception("Failed to connect adapter: {$channelId}");
        }

        $this->adapters[$channelId] = $adapter;

        Log::info('Adapter registered', [
            'channel_id' => $channelId,
            'type' => $adapter->getChannelType()
        ]);
    }

    /**
     * Dohvati adapter po channel ID
     */
    public function getAdapter(string $channelId): ?MessageAdapterInterface
    {
        // Already registered?
        if (isset($this->adapters[$channelId])) {
            return $this->adapters[$channelId];
        }

        // Try to load from config
        $config = config("messaging.adapters.{$channelId}");

        if (!$config || !($config['enabled'] ?? false)) {
            return null;
        }

        $adapter = $this->createAdapter($channelId, $config);

        if ($adapter) {
            $this->registerAdapter($adapter);
        }

        return $adapter;
    }

    /**
     * Dohvati poruke sa svih adaptera
     *
     * @return array<array> Array of message arrays
     */
    public function getAllMessages(?Carbon $since = null, int $limit = 50): array
    {
        $allMessages = [];

        foreach ($this->adapters as $channelId => $adapter) {
            try {
                if (!$adapter->isConnected()) {
                    Log::warning("Adapter not connected: {$channelId}");
                    continue;
                }

                $result = $adapter->receiveMessages($since, $limit);

                if (!is_array($result) || !($result['success'] ?? false)) {
                    Log::warning("Adapter failed", [
                        'channel_id' => $channelId,
                        'error' => $result['error'] ?? 'Unknown error'
                    ]);
                    continue;
                }

                $messages = $result['messages'] ?? [];
                $allMessages = array_merge($allMessages, $messages);

            } catch (\Exception $e) {
                Log::error("Failed to get messages from {$channelId}: {$e->getMessage()}");
            }
        }

        // Sort by timestamp
        usort($allMessages, function ($a, $b) {
            return strtotime($a['timestamp'] ?? 0) <=> strtotime($b['timestamp'] ?? 0);
        });

        return $allMessages;
    }

    /**
     * Dohvati poruke sa jednog adaptera
     *
     * @return array<array> Array of message arrays
     */
    public function getMessagesFromChannel(string $channelId, ?Carbon $since = null, int $limit = 50): array
    {
        $adapter = $this->getAdapter($channelId);

        if (!$adapter) {
            throw new \Exception("Adapter not found: {$channelId}");
        }

        if (!$adapter->isConnected()) {
            throw new \Exception("Adapter not connected: {$channelId}");
        }

        $result = $adapter->receiveMessages($since, $limit);

        if (!is_array($result) || !($result['success'] ?? false)) {
            throw new \Exception("Failed to fetch messages: " . ($result['error'] ?? 'Unknown'));
        }

        return $result['messages'] ?? [];
    }

    /**
     * Dohvati jednu poruku
     *
     * @return array|null Message array or null
     */
    public function getMessage(string $channelId, string $messageId): ?array
    {
        $adapter = $this->getAdapter($channelId);

        if (!$adapter || !$adapter->isConnected()) {
            return null;
        }

        return $adapter->getMessage($messageId);
    }

    /**
     * Status svih adaptera
     *
     * @return array<string, array>
     */
    public function getAdapterStatuses(): array
    {
        $statuses = [];

        foreach ($this->adapters as $channelId => $adapter) {
            try {
                $statuses[$channelId] = [
                    'channel_id' => $channelId,
                    'type' => $adapter->getChannelType(),
                    'connected' => $adapter->isConnected(),
                    'health' => $adapter->getHealthStatus(),
                    'last_sync' => $adapter->getLastSync()?->toIso8601String()
                ];
            } catch (\Exception $e) {
                $statuses[$channelId] = [
                    'channel_id' => $channelId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $statuses;
    }

    /**
     * Status jednog adaptera
     */
    public function getAdapterStatus(string $channelId): array
    {
        $adapter = $this->getAdapter($channelId);

        if (!$adapter) {
            throw new \Exception("Adapter not found: {$channelId}");
        }

        return [
            'channel_id' => $channelId,
            'type' => $adapter->getChannelType(),
            'connected' => $adapter->isConnected(),
            'health' => $adapter->getHealthStatus(),
            'last_sync' => $adapter->getLastSync()?->toIso8601String()
        ];
    }

    /**
     * Test connection
     */
    public function testConnection(string $channelId): bool
    {
        try {
            $adapter = $this->getAdapter($channelId);

            if (!$adapter) {
                return false;
            }

            if ($adapter->isConnected()) {
                $adapter->disconnect();
            }

            return $adapter->connect();
        } catch (\Exception $e) {
            Log::error("Connection test failed for {$channelId}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Disconnect svi adapteri
     */
    public function shutdown(): void
    {
        foreach ($this->adapters as $channelId => $adapter) {
            try {
                $adapter->disconnect();
            } catch (\Exception $e) {
                Log::error("Failed to disconnect {$channelId}: {$e->getMessage()}");
            }
        }

        $this->adapters = [];
    }

    /**
     * Factory: kreiraj adapter
     */
    private function createAdapter(string $channelId, array $config): ?MessageAdapterInterface
    {
        $config['channel_id'] = $channelId;

        // Map channel ID to adapter class
        $adapterClass = match($channelId) {
            'gmail-primary' => \App\Services\Messaging\Adapters\GmailAdapter::class,
            default => null
        };

        if (!$adapterClass || !class_exists($adapterClass)) {
            Log::warning("Adapter class not found for: {$channelId}");
            return null;
        }

        return new $adapterClass($config);
    }
}

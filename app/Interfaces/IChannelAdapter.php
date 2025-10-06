<?php

namespace App\Interfaces;

interface IChannelAdapter
{
    // Identification
    public function getChannelType(): string;
    public function getChannelId(): string;
    public function getName(): string;
    
    // Connection
    public function connect(): bool;
    public function disconnect(): void;
    public function isConnected(): bool;
    
    // Messages
    public function sendMessage(IMessage $message): string;
    public function receiveMessages(?\DateTime $since = null, ?int $limit = null): array;
    public function getMessage(string $messageId): ?IMessage;
    
    // Channels/Groups
    public function getChannels(): array;
    public function getChannelInfo(string $channelId): ?IChannel;
    
    // Validation and configuration
    public function validateConfiguration(): bool;
    public function getConfiguration(): IAdapterConfig;
    public function updateConfiguration(array $config): void;
    
    // Event handling
    public function onNewMessage(callable $callback): void;
    public function onMessageUpdate(callable $callback): void;
    public function onConnectionStatusChange(callable $callback): void;
    
    // Health check
    public function getHealthStatus(): IHealthStatus;
    public function getLastSync(): ?\DateTime;
}

interface IChannel
{
    public function getId(): string;
    public function getName(): string;
    public function getType(): string; // 'direct' | 'group' | 'channel' | 'thread'
    public function getParticipantCount(): ?int;
    public function getDescription(): ?string;
    public function isPrivate(): ?bool;
    public function isArchived(): ?bool;
}

interface IAdapterConfig
{
    public function getType(): string;
    public function getId(): string;
    public function getName(): string;
    public function isEnabled(): bool;
    public function getCredentials(): array;
    public function getSettings(): array;
    public function getWebhookUrl(): ?string;
    public function getSyncInterval(): ?int;
}

interface IHealthStatus
{
    public function getStatus(): string; // 'healthy' | 'degraded' | 'unhealthy'
    public function getLastCheck(): \DateTime;
    public function getDetails(): array;
    public function getErrors(): array;
    public function getLatency(): ?int;
}

<?php

namespace App\Interfaces;

use App\Services\Messaging\DTOs\Message;
use Illuminate\Support\Carbon;

interface MessageAdapterInterface
{
    public function connect(): bool;

    public function disconnect(): void;

    public function isConnected(): bool;

    public function receiveMessages(?Carbon $since = null, int $limit = 50): array;

    public function validateConfiguration(): bool;

    public function getChannelType(): string;

    public function getChannelId(): string;
}

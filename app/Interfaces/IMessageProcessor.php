<?php

namespace App\Interfaces;

interface IMessageProcessor
{
    public function processIncoming(array $rawMessage, IChannelAdapter $adapter): IMessage;
    public function processOutgoing(IMessage $message, IChannelAdapter $adapter): array;
    public function enrichMessage(IMessage $message): IMessage;
    public function validateMessage(IMessage $message): bool;
    public function normalizeMessage(IMessage $message): IMessage;
}

interface IProcessingResult
{
    public function isSuccess(): bool;
    public function getMessage(): ?IMessage;
    public function getErrors(): array;
    public function getWarnings(): array;
    public function getMetadata(): array;
}

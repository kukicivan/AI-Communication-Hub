<?php

namespace App\Interfaces;

interface IMessage
{
    public function getId(): string;
    public function getTimestamp(): \DateTime;
    public function getChannel(): string;
    public function getSubChannel(): ?string;
    public function getSender(): IParticipant;
    public function getRecipients(): array;
    public function getContent(): IMessageContent;
    public function getMetadata(): IMessageMetadata;
    public function getThreadId(): ?string;
    public function getParentMessageId(): ?string;
}

interface IMessageContent
{
    public function getText(): string;
    public function getHtml(): ?string;
    public function getAttachments(): array;
    public function getReactions(): array;
    public function getFormatting(): ?IFormatting;
}

interface IParticipant
{
    public function getId(): string;
    public function getName(): string;
    public function getEmail(): ?string;
    public function getAvatar(): ?string;
    public function getRole(): ?string;
}

interface IAttachment
{
    public function getId(): string;
    public function getName(): string;
    public function getType(): string;
    public function getSize(): int;
    public function getUrl(): ?string;
    public function getBase64Data(): ?string;
}

interface IReaction
{
    public function getEmoji(): string;
    public function getCount(): int;
    public function getUsers(): array;
}

interface IFormatting
{
    public function isBold(): ?bool;
    public function isItalic(): ?bool;
    public function isUnderline(): ?bool;
    public function isCode(): ?bool;
    public function getMentions(): array;
    public function getLinks(): array;
}

interface ILink
{
    public function getUrl(): string;
    public function getText(): string;
    public function getStart(): int;
    public function getEnd(): int;
}

interface IMessageMetadata
{
    public function getSubject(): ?string;
    public function getPriority(): ?string;
    public function getLabels(): array;
    public function isRead(): ?bool;
    public function isStarred(): ?bool;
    public function isArchived(): ?bool;
    public function isSpam(): ?bool;
    public function getHeaders(): array;
    public function getSource(): ?string;
    public function getConfidence(): ?float;
    public function isProcessed(): ?bool;
    public function getProcessingErrors(): array;
}

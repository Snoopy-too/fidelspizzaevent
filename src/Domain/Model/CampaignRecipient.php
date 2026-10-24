<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class CampaignRecipient
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_OPTED_OUT = 'opted_out';

    public function __construct(
        public private(set) ?int $id,
        public private(set) int $campaignId,
        public private(set) int $userId,
        public private(set) string $emailSentTo,
        public private(set) string $status = self::STATUS_PENDING,
        public private(set) ?string $errorMessage = null,
        public private(set) ?string $sentAt = null
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampaignId(): int
    {
        return $this->campaignId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getEmailSentTo(): string
    {
        return $this->emailSentTo;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getSentAt(): ?string
    {
        return $this->sentAt;
    }

    public function markSent(): void
    {
        $this->status = self::STATUS_SENT;
        $this->errorMessage = null;
        $this->sentAt = date('Y-m-d H:i:s');
    }

    public function markFailed(string $error): void
    {
        $this->status = self::STATUS_FAILED;
        $this->errorMessage = mb_substr($error, 0, 500);
        $this->sentAt = date('Y-m-d H:i:s');
    }

    public function markOptedOut(): void
    {
        $this->status = self::STATUS_OPTED_OUT;
        $this->errorMessage = 'User has unsubscribed from promotional emails.';
        $this->sentAt = date('Y-m-d H:i:s');
    }
}

<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

use InvalidArgumentException;

final class PromotionalCampaign
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENDING = 'sending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    private int $sentCount;
    private int $failedCount;
    private string $status;
    private ?string $completedAt;

    public function __construct(
        private readonly ?int $id,
        private readonly int $adminId,
        private readonly string $subject,
        private readonly string $bodyContent,
        private readonly int $totalRecipients,
        int $sentCount = 0,
        int $failedCount = 0,
        string $status = self::STATUS_DRAFT,
        private readonly ?string $createdAt = null,
        ?string $completedAt = null
    ) {
        // Enforce anti-CRLF injection invariant on subject
        if (preg_match('/[\r\n]/', $subject)) {
            throw new InvalidArgumentException('Email subject must not contain carriage return or newline characters.');
        }

        if (trim($subject) === '') {
            throw new InvalidArgumentException('Email subject cannot be empty.');
        }

        if (trim($bodyContent) === '') {
            throw new InvalidArgumentException('Email body cannot be empty.');
        }

        $this->sentCount = max(0, $sentCount);
        $this->failedCount = max(0, $failedCount);
        $this->status = in_array($status, [self::STATUS_DRAFT, self::STATUS_SENDING, self::STATUS_COMPLETED, self::STATUS_FAILED], true)
            ? $status
            : self::STATUS_DRAFT;
        $this->completedAt = $completedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdminId(): int
    {
        return $this->adminId;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBodyContent(): string
    {
        return $this->bodyContent;
    }

    public function getTotalRecipients(): int
    {
        return $this->totalRecipients;
    }

    public function getSentCount(): int
    {
        return $this->sentCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?string
    {
        return $this->completedAt;
    }

    public function recordSent(): void
    {
        $this->sentCount++;
    }

    public function recordFailed(): void
    {
        $this->failedCount++;
    }

    public function markCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = date('Y-m-d H:i:s');
    }

    public function markFailed(): void
    {
        $this->status = self::STATUS_FAILED;
        $this->completedAt = date('Y-m-d H:i:s');
    }
}

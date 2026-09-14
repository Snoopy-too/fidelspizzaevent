<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

use InvalidArgumentException;

final class PromotionalTemplate
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $adminId,
        private readonly string $name,
        private readonly string $subject,
        private readonly string $bodyContent,
        private readonly ?string $createdAt = null,
        private readonly ?string $updatedAt = null
    ) {
        $cleanName = trim($this->name);
        if ($cleanName === '') {
            throw new InvalidArgumentException('Template name cannot be empty.');
        }

        $cleanSubject = trim($this->subject);
        if ($cleanSubject === '') {
            throw new InvalidArgumentException('Template subject cannot be empty.');
        }

        if (preg_match('/[\r\n]/', $cleanSubject)) {
            throw new InvalidArgumentException('Template subject contains forbidden newline characters.');
        }

        if (trim($this->bodyContent) === '') {
            throw new InvalidArgumentException('Template body content cannot be empty.');
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdminId(): int
    {
        return $this->adminId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBodyContent(): string
    {
        return $this->bodyContent;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'admin_id' => $this->adminId,
            'name' => $this->name,
            'subject' => $this->subject,
            'body_content' => $this->bodyContent,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
}

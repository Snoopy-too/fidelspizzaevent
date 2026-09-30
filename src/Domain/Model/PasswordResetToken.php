<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

use DateTimeImmutable;
use InvalidArgumentException;

final class PasswordResetToken
{
    private int $userId;
    private string $selector;
    private string $tokenHash;
    private ?string $requestedIp;
    private DateTimeImmutable $expiresAt;
    private ?DateTimeImmutable $usedAt;
    private DateTimeImmutable $createdAt;
    private ?int $id;

    public function __construct(
        int $userId,
        string $selector,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        ?string $requestedIp = null,
        ?DateTimeImmutable $usedAt = null,
        ?DateTimeImmutable $createdAt = null,
        ?int $id = null
    ) {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be a positive integer.');
        }
        if (!preg_match('/^[a-f0-9]{16}$/', $selector)) {
            throw new InvalidArgumentException('Selector must be a 16-character lowercase hex string.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $tokenHash)) {
            throw new InvalidArgumentException('Token hash must be a 64-character SHA-256 hex string.');
        }

        $this->id = $id;
        $this->userId = $userId;
        $this->selector = $selector;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->requestedIp = $requestedIp;
        $this->usedAt = $usedAt;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    /**
     * Split a raw 80-char hex token into [selector, verifier].
     *
     * @return array{selector: string, verifier: string}|null
     */
    public static function parseRawToken(string $rawToken): ?array
    {
        $clean = strtolower(trim($rawToken));
        if (!preg_match('/^[a-f0-9]{80}$/', $clean)) {
            return null;
        }

        return [
            'selector' => substr($clean, 0, 16),
            'verifier' => substr($clean, 16, 64),
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getRequestedIp(): ?string
    {
        return $this->requestedIp;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getUsedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        $reference = $now ?? new DateTimeImmutable();
        return $reference >= $this->expiresAt;
    }

    public function verifyVerifier(string $rawVerifier): bool
    {
        $clean = strtolower(trim($rawVerifier));
        if (!preg_match('/^[a-f0-9]{64}$/', $clean)) {
            return false;
        }
        $candidateHash = hash('sha256', $clean);
        return hash_equals($this->tokenHash, $candidateHash);
    }

    public function isValid(string $rawVerifier, ?DateTimeImmutable $now = null): bool
    {
        if ($this->isUsed() || $this->isExpired($now)) {
            return false;
        }
        return $this->verifyVerifier($rawVerifier);
    }
}

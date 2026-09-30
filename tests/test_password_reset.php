<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../src/bootstrap.php';

use FidelsPizza\Application\UseCase\RequestPasswordResetUseCase;
use FidelsPizza\Application\UseCase\ResetPasswordUseCase;
use FidelsPizza\Domain\Model\PasswordResetToken;
use FidelsPizza\Domain\Repository\PasswordResetRepositoryInterface;
use FidelsPizza\Domain\Service\EmailSenderInterface;

echo "===========================================\n";
echo "PASSWORD RECOVERY & RESET VERIFICATION\n";
echo "===========================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertTrue(bool $condition, string $message): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo " [PASS] $message\n";
        $testsPassed++;
    } else {
        echo " [FAIL] $message\n";
        $testsFailed++;
    }
}

// 1. Domain Model Invariants & Split-Token Verification
$selector = bin2hex(random_bytes(8));
$verifier = bin2hex(random_bytes(32));
$wrongVerifier = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $verifier);
$rawToken = $selector . $verifier;

$parsed = PasswordResetToken::parseRawToken($rawToken);
assertTrue($parsed !== null && $parsed['selector'] === $selector && $parsed['verifier'] === $verifier, "Split token (80 hex chars) parses into 16-char selector + 64-char verifier");
assertTrue(PasswordResetToken::parseRawToken('invalid-token') === null, "Malformed raw token is rejected by parseRawToken()");

$domainToken = new PasswordResetToken(
    userId: 1,
    selector: $selector,
    tokenHash: $tokenHash,
    expiresAt: (new \DateTimeImmutable())->modify('+1 hour')
);
assertTrue($domainToken->isValid($verifier) === true, "Valid verifier passes constant-time SHA-256 verification");
assertTrue($domainToken->isValid($wrongVerifier) === false, "Wrong verifier fails SHA-256 verification");

$expiredToken = new PasswordResetToken(
    userId: 1,
    selector: $selector,
    tokenHash: $tokenHash,
    expiresAt: (new \DateTimeImmutable())->modify('-1 second')
);
assertTrue($expiredToken->isValid($verifier) === false, "Expired token is rejected by isValid()");

$usedToken = new PasswordResetToken(
    userId: 1,
    selector: $selector,
    tokenHash: $tokenHash,
    expiresAt: (new \DateTimeImmutable())->modify('+1 hour'),
    usedAt: new \DateTimeImmutable()
);
assertTrue($usedToken->isValid($verifier) === false, "Already-used token is rejected by isValid()");

// 2. Application Layer UseCase Lifecycle Verification (Hexagonal Port/Adapter Test)
$inMemoryRepo = new class implements PasswordResetRepositoryInterface {
    /** @var array<int, array{id: int, email: string, first_name: string, last_name: string, is_confirmed: bool, password_hash: string}> */
    public array $users = [];
    /** @var array<int, PasswordResetToken> */
    public array $tokens = [];
    /** @var array<int, int> */
    public array $activeSessionsByUser = [];
    private int $nextTokenId = 1;

    public function findUserByEmail(string $email): ?array {
        foreach ($this->users as $u) {
            if (strtolower($u['email']) === strtolower(trim($email))) {
                return $u;
            }
        }
        return null;
    }

    public function findUserById(int $userId): ?array {
        return $this->users[$userId] ?? null;
    }

    public function countRecentRequestsForUser(int $userId, \DateTimeImmutable $since): int {
        $count = 0;
        foreach ($this->tokens as $t) {
            if ($t->getUserId() === $userId && $t->getCreatedAt() >= $since) {
                $count++;
            }
        }
        return $count;
    }

    public function countRecentRequestsForIp(string $ipAddress, \DateTimeImmutable $since): int {
        $count = 0;
        foreach ($this->tokens as $t) {
            if ($t->getRequestedIp() === $ipAddress && $t->getCreatedAt() >= $since) {
                $count++;
            }
        }
        return $count;
    }

    public function createToken(PasswordResetToken $token): PasswordResetToken {
        $now = new \DateTimeImmutable();
        foreach ($this->tokens as $id => $existing) {
            if ($existing->getUserId() === $token->getUserId() && !$existing->isUsed()) {
                $this->tokens[$id] = new PasswordResetToken(
                    userId: $existing->getUserId(),
                    selector: $existing->getSelector(),
                    tokenHash: $existing->getTokenHash(),
                    expiresAt: $existing->getExpiresAt(),
                    requestedIp: $existing->getRequestedIp(),
                    usedAt: $now,
                    createdAt: $existing->getCreatedAt(),
                    id: $existing->getId()
                );
            }
        }
        $id = $this->nextTokenId++;
        $saved = new PasswordResetToken(
            userId: $token->getUserId(),
            selector: $token->getSelector(),
            tokenHash: $token->getTokenHash(),
            expiresAt: $token->getExpiresAt(),
            requestedIp: $token->getRequestedIp(),
            usedAt: $token->getUsedAt(),
            createdAt: $token->getCreatedAt(),
            id: $id
        );
        $this->tokens[$id] = $saved;
        return $saved;
    }

    public function findBySelector(string $selector): ?PasswordResetToken {
        foreach ($this->tokens as $t) {
            if ($t->getSelector() === strtolower(trim($selector))) {
                return $t;
            }
        }
        return null;
    }

    public function consumeTokenAndResetPassword(int $tokenId, int $userId, string $newPasswordHash): bool {
        if (!isset($this->tokens[$tokenId]) || $this->tokens[$tokenId]->isUsed()) {
            return false;
        }
        $now = new \DateTimeImmutable();
        foreach ($this->tokens as $id => $t) {
            if ($t->getUserId() === $userId && !$t->isUsed()) {
                $this->tokens[$id] = new PasswordResetToken(
                    userId: $t->getUserId(),
                    selector: $t->getSelector(),
                    tokenHash: $t->getTokenHash(),
                    expiresAt: $t->getExpiresAt(),
                    requestedIp: $t->getRequestedIp(),
                    usedAt: $now,
                    createdAt: $t->getCreatedAt(),
                    id: $t->getId()
                );
            }
        }
        if (isset($this->users[$userId])) {
            $this->users[$userId]['password_hash'] = $newPasswordHash;
            $this->users[$userId]['is_confirmed'] = true;
        }
        unset($this->activeSessionsByUser[$userId]);
        return true;
    }

    public function updateUserPassword(int $userId, string $newPasswordHash): bool {
        if (!isset($this->users[$userId])) {
            return false;
        }
        $this->users[$userId]['password_hash'] = $newPasswordHash;
        unset($this->activeSessionsByUser[$userId]);
        return true;
    }

    public function getPasswordResetEmailTemplate(): ?array {
        return null;
    }
};

$testUserId = 42;
$testEmail = 'hanako.customer@example.com';
$initialPassword = 'OldPassword123!';
$newPassword = 'NewSecurePassword456!';

$inMemoryRepo->users[$testUserId] = [
    'id' => $testUserId,
    'email' => $testEmail,
    'first_name' => 'Hanako',
    'last_name' => 'Yamada',
    'is_confirmed' => false,
    'password_hash' => hashPassword($initialPassword),
];
$inMemoryRepo->activeSessionsByUser[$testUserId] = 2;

$sentEmails = [];
$spyEmailSender = new class($sentEmails) implements EmailSenderInterface {
    public function __construct(public array &$sentEmails) {}
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): bool {
        $this->sentEmails[] = [
            'to' => $toEmail,
            'name' => $toName,
            'subject' => $subject,
            'html' => $htmlBody,
            'text' => $textBody,
        ];
        return true;
    }
};

$requestUseCase = new RequestPasswordResetUseCase(
    $inMemoryRepo,
    $spyEmailSender,
    ['site_title' => "Fidel's Pizza Event", 'site_url' => 'http://localhost/fidelspizzaevent']
);
$resetUseCase = new ResetPasswordUseCase($inMemoryRepo);

// Test 2A: Anti-enumeration on non-existent email
$nonExistentResult = $requestUseCase->execute('nobody_at_all_99999@example.com', '127.0.0.1', 'en');
assertTrue($nonExistentResult['success'] === true && count($sentEmails) === 0, "Non-existent email returns generic success without sending email (anti-enumeration)");

// Test 2B: First reset token request & invalidation when second token is requested
$firstReq = $requestUseCase->execute($testEmail, '127.0.0.1', 'ja');
assertTrue($firstReq['success'] === true && count($sentEmails) === 1, "Password reset request dispatches email for registered user");

preg_match('/reset_password\.php\?token=([a-f0-9]{80})/', $sentEmails[0]['text'], $matches1);
$firstRawToken = $matches1[1] ?? '';
assertTrue(strlen($firstRawToken) === 80, "Reset email contains 80-char split token link");

// Request a second token -> first token must be invalidated
$secondReq = $requestUseCase->execute($testEmail, '127.0.0.1', 'en');
assertTrue($secondReq['success'] === true && count($sentEmails) === 2, "Second password reset request dispatches updated email");

preg_match('/reset_password\.php\?token=([a-f0-9]{80})/', $sentEmails[1]['text'], $matches2);
$secondRawToken = $matches2[1] ?? '';
assertTrue(strlen($secondRawToken) === 80 && $secondRawToken !== $firstRawToken, "Second reset token is distinct from first token");
assertTrue($resetUseCase->validateToken($firstRawToken) === null, "First token is automatically invalidated when a newer token is issued");
assertTrue($resetUseCase->validateToken($secondRawToken) !== null, "Second token is active and valid");

// Test 2C: Password validation checks (short password / mismatch)
$shortAttempt = $resetUseCase->execute($secondRawToken, '12345', '12345');
assertTrue($shortAttempt['success'] === false && $shortAttempt['error_code'] === 'error_password_length', "Password shorter than 6 chars is rejected without consuming token");

$mismatchAttempt = $resetUseCase->execute($secondRawToken, 'ValidPass123', 'DifferentPass123');
assertTrue($mismatchAttempt['success'] === false && $mismatchAttempt['error_code'] === 'error_password_mismatch', "Mismatched password confirmation is rejected without consuming token");

// Test 2D: Successful password reset consumes token, updates hash, confirms email, and revokes sessions
$resetResult = $resetUseCase->execute($secondRawToken, $newPassword, $newPassword);
assertTrue($resetResult['success'] === true, "Valid password reset succeeds");
assertTrue(
    verifyPassword($newPassword, $inMemoryRepo->users[$testUserId]['password_hash']),
    "Customer password_hash verifies against new password"
);
assertTrue(
    !verifyPassword($initialPassword, $inMemoryRepo->users[$testUserId]['password_hash']),
    "Old password no longer verifies"
);
assertTrue(
    $inMemoryRepo->users[$testUserId]['is_confirmed'] === true,
    "Unconfirmed customer account is marked confirmed after proving email ownership via reset token"
);
assertTrue(
    !isset($inMemoryRepo->activeSessionsByUser[$testUserId]),
    "All active sessions for the customer are revoked upon password reset"
);

// Test 2E: Replay attack (re-using consumed token must fail)
$replayResult = $resetUseCase->execute($secondRawToken, 'AnotherPass789!', 'AnotherPass789!');
assertTrue($replayResult['success'] === false && $replayResult['error_code'] === 'error_invalid_or_expired_reset_token', "Re-using an already consumed token is rejected (single-use enforcement)");

// Test 2F: Rate-limiting per user (max 3 requests per 15m window)
$thirdReq = $requestUseCase->execute($testEmail, '127.0.0.2', 'en');
assertTrue($thirdReq['success'] === true && $thirdReq['rate_limited'] === false, "3rd request within 15-minute window is allowed");

$fourthReq = $requestUseCase->execute($testEmail, '127.0.0.2', 'en');
assertTrue($fourthReq['success'] === true && $fourthReq['rate_limited'] === true && count($sentEmails) === 3, "4th request within 15-minute window is rate-limited and does not send another email");

// Test 2G: Admin direct password reset
$adminNewPass = 'AdminSetPassword999!';
$adminReset = $resetUseCase->adminResetPassword($testUserId, $adminNewPass);
assertTrue($adminReset['success'] === true, "Admin direct password reset succeeds");
assertTrue(
    verifyPassword($adminNewPass, $inMemoryRepo->users[$testUserId]['password_hash']),
    "Admin-set password verifies against updated hash"
);

echo "\n===========================================\n";
echo "SUMMARY: $testsPassed passed, $testsFailed failed\n";
echo "===========================================\n";

exit($testsFailed > 0 ? 1 : 0);

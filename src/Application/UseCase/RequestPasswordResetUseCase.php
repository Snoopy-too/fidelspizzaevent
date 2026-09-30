<?php
declare(strict_types=1);

namespace FidelsPizza\Application\UseCase;

use DateTimeImmutable;
use FidelsPizza\Domain\Model\PasswordResetToken;
use FidelsPizza\Domain\Repository\PasswordResetRepositoryInterface;
use FidelsPizza\Domain\Service\EmailSenderInterface;

final class RequestPasswordResetUseCase
{
    public const TOKEN_TTL_SECONDS = 3600; // 60 minutes
    public const RATE_LIMIT_WINDOW_SECONDS = 900; // 15 minutes
    public const MAX_REQUESTS_PER_USER = 3;
    public const MAX_REQUESTS_PER_IP = 5;

    /**
     * @param PasswordResetRepositoryInterface $repository
     * @param EmailSenderInterface $emailSender
     * @param array<string, mixed> $siteConfig
     */
    public function __construct(
        private readonly PasswordResetRepositoryInterface $repository,
        private readonly EmailSenderInterface $emailSender,
        private readonly array $siteConfig = []
    ) {
    }

    /**
     * Request a password reset link for an email address.
     * Always returns success=true for unknown emails to prevent account enumeration.
     *
     * @return array{success: bool, rate_limited: bool, error_code: ?string}
     */
    public function execute(string $email, ?string $ipAddress = null, string $lang = 'ja'): array
    {
        $cleanEmail = trim($email);
        if ($cleanEmail === '' || !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'rate_limited' => false,
                'error_code' => 'error_invalid_email',
            ];
        }

        $now = new DateTimeImmutable();
        $windowStart = $now->modify('-' . self::RATE_LIMIT_WINDOW_SECONDS . ' seconds');

        $cleanIp = $ipAddress !== null ? trim($ipAddress) : null;
        if ($cleanIp !== null && $cleanIp !== '') {
            $ipCount = $this->repository->countRecentRequestsForIp($cleanIp, $windowStart);
            if ($ipCount >= self::MAX_REQUESTS_PER_IP) {
                return [
                    'success' => false,
                    'rate_limited' => true,
                    'error_code' => 'error_reset_rate_limit',
                ];
            }
        }

        $user = $this->repository->findUserByEmail($cleanEmail);
        if ($user === null) {
            // Anti-enumeration: do not reveal that the email is not registered
            return [
                'success' => true,
                'rate_limited' => false,
                'error_code' => null,
            ];
        }

        $userCount = $this->repository->countRecentRequestsForUser($user['id'], $windowStart);
        if ($userCount >= self::MAX_REQUESTS_PER_USER) {
            // Return generic success so rate limit per user cannot be used to enumerate accounts
            return [
                'success' => true,
                'rate_limited' => true,
                'error_code' => null,
            ];
        }

        $this->issueAndSendResetToken($user, $cleanIp, $now, $lang);

        return [
            'success' => true,
            'rate_limited' => false,
            'error_code' => null,
        ];
    }

    /**
     * Trigger a password reset email for a specific customer user ID (e.g. initiated by an admin).
     */
    public function sendResetForUserId(int $userId, ?string $ipAddress = null, string $lang = 'ja'): bool
    {
        $user = $this->repository->findUserById($userId);
        if ($user === null) {
            return false;
        }

        $now = new DateTimeImmutable();
        $cleanIp = $ipAddress !== null ? trim($ipAddress) : null;

        return $this->issueAndSendResetToken($user, $cleanIp, $now, $lang);
    }

    /**
     * @param array{id: int, email: string, first_name: string, last_name: string, is_confirmed: bool} $user
     */
    private function issueAndSendResetToken(
        array $user,
        ?string $ipAddress,
        DateTimeImmutable $now,
        string $lang
    ): bool {
        $selector = bin2hex(random_bytes(8));  // 16 hex chars
        $verifier = bin2hex(random_bytes(32)); // 64 hex chars (256-bit secret)
        $tokenHash = hash('sha256', $verifier);
        $rawToken = $selector . $verifier;     // 80 hex chars total

        $expiresAt = $now->modify('+' . self::TOKEN_TTL_SECONDS . ' seconds');

        $tokenEntity = new PasswordResetToken(
            userId: $user['id'],
            selector: $selector,
            tokenHash: $tokenHash,
            expiresAt: $expiresAt,
            requestedIp: $ipAddress,
            usedAt: null,
            createdAt: $now
        );

        $this->repository->createToken($tokenEntity);

        $resetLink = rtrim($this->resolveBaseUrl(), '/') . '/reset_password.php?token=' . urlencode($rawToken);
        $siteTitle = (string)($this->siteConfig['site_title'] ?? "Fidel's Pizza Event");
        $expiryMinutes = (string)(int)(self::TOKEN_TTL_SECONDS / 60);

        $placeholders = [
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'full_name' => trim($user['first_name'] . ' ' . $user['last_name']),
            'email' => $user['email'],
            'reset_link' => $resetLink,
            'site_title' => $siteTitle,
            'expiry_minutes' => $expiryMinutes,
        ];

        $template = $this->repository->getPasswordResetEmailTemplate();
        if ($template !== null && $lang === 'en') {
            $subject = $this->interpolate($template['subject'], $placeholders);
            $textBody = $this->interpolate($template['body'], $placeholders);
        } elseif ($lang === 'ja') {
            $subject = sprintf('【%s】パスワード再設定のご案内 / Password Reset', $siteTitle);
            $textBody = sprintf(
                "%s 様\n\n%s をご利用いただきありがとうございます。\nアカウント（%s）のパスワード再設定リクエストを受け付けました。\n\n以下のリンクをクリックして、新しいパスワードを設定してください（有効期限：%s分）：\n%s\n\n※このメールに心当たりがない場合は、このまま破棄してください。パスワードは変更されません。\n\n--------------------------------------------------\nHello %s,\n\nWe received a request to reset the password for your account (%s) at %s.\n\nPlease click the link below to set a new password (valid for %s minutes):\n%s\n\nIf you did not request this change, you can safely ignore this email.\n\n%s",
                $user['first_name'],
                $siteTitle,
                $user['email'],
                $expiryMinutes,
                $resetLink,
                $user['first_name'],
                $user['email'],
                $siteTitle,
                $expiryMinutes,
                $resetLink,
                $siteTitle
            );
        } else {
            $defaultSubject = $template['subject'] ?? 'Password Reset Request - {{site_title}}';
            $defaultBody = $template['body'] ?? "Hello {{first_name}},\n\nWe received a request to reset your password at {{site_title}}.\n\nClick the link below to set a new password (valid for {{expiry_minutes}} minutes):\n{{reset_link}}\n\nIf you did not request this, please ignore this email.";
            $subject = $this->interpolate($defaultSubject, $placeholders);
            $textBody = $this->interpolate($defaultBody, $placeholders);
        }

        $htmlBody = $this->buildHtmlEmail($siteTitle, $textBody, $resetLink, $lang);
        $recipientName = trim($user['first_name'] . ' ' . $user['last_name']);

        try {
            return $this->emailSender->send(
                $user['email'],
                $recipientName !== '' ? $recipientName : $user['email'],
                $subject,
                $htmlBody,
                $textBody
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, string> $placeholders
     */
    private function interpolate(string $text, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }

    private function buildHtmlEmail(string $siteTitle, string $textBody, string $resetLink, string $lang): string
    {
        $escapedTitle = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
        $escapedLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
        $escapedBody = nl2br(htmlspecialchars($textBody, ENT_QUOTES, 'UTF-8'));
        $btnLabel = $lang === 'ja' ? 'パスワードを再設定する / Reset Password' : 'Reset Password';

        return <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #f8f9fa; padding: 24px; color: #333333;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
        <h2 style="color: #d32f2f; margin-top: 0;">🍕 {$escapedTitle}</h2>
        <div style="line-height: 1.7; font-size: 15px; color: #444444; margin: 20px 0;">
            {$escapedBody}
        </div>
        <div style="text-align: center; margin: 28px 0;">
            <a href="{$escapedLink}" style="display: inline-block; background-color: #d32f2f; color: #ffffff; text-decoration: none; font-weight: bold; padding: 14px 28px; border-radius: 8px; font-size: 16px;">
                {$btnLabel}
            </a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function resolveBaseUrl(): string
    {
        $configured = trim((string)($this->siteConfig['site_url'] ?? ''));
        if ($configured !== '' && $configured !== 'https://yoursite.com') {
            return $configured;
        }

        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '' && preg_match('/^[a-zA-Z0-9.\-:]+$/', $host)) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
            $scheme = $isHttps ? 'https' : 'http';
            $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
            if (str_ends_with($scriptDir, '/admin')) {
                $scriptDir = substr($scriptDir, 0, -6);
            }
            $scriptDir = rtrim($scriptDir, '/');
            return $scheme . '://' . $host . $scriptDir;
        }

        return $configured !== '' ? $configured : 'http://localhost/fidelspizzaevent';
    }
}

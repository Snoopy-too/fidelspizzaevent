<?php
declare(strict_types=1);

namespace FidelsPizza\Application\UseCase;

use FidelsPizza\Domain\Service\EmailSenderInterface;
use InvalidArgumentException;

final class SendRegistrationConfirmationUseCase
{
    /**
     * @param EmailSenderInterface $emailSender
     * @param array<string, mixed> $siteConfig
     */
    public function __construct(
        private readonly EmailSenderInterface $emailSender,
        private readonly array $siteConfig = []
    ) {
    }

    /**
     * Build and deliver a responsive, anti-phishing compliant confirmation email.
     *
     * @param string $email
     * @param string $firstName
     * @param string $confirmationToken
     * @param string $lang
     * @return bool
     */
    public function execute(
        string $email,
        string $firstName,
        string $confirmationToken,
        string $lang = 'ja'
    ): bool {
        $cleanEmail = trim($email);
        if ($cleanEmail === '' || !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $cleanToken = trim($confirmationToken);
        if ($cleanToken === '') {
            return false;
        }

        $safeFirstName = trim($firstName);
        $baseUrl = $this->resolveBaseUrl();
        $confirmationUrl = rtrim($baseUrl, '/') . '/confirm.php?token=' . urlencode($cleanToken);
        $siteTitle = (string)($this->siteConfig['site_title'] ?? "Fidel's Pizza Event");
        $eventDate = (string)($this->siteConfig['event_date'] ?? '');
        $eventLocation = (string)($this->siteConfig['event_location'] ?? '');
        $adminEmail = (string)($this->siteConfig['admin_email'] ?? '');

        $isJapanese = ($lang === 'ja');

        if ($isJapanese) {
            $subject = sprintf('【%s】メールアドレスのご確認 / Confirm Your Registration', $siteTitle);
        } else {
            $subject = sprintf('Confirm Your Registration - %s', $siteTitle);
        }

        $textBody = $this->buildTextBody(
            $siteTitle,
            $safeFirstName,
            $confirmationUrl,
            $eventDate,
            $eventLocation,
            $adminEmail,
            $isJapanese
        );

        $htmlBody = $this->buildHtmlBody(
            $siteTitle,
            $safeFirstName,
            $confirmationUrl,
            $eventDate,
            $eventLocation,
            $adminEmail,
            $isJapanese
        );

        try {
            return $this->emailSender->send(
                toEmail: $cleanEmail,
                toName: $safeFirstName !== '' ? $safeFirstName : $cleanEmail,
                subject: $subject,
                htmlBody: $htmlBody,
                textBody: $textBody
            );
        } catch (\Throwable) {
            return false;
        }
    }

    private function buildHtmlBody(
        string $siteTitle,
        string $firstName,
        string $confirmationUrl,
        string $eventDate,
        string $eventLocation,
        string $adminEmail,
        bool $isJapanese
    ): string {
        $escapedTitle = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
        $escapedName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $escapedUrl = htmlspecialchars($confirmationUrl, ENT_QUOTES, 'UTF-8');
        $escapedDate = htmlspecialchars($eventDate, ENT_QUOTES, 'UTF-8');
        $escapedLocation = htmlspecialchars($eventLocation, ENT_QUOTES, 'UTF-8');
        $escapedContact = htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8');

        $greeting = $isJapanese
            ? ($escapedName !== '' ? "{$escapedName} 様" : "お客様")
            : ($escapedName !== '' ? "Hello {$escapedName}," : "Hello,");

        $headingText = $isJapanese ? 'ご登録ありがとうございます！' : 'Welcome to ' . $escapedTitle . '!';
        $btnText = $isJapanese ? 'メールアドレスを確認する / Confirm Email' : 'Confirm Your Email Address';
        $messageIntro = $isJapanese
            ? "この度は <strong>{$escapedTitle}</strong> にご登録いただき、誠にありがとうございます。<br>以下の「メールアドレスを確認する」ボタンをクリックして、アカウント認証を完了してください。"
            : "Thank you for creating an account with <strong>{$escapedTitle}</strong>.<br>Please click the button below to confirm your email address and activate your account:";

        $securityNotice = $isJapanese
            ? "※このメールに心当たりがない場合、第三者が誤ってメールアドレスを入力した可能性があります。このままメールを破棄してください。認証を行わない限り、アカウントが有効化されることはありません。"
            : "If you did not create an account with us, please disregard this email. Your account will not be activated without clicking the link above.";

        $troubleNotice = $isJapanese
            ? "ボタンが正常に動作しない場合は、以下のURLをブラウザのアドレスバーに直接貼り付けてアクセスしてください："
            : "If the button above does not work, copy and paste the following URL into your browser:";

        $eventInfoHtml = '';
        if ($escapedDate !== '' || $escapedLocation !== '') {
            $eventInfoHtml = '<div style="margin-top: 24px; padding: 16px; background-color: #f8fafc; border-left: 4px solid #d32f2f; border-radius: 4px; font-size: 14px; color: #475569;">';
            $eventInfoHtml .= '<strong style="color: #1e293b;">🍕 Event Details:</strong><br>';
            if ($escapedDate !== '') {
                $eventInfoHtml .= '<strong>Date:</strong> ' . $escapedDate . '<br>';
            }
            if ($escapedLocation !== '') {
                $eventInfoHtml .= '<strong>Location:</strong> ' . $escapedLocation . '<br>';
            }
            $eventInfoHtml .= '</div>';
        }

        $contactHtml = '';
        if ($escapedContact !== '') {
            $contactHtml = '<p style="margin: 8px 0 0 0; font-size: 12px; color: #94a3b8;">Questions? Contact us at: <a href="mailto:' . $escapedContact . '" style="color: #64748b; text-decoration: underline;">' . $escapedContact . '</a></p>';
        }

        $htmlLang = $isJapanese ? 'ja' : 'en';
        $currentYear = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="{$htmlLang}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$escapedTitle}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 24px 12px; color: #1e293b; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 580px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
        <!-- Header Banner -->
        <tr>
            <td style="background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); padding: 28px 24px; text-align: center; color: #ffffff;">
                <h1 style="margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">🍕 {$escapedTitle}</h1>
            </td>
        </tr>
        <!-- Main Content -->
        <tr>
            <td style="padding: 32px 28px; line-height: 1.7; font-size: 15px; color: #334155;">
                <h2 style="margin: 0 0 16px 0; font-size: 19px; color: #0f172a; font-weight: 600;">{$headingText}</h2>
                <p style="margin: 0 0 16px 0; font-size: 16px; font-weight: 600; color: #1e293b;">{$greeting}</p>
                <p style="margin: 0 0 24px 0;">{$messageIntro}</p>

                <!-- Action Button -->
                <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 28px auto; text-align: center;">
                    <tr>
                        <td style="border-radius: 8px; background-color: #d32f2f;">
                            <a href="{$escapedUrl}" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 15px 32px; font-size: 16px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 8px; background-color: #d32f2f;">
                                {$btnText}
                            </a>
                        </td>
                    </tr>
                </table>

                {$eventInfoHtml}

                <!-- Plain URL Fallback Box -->
                <div style="margin-top: 28px; padding: 14px 16px; background-color: #f8fafc; border-radius: 6px; font-size: 12px; color: #64748b; word-break: break-all; border: 1px dashed #cbd5e1;">
                    <p style="margin: 0 0 6px 0; font-weight: 600; color: #475569;">{$troubleNotice}</p>
                    <a href="{$escapedUrl}" target="_blank" rel="noopener noreferrer" style="color: #d32f2f; text-decoration: underline;">{$escapedUrl}</a>
                </div>

                <!-- Anti-Phishing Security Advisory -->
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #f1f5f9; font-size: 12px; color: #94a3b8; line-height: 1.6;">
                    {$securityNotice}
                </div>
            </td>
        </tr>
        <!-- Footer -->
        <tr>
            <td style="background-color: #f8fafc; padding: 20px 24px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b;">
                <p style="margin: 0 0 4px 0; font-weight: 600; color: #475569;">{$escapedTitle}</p>
                {$contactHtml}
                <p style="margin: 8px 0 0 0; font-size: 11px; color: #94a3b8;">&copy; {$currentYear} {$escapedTitle}. All rights reserved.</p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function buildTextBody(
        string $siteTitle,
        string $firstName,
        string $confirmationUrl,
        string $eventDate,
        string $eventLocation,
        string $adminEmail,
        bool $isJapanese
    ): string {
        if ($isJapanese) {
            $greeting = ($firstName !== '') ? "{$firstName} 様" : "お客様";
            $text = "{$greeting}\n\n";
            $text .= "{$siteTitle} にご登録いただき、誠にありがとうございます。\n";
            $text .= "以下の認証URLをクリックして、メールアドレスの確認とアカウントの有効化を完了してください：\n\n";
            $text .= "{$confirmationUrl}\n\n";

            if ($eventDate !== '' || $eventLocation !== '') {
                $text .= "--------------------------------------------------\n";
                $text .= "【イベント詳細】\n";
                if ($eventDate !== '') {
                    $text .= "開催日: {$eventDate}\n";
                }
                if ($eventLocation !== '') {
                    $text .= "場所: {$eventLocation}\n";
                }
                $text .= "--------------------------------------------------\n\n";
            }

            $text .= "※このメールに心当たりがない場合は、第三者が誤ってメールアドレスを入力した可能性があります。このままメールを破棄してください。認証を行わない限り、アカウントは有効化されません。\n\n";
            if ($adminEmail !== '') {
                $text .= "お問い合わせ: {$adminEmail}\n";
            }
            $text .= "{$siteTitle}";
            return $text;
        }

        $greeting = ($firstName !== '') ? "Hello {$firstName}," : "Hello,";
        $text = "{$greeting}\n\n";
        $text .= "Thank you for registering with {$siteTitle}!\n";
        $text .= "Please click the link below to confirm your email address and activate your account:\n\n";
        $text .= "{$confirmationUrl}\n\n";

        if ($eventDate !== '' || $eventLocation !== '') {
            $text .= "--------------------------------------------------\n";
            $text .= "Event Details:\n";
            if ($eventDate !== '') {
                $text .= "Date: {$eventDate}\n";
            }
            if ($eventLocation !== '') {
                $text .= "Location: {$eventLocation}\n";
            }
            $text .= "--------------------------------------------------\n\n";
        }

        $text .= "If you did not register for this account, please disregard this email.\n\n";
        if ($adminEmail !== '') {
            $text .= "Contact: {$adminEmail}\n";
        }
        $text .= "Best regards,\n{$siteTitle} Team";
        return $text;
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

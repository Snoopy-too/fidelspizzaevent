<?php
declare(strict_types=1);

namespace FidelsPizza\Infrastructure\Mail;

use FidelsPizza\Domain\Service\EmailSenderInterface;
use InvalidArgumentException;

final class NativePhpEmailSender implements EmailSenderInterface
{
    public function __construct(
        private readonly string $fromEmail,
        private readonly string $fromName = "Fidel's Pizza Event"
    ) {
    }

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        // 1. Strict anti-CRLF injection sanitization
        $safeToEmail = str_replace(["\r", "\n"], '', trim($toEmail));
        $safeSubject = str_replace(["\r", "\n"], '', trim($subject));
        $safeFromName = str_replace(["\r", "\n"], '', trim($this->fromName));
        $safeFromEmail = str_replace(["\r", "\n"], '', trim($this->fromEmail));
        $safeToName = str_replace(["\r", "\n"], '', trim($toName));

        if (!filter_var($safeToEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid destination email: $safeToEmail");
        }

        // 2. MIME header encoding
        $encodedSubject = mb_encode_mimeheader($safeSubject, 'UTF-8', 'B', "\r\n");
        $encodedFromName = mb_encode_mimeheader($safeFromName, 'UTF-8', 'B', "\r\n");
        $encodedToName = mb_encode_mimeheader($safeToName, 'UTF-8', 'B', "\r\n");

        $fromHeader = "$encodedFromName <$safeFromEmail>";
        $replyToHeader = $safeFromEmail;

        // 3. RFC 5322 & Deliverability Headers
        $fromDomain = 'localhost';
        if (str_contains($safeFromEmail, '@')) {
            $domainParts = explode('@', $safeFromEmail);
            $fromDomain = (string)array_pop($domainParts);
        }
        $messageId = sprintf('<%s.%s@%s>', bin2hex(random_bytes(12)), (string)microtime(true), $fromDomain);
        $dateHeader = date(DATE_RFC2822);

        // 4. Construct multipart/alternative email
        $boundary = "=_fidels_boundary_" . md5(uniqid((string)microtime(true), true));

        $headers = [
            "Date: $dateHeader",
            "Message-ID: $messageId",
            "MIME-Version: 1.0",
            "From: $fromHeader",
            "Reply-To: $replyToHeader",
            "Auto-Submitted: auto-generated",
            "X-Mailer: FidelsPizza-Mailer/2.0",
            "Content-Type: multipart/alternative; boundary=\"$boundary\""
        ];
        $headersStr = implode("\r\n", $headers);

        $bodyParts = [];
        // Text/plain section
        $bodyParts[] = "--$boundary";
        $bodyParts[] = "Content-Type: text/plain; charset=UTF-8";
        $bodyParts[] = "Content-Transfer-Encoding: 8bit";
        $bodyParts[] = "";
        $bodyParts[] = $textBody;

        // Text/html section
        $bodyParts[] = "--$boundary";
        $bodyParts[] = "Content-Type: text/html; charset=UTF-8";
        $bodyParts[] = "Content-Transfer-Encoding: 8bit";
        $bodyParts[] = "";
        $bodyParts[] = $htmlBody;

        // Final boundary
        $bodyParts[] = "--$boundary--";
        $bodyParts[] = "";

        $multipartPayload = implode("\r\n", $bodyParts);

        // 5. Native PHP mail invocation with envelope sender flag (-f) for Return-Path SPF/DMARC alignment
        $additionalParams = "-f" . $safeFromEmail;
        $sent = @mail($safeToEmail, $encodedSubject, $multipartPayload, $headersStr, $additionalParams);
        if (!$sent) {
            // Fallback retry without -f in case MTA restricts -f parameter for the web user
            $sent = @mail($safeToEmail, $encodedSubject, $multipartPayload, $headersStr);
        }

        if (!$sent) {
            error_log("[NativePhpEmailSender] mail() dispatch failed for recipient '$safeToEmail' (Subject: '$safeSubject')");
        }

        return $sent;
    }
}

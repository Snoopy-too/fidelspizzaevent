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

        // 3. Construct multipart/alternative email
        $boundary = "=_fidels_boundary_" . md5(uniqid((string)time(), true));

        $headers = [
            "MIME-Version: 1.0",
            "From: $fromHeader",
            "Reply-To: $replyToHeader",
            "X-Mailer: FidelsPizza-Promo-Mailer/1.0",
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

        // Native PHP mail invocation
        return @mail($safeToEmail, $encodedSubject, $multipartPayload, $headersStr);
    }
}

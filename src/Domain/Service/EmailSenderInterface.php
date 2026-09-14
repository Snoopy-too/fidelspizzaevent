<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Service;

interface EmailSenderInterface
{
    /**
     * Send an email to a single recipient with subject, HTML body, and plain text alternative.
     *
     * @param string $toEmail The destination email address.
     * @param string $toName  The destination recipient display name.
     * @param string $subject The email subject (must be free of CRLF).
     * @param string $htmlBody The HTML content.
     * @param string $textBody The plain-text fallback content.
     * @return bool True on successful transmission handoff, false otherwise.
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool;
}

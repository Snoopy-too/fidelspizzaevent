<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../src/bootstrap.php';

use FidelsPizza\Application\UseCase\SendRegistrationConfirmationUseCase;
use FidelsPizza\Domain\Service\EmailSenderInterface;

echo "===========================================\n";
echo "REGISTRATION CONFIRMATION EMAIL VERIFICATION\n";
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

/**
 * In-memory test spy for EmailSenderInterface
 */
final class MockEmailSender implements EmailSenderInterface
{
    /** @var array<array{toEmail: string, toName: string, subject: string, htmlBody: string, textBody: string}> */
    public array $sent = [];
    public bool $simulateSuccess = true;

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        if (!$this->simulateSuccess) {
            return false;
        }
        $this->sent[] = [
            'toEmail' => $toEmail,
            'toName' => $toName,
            'subject' => $subject,
            'htmlBody' => $htmlBody,
            'textBody' => $textBody
        ];
        return true;
    }
}

$mockSender = new MockEmailSender();
$siteConfig = [
    'site_title' => "Fidel's Pizza Event",
    'site_url' => 'https://fidelspizza.example.com',
    'event_date' => '2026-11-08',
    'event_location' => 'Pier English School, Chiba',
    'admin_email' => 'contact@fidelspizza.example.com'
];

$useCase = new SendRegistrationConfirmationUseCase($mockSender, $siteConfig);

// 1. Successful Japanese confirmation email dispatch
$token = bin2hex(random_bytes(16));
$resultJa = $useCase->execute('customer@test.com', '太郎', $token, 'ja');
assertTrue($resultJa === true, "Japanese confirmation email returns true");
assertTrue(count($mockSender->sent) === 1, "One email was dispatched");

$sentJa = $mockSender->sent[0];
assertTrue($sentJa['toEmail'] === 'customer@test.com', "Sent to correct email");
assertTrue($sentJa['toName'] === '太郎', "Sent to correct recipient name");
assertTrue(str_contains($sentJa['subject'], "【Fidel's Pizza Event】"), "Subject includes site title banner in JA");
assertTrue(str_contains($sentJa['htmlBody'], '太郎 様'), "HTML body addresses customer politely in Japanese");
assertTrue(str_contains($sentJa['htmlBody'], 'confirm.php?token=' . $token), "HTML contains confirmation token link");
assertTrue(str_contains($sentJa['htmlBody'], 'background-color: #d32f2f'), "HTML has styled CTA button");
assertTrue(str_contains($sentJa['htmlBody'], '※このメールに心当たりがない場合'), "HTML has anti-phishing security notice");
assertTrue(str_contains($sentJa['textBody'], 'confirm.php?token=' . $token), "Text body includes token URL");
assertTrue(str_contains($sentJa['textBody'], 'Pier English School, Chiba'), "Text body includes venue location");

// 2. Successful English confirmation email dispatch
$mockSender->sent = [];
$resultEn = $useCase->execute('john.doe@test.com', 'John', $token, 'en');
assertTrue($resultEn === true, "English confirmation email returns true");
assertTrue(count($mockSender->sent) === 1, "English email dispatched");
$sentEn = $mockSender->sent[0];
assertTrue($sentEn['subject'] === "Confirm Your Registration - Fidel's Pizza Event", "Subject matches English format");
assertTrue(str_contains($sentEn['htmlBody'], 'Hello John,'), "HTML body addresses customer in English");
assertTrue(str_contains($sentEn['htmlBody'], 'Confirm Your Email Address'), "English CTA button text present");
assertTrue(str_contains($sentEn['textBody'], 'If you did not register for this account'), "English anti-phishing notice present");

// 3. XSS Sanitization in first_name
$mockSender->sent = [];
$xssName = '<script>alert("XSS")</script>';
$useCase->execute('victim@test.com', $xssName, $token, 'en');
$sentXss = $mockSender->sent[0];
assertTrue(!str_contains($sentXss['htmlBody'], '<script>'), "HTML body does not contain unescaped script tags");
assertTrue(str_contains($sentXss['htmlBody'], '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;'), "HTML body escapes special characters safely");

// 4. Invalid email rejection
$mockSender->sent = [];
$invalidRes = $useCase->execute('not-an-email', 'Bob', $token, 'en');
assertTrue($invalidRes === false, "Invalid email address is rejected");
assertTrue(empty($mockSender->sent), "No email dispatched for invalid recipient");

// 5. Empty token rejection
$emptyTokenRes = $useCase->execute('bob@test.com', 'Bob', '', 'en');
assertTrue($emptyTokenRes === false, "Empty confirmation token is rejected");
assertTrue(empty($mockSender->sent), "No email dispatched for empty token");

// 6. NativePhpEmailSender Header Check
$nativeSender = new \FidelsPizza\Infrastructure\Mail\NativePhpEmailSender(
    'noreply@fidelspizza.example.com',
    "Fidel's Pizza Event"
);
$reflection = new \ReflectionClass($nativeSender);
assertTrue($reflection->hasMethod('send'), "NativePhpEmailSender has send() method");

echo "\n===========================================\n";
echo "SUMMARY: {$testsPassed} passed, {$testsFailed} failed\n";
echo "===========================================\n";

if ($testsFailed > 0) {
    exit(1);
}

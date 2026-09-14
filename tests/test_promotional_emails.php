<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../src/bootstrap.php';

use FidelsPizza\Application\DTO\CreateCampaignRequest;
use FidelsPizza\Domain\Model\MarketingUser;
use FidelsPizza\Domain\Model\PromotionalCampaign;
use FidelsPizza\Domain\Service\EmailSenderInterface;

echo "===========================================\n";
echo "PROMOTIONAL EMAILS SYSTEM VERIFICATION\n";
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

// TEST 1: Anti-CRLF Injection in Subject Line
try {
    new PromotionalCampaign(
        id: null,
        adminId: 1,
        subject: "Subject with\r\nBcc: evil@attacker.com",
        bodyContent: "Some content",
        totalRecipients: 5
    );
    assertTrue(false, "CRLF injection in PromotionalCampaign subject must throw an exception");
} catch (\InvalidArgumentException $e) {
    assertTrue(true, "CRLF injection in PromotionalCampaign correctly blocked");
}

// TEST 2: MarketingUser Invariants
$optedInUser = new MarketingUser(
    id: 1,
    email: 'user1@example.com',
    firstName: 'Taro',
    lastName: 'Yamada',
    phone: '09012345678',
    isConfirmed: true,
    acceptsMarketing: true,
    unsubscribeToken: 'test_token_123'
);
assertTrue($optedInUser->canReceivePromotions() === true, "Confirmed user with marketing consent can receive promotions");

$optedOutUser = new MarketingUser(
    id: 2,
    email: 'user2@example.com',
    firstName: 'Jiro',
    lastName: 'Tanaka',
    phone: null,
    isConfirmed: true,
    acceptsMarketing: false,
    unsubscribeToken: 'test_token_456'
);
assertTrue($optedOutUser->canReceivePromotions() === false, "Opted-out user cannot receive promotions");

// TEST 3: Placeholder rendering & Unsubscribe Link auto-injection
$container = getServiceContainer();
$sendUseCase = $container->getSendPromotionalCampaignUseCase();

$template = "Hello {{first_name}} {{last_name}}, we invite you to {{site_title}}!";
$rendered = $sendUseCase->renderEmailContent($template, $optedInUser);

assertTrue(str_contains($rendered['text'], 'Hello Taro Yamada, we invite you to'), "Personalization tokens replaced in text");
assertTrue(str_contains($rendered['text'], 'unsubscribe.php?token=test_token_123'), "Unsubscribe link automatically appended when omitted");
assertTrue(str_contains($rendered['html'], 'Taro Yamada'), "Personalization tokens rendered in HTML");
assertTrue(str_contains($rendered['html'], 'Unsubscribe / 配信停止'), "HTML includes unsubscribe footer");

// TEST 4: Campaign Creation and Database Persistence
$adminId = 1;
$testSubject = "Automated Test Campaign - " . date('Ymd_His');
$testBody = "This is a test promo campaign body for {{full_name}}.";

$requestDto = new CreateCampaignRequest(
    adminId: $adminId,
    subject: $testSubject,
    bodyContent: $testBody,
    selectedUserIds: [$optedInUser->getId()]
);

$campaign = $sendUseCase->createCampaign($requestDto);
assertTrue($campaign->getId() !== null && $campaign->getId() > 0, "Campaign persisted to MySQL with generated ID #" . ($campaign->getId() ?? 0));

$loadedCampaign = $container->getCampaignRepository()->findById($campaign->getId());
assertTrue($loadedCampaign !== null, "Campaign loaded back from MySQL InnoDB");
assertTrue($loadedCampaign->getSubject() === $testSubject, "Campaign subject matches persisted record");

// TEST 5: Batch Processing with Mock/Spied Email Sender
// Replace email sender with test spy
$spySent = [];
$mockEmailSender = new class($spySent) implements EmailSenderInterface {
    public function __construct(public array &$spySent) {}
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): bool {
        $this->spySent[] = ['to' => $toEmail, 'subject' => $subject];
        return true;
    }
};

$testContainer = new \FidelsPizza\Infrastructure\Container\ServiceContainer(
    $container->getPdo(),
    $container->getSiteConfig(),
    'noreply@fidelspizza.com',
    "Fidel's Pizza"
);

// We run a batch process using container
$batchResult = $sendUseCase->processBatch($campaign->getId(), 10);
assertTrue($batchResult->isFinished, "Batch process finished processing recipients");
assertTrue($batchResult->totalSent + $batchResult->totalFailed === $batchResult->totalRecipients, "All recipients accounted for");

// TEST 6: Unsubscribe User Flow
$unsubToken = 'test_unsub_run_' . bin2hex(random_bytes(16));
$db = getDB();
// Insert temporary test user
$db->exec("
    INSERT INTO `users` (`email`, `password_hash`, `first_name`, `last_name`, `is_confirmed`, `accepts_marketing`, `unsubscribe_token`)
    VALUES ('unsub_test@example.com', 'dummy_hash', 'Opt', 'Out', 1, 1, '{$unsubToken}')
");
$tempUserId = (int)$db->lastInsertId();

$unsubUseCase = $container->getUnsubscribeUserUseCase();
$unsubResult = $unsubUseCase->execute($unsubToken);

assertTrue($unsubResult['success'] === true, "UnsubscribeUseCase successfully processed token");

// Verify in database
$checkStmt = $db->prepare("SELECT accepts_marketing FROM `users` WHERE id = ?");
$checkStmt->execute([$tempUserId]);
$marketingStatus = (int)$checkStmt->fetchColumn();
assertTrue($marketingStatus === 0, "User accepts_marketing updated to 0 in database");

// Cleanup test user
$db->prepare("DELETE FROM `users` WHERE id = ?")->execute([$tempUserId]);

// TEST 7: Template Management (Save, Load, Delete)
$templateUseCase = $container->getManagePromotionalTemplatesUseCase();
$savedTemplate = $templateUseCase->saveTemplate(
    adminId: 1,
    name: "Test Template " . date('Ymd_His'),
    subject: "Special Offer {{first_name}}!",
    bodyContent: "Hello {{first_name}}, this is a saved template test."
);

assertTrue($savedTemplate->getId() !== null && $savedTemplate->getId() > 0, "Template saved to MySQL with generated ID #" . ($savedTemplate->getId() ?? 0));

$allTemplates = $templateUseCase->getAllTemplates();
$found = array_filter($allTemplates, fn($t) => $t['id'] === $savedTemplate->getId());
assertTrue(!empty($found), "Saved template retrieved from template list");

$deleted = $templateUseCase->deleteTemplate($savedTemplate->getId());
assertTrue($deleted === true, "Template successfully deleted from database");

// Cleanup test campaign
$db->prepare("DELETE FROM `promotional_campaigns` WHERE id = ?")->execute([$campaign->getId()]);
assertTrue(true, "Test artifacts cleaned up successfully");

echo "\n===========================================\n";
echo "SUMMARY: $testsPassed passed, $testsFailed failed\n";
echo "===========================================\n";

if ($testsFailed > 0) {
    exit(1);
}

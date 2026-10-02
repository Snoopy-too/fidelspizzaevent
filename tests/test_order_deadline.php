<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../src/bootstrap.php';

use FidelsPizza\Domain\Model\MarketingUser;
use FidelsPizza\Domain\Service\OrderWindowService;

echo "===========================================\n";
echo "ORDER DEADLINE & AUTO-CUTOFF VERIFICATION\n";
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

$tz = new DateTimeZone('Asia/Tokyo');
$service = new OrderWindowService($tz);

// Test 1: Future deadline with auto mode -> Open
$now = new DateTimeImmutable('2026-10-15 12:00:00', $tz);
$futureConfig = [
    'order_deadline' => '2026-10-20 23:59:00',
    'order_status_override' => 'auto',
    'event_date' => '2026-10-23'
];
assertTrue($service->isOrderingOpen($futureConfig, $now) === true, "Auto mode with future deadline is OPEN");
assertTrue($service->getOrderingStatus($futureConfig, $now) === OrderWindowService::STATUS_OPEN, "Status is 'open'");

// Test 2: Past deadline with auto mode -> Closed
$pastNow = new DateTimeImmutable('2026-10-21 00:01:00', $tz);
assertTrue($service->isOrderingOpen($futureConfig, $pastNow) === false, "Auto mode with past deadline is CLOSED");
assertTrue($service->getOrderingStatus($futureConfig, $pastNow) === OrderWindowService::STATUS_CLOSED, "Status is 'closed'");

// Test 3: Force Closed Master Switch
$forceClosedConfig = [
    'order_deadline' => '2026-10-30 23:59:00',
    'order_status_override' => 'force_closed',
    'event_date' => '2026-11-02'
];
assertTrue($service->isOrderingOpen($forceClosedConfig, $now) === false, "Force Closed master switch closes ordering even before deadline");
assertTrue($service->getOrderingStatus($forceClosedConfig, $now) === OrderWindowService::STATUS_FORCE_CLOSED, "Status is 'force_closed'");

// Test 4: Force Open Master Switch
$forceOpenConfig = [
    'order_deadline' => '2026-10-10 23:59:00',
    'order_status_override' => 'force_open',
    'event_date' => '2026-10-12'
];
assertTrue($service->isOrderingOpen($forceOpenConfig, $now) === true, "Force Open master switch allows ordering even after deadline has passed");
assertTrue($service->getOrderingStatus($forceOpenConfig, $now) === OrderWindowService::STATUS_FORCE_OPEN, "Status is 'force_open'");

// Test 5: Fallback when order_deadline is omitted
$noDeadlineConfig = [
    'order_deadline' => null,
    'order_status_override' => 'auto',
    'event_date' => '2026-10-15'
];
$todayNoon = new DateTimeImmutable('2026-10-15 12:00:00', $tz);
assertTrue($service->isOrderingOpen($noDeadlineConfig, $todayNoon) === true, "No deadline fallback: Open during event date");

$tomorrowMorning = new DateTimeImmutable('2026-10-16 08:00:00', $tz);
assertTrue($service->isOrderingOpen($noDeadlineConfig, $tomorrowMorning) === false, "No deadline fallback: Closed after event date has ended");

// Test 6: Formatted Deadline String
$testDateConfig = [
    'order_deadline' => '2026-11-05 18:00:00',
    'order_status_override' => 'auto'
];
$formattedJa = $service->getFormattedDeadline($testDateConfig, 'ja');
$formattedEn = $service->getFormattedDeadline($testDateConfig, 'en');

assertTrue(str_contains((string)$formattedJa, '2026年11月5日') && str_contains((string)$formattedJa, '18:00'), "Japanese formatted deadline contains date and time: $formattedJa");
assertTrue(str_contains((string)$formattedEn, 'Nov 5, 2026') && str_contains((string)$formattedEn, '18:00'), "English formatted deadline contains date and time: $formattedEn");

// Test 7: Seconds remaining & countdown formatting
$countdownConfig = [
    'order_deadline' => '2026-10-15 14:30:00',
    'order_status_override' => 'auto'
];
$calcNow = new DateTimeImmutable('2026-10-15 12:00:00', $tz); // 2 hours 30 mins remaining
$secondsRem = $service->getSecondsRemaining($countdownConfig, $calcNow);
assertTrue($secondsRem === 9000, "Remaining seconds calculated accurately (2.5 hours = 9000s)");

$formattedRemJa = $service->formatRemainingCountdown(9000, 'ja');
$formattedRemEn = $service->formatRemainingCountdown(9000, 'en');
assertTrue(str_contains($formattedRemJa, '2時間 30分'), "Japanese countdown format matches: $formattedRemJa");
assertTrue(str_contains($formattedRemEn, '2 hours 30 mins'), "English countdown format matches: $formattedRemEn");

// Test 8: Promotional Campaign {{order_deadline}} Token Replacement
$container = getServiceContainer();
$sendUseCase = $container->getSendPromotionalCampaignUseCase();

$testUser = new MarketingUser(
    id: 100,
    email: 'student@example.com',
    firstName: 'Hanako',
    lastName: 'Suzuki',
    phone: null,
    isConfirmed: true,
    acceptsMarketing: true,
    unsubscribeToken: 'test_deadline_token'
);

$promoSubject = "Reminder for {{first_name}}: Orders close {{order_deadline}}!";
$renderedSubject = $sendUseCase->renderSubject($promoSubject, $testUser);
assertTrue(!str_contains($renderedSubject, '{{order_deadline}}'), "{{order_deadline}} token replaced in subject line");

$promoBody = "Hello {{first_name}},\nMake sure to pre-order your pizza before {{order_deadline}}.\n{{site_url}}";
$renderedEmail = $sendUseCase->renderEmailContent($promoBody, $testUser);
assertTrue(!str_contains($renderedEmail['text'], '{{order_deadline}}'), "{{order_deadline}} token replaced in email text");
assertTrue(!str_contains($renderedEmail['html'], '{{order_deadline}}'), "{{order_deadline}} token replaced in email HTML");

// Test 9: Database schema persistence via PDO
$db = getDB();
$db->exec("
    UPDATE `site_config`
    SET `order_deadline` = '2026-11-06 20:00:00', `order_status_override` = 'auto'
    WHERE `id` = 1
");

$stmt = $db->query("SELECT `order_deadline`, `order_status_override` FROM `site_config` WHERE `id` = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
assertTrue($row['order_deadline'] === '2026-11-06 20:00:00', "order_deadline saved and verified in MySQL InnoDB");
assertTrue($row['order_status_override'] === 'auto', "order_status_override saved and verified in MySQL InnoDB");

echo "\n===========================================\n";
echo "SUMMARY: $testsPassed passed, $testsFailed failed\n";
echo "===========================================\n";

if ($testsFailed > 0) {
    exit(1);
}

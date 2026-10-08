<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../src/autoload.php';

use FidelsPizza\Application\UseCase\SendOrderNotificationUseCase;
use FidelsPizza\Domain\Model\PickupTimeSlot;
use FidelsPizza\Domain\Repository\PickupTimeSlotRepositoryInterface;
use FidelsPizza\Domain\Service\EmailSenderInterface;

echo "===========================================\n";
echo "ORDER NOTIFICATION EMAIL SYSTEM VERIFICATION\n";
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

// Mock Email Sender
class InMemoryOrderEmailSender implements EmailSenderInterface
{
    /** @var array<array{toEmail: string, toName: string, subject: string, htmlBody: string, textBody: string}> */
    public array $sentMessages = [];
    public bool $shouldFail = false;

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        if ($this->shouldFail) {
            return false;
        }

        $this->sentMessages[] = [
            'toEmail' => $toEmail,
            'toName' => $toName,
            'subject' => $subject,
            'htmlBody' => $htmlBody,
            'textBody' => $textBody,
        ];

        return true;
    }
}

// Mock Pickup Slot Repository
class MockPickupSlotRepository implements PickupTimeSlotRepositoryInterface
{
    public function findAll(int $configId = 1, ?string $eventDate = null, ?int $excludeOrderId = null): array { return []; }
    public function findActiveWithAvailability(int $configId = 1, ?string $eventDate = null, ?int $excludeOrderId = null): array { return []; }
    public function findById(int $id, ?string $eventDate = null, ?int $excludeOrderId = null): ?PickupTimeSlot {
        return new PickupTimeSlot($id, 1, 'Slot A', '12:00', '13:00', 20, 10, true, 0, 0, 0);
    }
    public function findForOrder(?int $slotId, ?string $pickupDatetime, int $configId = 1): ?PickupTimeSlot {
        return new PickupTimeSlot(1, 1, 'Slot A', '12:00', '13:00', 20, 10, true, 0, 0, 0);
    }
    public function syncSlots(int $configId, array $slots): void {}
}

// In-Memory SQLite Setup
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("
    CREATE TABLE site_config (
        id INTEGER PRIMARY KEY,
        site_title TEXT,
        event_location TEXT,
        event_date TEXT,
        admin_email TEXT,
        admin_email_2 TEXT
    );

    CREATE TABLE email_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        template_name TEXT,
        subject TEXT,
        body TEXT
    );

    CREATE TABLE admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT,
        email TEXT
    );

    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        first_name TEXT,
        last_name TEXT,
        email TEXT
    );

    CREATE TABLE orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        order_number TEXT,
        total_amount REAL,
        status TEXT,
        pickup_time TEXT,
        pickup_slot_id INTEGER
    );

    CREATE TABLE menu_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        price REAL
    );

    CREATE TABLE order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER,
        menu_item_id INTEGER,
        quantity INTEGER,
        unit_price REAL,
        subtotal REAL
    );
");

// Populate standard seed data
$pdo->exec("
    INSERT INTO site_config (id, site_title, event_location, event_date, admin_email, admin_email_2)
    VALUES (1, 'Fidel''s Pizza Event', 'Pier English School', '2026-11-02', 'admin1@fidels.com', 'admin2@fidels.com');

    INSERT INTO admins (username, email) VALUES ('fidel_admin', 'fallback_admin@fidels.com');

    INSERT INTO email_templates (template_name, subject, body) VALUES
    ('order_confirmation', 'Your Pizza Order Confirmation - #{{order_number}}', 'Hello {{first_name}},\nThank you!\nOrder #{{order_number}}\nItems:\n{{order_items}}'),
    ('admin_order_notification', 'New Pizza Order Received - #{{order_number}}', 'New order:\nCustomer: {{customer_name}} ({{customer_email}})\nOrder #{{order_number}}\nItems:\n{{order_items}}');

    INSERT INTO users (id, first_name, last_name, email)
    VALUES (1, 'Taro', 'Yamada', 'customer@example.com');

    INSERT INTO menu_items (id, name, price) VALUES
    (1, 'Margherita Pizza', 1500.00),
    (2, 'Pepperoni Pizza', 1600.00);

    INSERT INTO orders (id, user_id, order_number, total_amount, status, pickup_time, pickup_slot_id)
    VALUES (1, 1, 'PZ20261001', 3100.00, 'pending', '2026-11-02 12:00:00', 1);

    INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, subtotal) VALUES
    (1, 1, 1, 1500.00, 1500.00),
    (1, 2, 1, 1600.00, 1600.00);
");

$emailSender = new InMemoryOrderEmailSender();
$slotRepo = new MockPickupSlotRepository();
$useCase = new SendOrderNotificationUseCase($pdo, $slotRepo, $emailSender);

// TEST 1: Customer and dual admins all receive notifications
$result = $useCase->execute(1, 'created');
assertTrue($result === true, "Order notification execution returns true when customer email succeeds");
assertTrue(count($emailSender->sentMessages) === 3, "Dispatches exactly 3 emails (1 customer + 2 admins)");

$recipients = array_column($emailSender->sentMessages, 'toEmail');
assertTrue(in_array('customer@example.com', $recipients, true), "Customer received confirmation email");
assertTrue(in_array('admin1@fidels.com', $recipients, true), "Admin 1 received notification email");
assertTrue(in_array('admin2@fidels.com', $recipients, true), "Admin 2 received notification email");

// TEST 2: Email structure has both plain text and HTML components
$adminMsg = null;
foreach ($emailSender->sentMessages as $msg) {
    if ($msg['toEmail'] === 'admin1@fidels.com') {
        $adminMsg = $msg;
        break;
    }
}
assertTrue($adminMsg !== null, "Admin message captured");
assertTrue(!empty($adminMsg['htmlBody']), "Admin message contains styled HTML body");
assertTrue(str_contains($adminMsg['htmlBody'], 'New Pizza Order Received - #PZ20261001'), "HTML contains order title");
assertTrue(str_contains($adminMsg['textBody'], 'Margherita Pizza x 1'), "Plain text body contains order items");
assertTrue(str_contains($adminMsg['textBody'], 'Customer: Taro Yamada'), "Plain text body contains customer name");

// TEST 3: Admin fallback when site_config admin emails are empty
$emailSender->sentMessages = [];
$pdo->exec("UPDATE site_config SET admin_email = '', admin_email_2 = '' WHERE id = 1");

$useCase->execute(1, 'created');
$recipients = array_column($emailSender->sentMessages, 'toEmail');
assertTrue(in_array('fallback_admin@fidels.com', $recipients, true), "Falls back to admins table when site_config admin emails are empty");

// TEST 4: Email deduplication and comma-separated extraction
$testConfig = [
    'admin_email' => 'admin1@test.com, admin2@test.com',
    'admin_email_2' => 'ADMIN1@TEST.COM; admin3@test.com',
];
$extracted = SendOrderNotificationUseCase::extractAdminEmails($testConfig);
assertTrue(count($extracted) === 3, "Extracts, normalizes, and deduplicates multiple emails across fields");
assertTrue($extracted === ['admin1@test.com', 'admin2@test.com', 'admin3@test.com'], "Expected deduplicated list matches");

// TEST 5: Graceful non-throwing behavior if email delivery fails
$emailSender->sentMessages = [];
$emailSender->shouldFail = true;
$failResult = $useCase->execute(1, 'created');
assertTrue($failResult === false, "Returns false if customer delivery fails, without uncaught exceptions");

echo "\n===========================================\n";
echo "SUMMARY: $testsPassed passed, $testsFailed failed\n";
echo "===========================================\n";

if ($testsFailed > 0) {
    exit(1);
}

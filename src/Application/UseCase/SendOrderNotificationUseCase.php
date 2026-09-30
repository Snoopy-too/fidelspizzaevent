<?php
declare(strict_types=1);

namespace FidelsPizza\Application\UseCase;

use FidelsPizza\Domain\Repository\PickupTimeSlotRepositoryInterface;
use FidelsPizza\Domain\Service\EmailSenderInterface;
use PDO;
use PDOException;

final class SendOrderNotificationUseCase
{
    private static bool $schemaEnsured = false;

    public function __construct(
        private readonly PDO $pdo,
        private readonly PickupTimeSlotRepositoryInterface $slotRepository,
        private readonly EmailSenderInterface $emailSender
    ) {
        $this->ensureNotificationSchema();
    }

    private function ensureNotificationSchema(): void
    {
        if (self::$schemaEnsured || $this->pdo->inTransaction()) {
            return;
        }

        try {
            $colCheck = $this->pdo->query("SHOW COLUMNS FROM `site_config` LIKE 'admin_email_2'");
            if ($colCheck !== false && $colCheck->rowCount() === 0) {
                $this->pdo->exec("
                    ALTER TABLE `site_config`
                        ADD COLUMN `admin_email_2` VARCHAR(255) DEFAULT NULL AFTER `admin_email`;
                ");
            }

            // Ensure cancellation email templates exist in email_templates
            $tplCheck = $this->pdo->prepare("SELECT COUNT(*) FROM `email_templates` WHERE `template_name` = ?");
            $tplCheck->execute(['order_cancelled']);
            if ((int)$tplCheck->fetchColumn() === 0) {
                $ins = $this->pdo->prepare("
                    INSERT INTO `email_templates` (`template_name`, `subject`, `body`)
                    VALUES (?, ?, ?)
                ");
                $ins->execute([
                    'order_cancelled',
                    'Your Pizza Order #{{order_number}} Has Been Cancelled',
                    "Hello {{first_name}},\n\nYour pizza order #{{order_number}} has been cancelled.\n\nOrder Number: {{order_number}}\nPickup Time: {{pickup_time}}\nCancelled Items:\n{{order_items}}\n\nIf you did not request this cancellation or have any questions, please contact us.\n\nBest regards,\nFidel's Pizza Team"
                ]);
            }

            $tplCheck->execute(['admin_order_cancelled_notification']);
            if ((int)$tplCheck->fetchColumn() === 0) {
                $ins = $this->pdo->prepare("
                    INSERT INTO `email_templates` (`template_name`, `subject`, `body`)
                    VALUES (?, ?, ?)
                ");
                $ins->execute([
                    'admin_order_cancelled_notification',
                    'Order Cancelled - #{{order_number}}',
                    "A pizza order has been cancelled:\n\nOrder Number: {{order_number}}\nCustomer: {{customer_name}} ({{customer_email}})\nPickup Time: {{pickup_time}}\nTotal Amount: ¥{{total_amount}}\n\nOrder Details:\n{{order_items}}\n\nPlease log in to the admin panel to view your updated schedule."
                ]);
            }

            self::$schemaEnsured = true;
        } catch (PDOException) {
            // Ignore if DB lacks DDL permissions
        }
    }

    /**
     * Send customer and administrator notifications for an order event.
     *
     * @param int $orderId
     * @param string $eventType One of: 'created', 'updated', 'cancelled'
     * @param string|null $overrideItemsText Optional pre-formatted items string (e.g. if items were removed on cancel)
     * @return bool True if customer email was sent successfully
     */
    public function execute(int $orderId, string $eventType = 'created', ?string $overrideItemsText = null): bool
    {
        // Fetch fresh site_config directly from DB so both admin_email and admin_email_2 are always current
        $cfgStmt = $this->pdo->query("SELECT * FROM `site_config` ORDER BY `id` DESC LIMIT 1");
        $config = $cfgStmt ? ($cfgStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];

        // Fetch order and customer info
        $stmt = $this->pdo->prepare("
            SELECT o.*, u.first_name, u.last_name, u.email
            FROM `orders` o
            JOIN `users` u ON o.`user_id` = u.`id`
            WHERE o.`id` = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return false;
        }

        // Format order items
        if ($overrideItemsText !== null && trim($overrideItemsText) !== '') {
            $itemsString = $overrideItemsText;
        } else {
            $itemStmt = $this->pdo->prepare("
                SELECT mi.`name`, oi.`quantity`, oi.`subtotal`
                FROM `order_items` oi
                JOIN `menu_items` mi ON oi.`menu_item_id` = mi.`id`
                WHERE oi.`order_id` = ?
            ");
            $itemStmt->execute([$orderId]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            $lines = [];
            foreach ($items as $item) {
                $lines[] = sprintf(
                    '- %s x %d (¥%s)',
                    (string)$item['name'],
                    (int)$item['quantity'],
                    number_format((float)$item['subtotal'], 0)
                );
            }
            $itemsString = !empty($lines) ? implode("\n", $lines) : 'No items in this order.';
        }

        // Resolve pickup time display (including start and end time range if slot is found)
        $pickupDisplay = 'N/A';
        $pickupSlotId = isset($order['pickup_slot_id']) ? (int)$order['pickup_slot_id'] : null;
        $pickupDatetime = isset($order['pickup_time']) ? (string)$order['pickup_time'] : null;

        $matchedSlot = $this->slotRepository->findForOrder($pickupSlotId, $pickupDatetime, 1);
        if ($pickupDatetime !== null && trim($pickupDatetime) !== '') {
            $ts = strtotime($pickupDatetime);
            $datePart = $ts !== false ? date('Y-m-d', $ts) : (string)($config['event_date'] ?? '');
            if ($matchedSlot !== null) {
                $pickupDisplay = trim($datePart . ' ' . $matchedSlot->getDisplayName());
            } elseif ($ts !== false) {
                $pickupDisplay = date('Y-m-d H:i', $ts);
            }
        } elseif ($matchedSlot !== null) {
            $pickupDisplay = $matchedSlot->getDisplayName();
        }

        $placeholders = [
            'first_name' => (string)$order['first_name'],
            'last_name' => (string)$order['last_name'],
            'customer_name' => trim((string)$order['first_name'] . ' ' . (string)$order['last_name']),
            'customer_email' => (string)$order['email'],
            'order_number' => (string)$order['order_number'],
            'total_amount' => number_format((float)$order['total_amount'], 0),
            'event_date' => (string)($config['event_date'] ?? 'N/A'),
            'event_location' => (string)($config['event_location'] ?? 'N/A'),
            'pickup_time' => $pickupDisplay,
            'order_items' => $itemsString,
        ];

        // Determine template names based on eventType
        $normalizedType = strtolower(trim($eventType));
        if ($normalizedType === 'cancelled' || $normalizedType === 'canceled') {
            $customerTemplateName = 'order_cancelled';
            $adminTemplateName = 'admin_order_cancelled_notification';
        } elseif ($normalizedType === 'updated' || $normalizedType === 'update') {
            $customerTemplateName = 'order_updated';
            $adminTemplateName = 'admin_order_updated_notification';
        } else {
            $customerTemplateName = 'order_confirmation';
            $adminTemplateName = 'admin_order_notification';
        }

        // 1. Send email to Customer
        $customerEmail = trim((string)$order['email']);
        $customerSent = false;
        if (filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $customerTpl = $this->getTemplateWithFallback($customerTemplateName);
            $hasPickupPlaceholder = str_contains($customerTpl['body'], '{{pickup_time}}');
            $subject = $this->interpolate($customerTpl['subject'], $placeholders);
            $body = $this->interpolate($customerTpl['body'], $placeholders);
            if (!$hasPickupPlaceholder && $pickupDisplay !== 'N/A') {
                $body .= "\n\nPickup Time: " . $pickupDisplay;
            }
            $customerSent = $this->deliverPlainEmail(
                $customerEmail,
                $placeholders['customer_name'],
                $subject,
                $body
            );
        }

        // 2. Send email to all configured Administrator Emails (admin_email and admin_email_2)
        $adminEmails = self::extractAdminEmails($config);
        if (!empty($adminEmails)) {
            $adminTpl = $this->getTemplateWithFallback($adminTemplateName);
            $hasPickupPlaceholder = str_contains($adminTpl['body'], '{{pickup_time}}');
            $adminSubject = $this->interpolate($adminTpl['subject'], $placeholders);
            $adminBody = $this->interpolate($adminTpl['body'], $placeholders);
            if (!$hasPickupPlaceholder && $pickupDisplay !== 'N/A') {
                $adminBody .= "\n\nPickup Time: " . $pickupDisplay;
            }

            foreach ($adminEmails as $adminEmail) {
                $this->deliverPlainEmail($adminEmail, 'Administrator', $adminSubject, $adminBody);
            }
        }

        return $customerSent;
    }

    /**
     * Extract, validate, and deduplicate administrator notification emails from site_config.
     *
     * @param array<string, mixed> $config
     * @return string[]
     */
    public static function extractAdminEmails(array $config): array
    {
        $rawCandidates = [
            (string)($config['admin_email'] ?? ''),
            (string)($config['admin_email_2'] ?? ''),
        ];

        $validEmails = [];
        $seenLower = [];

        foreach ($rawCandidates as $rawGroup) {
            if (trim($rawGroup) === '') {
                continue;
            }
            $parts = preg_split('/[,;\s]+/', $rawGroup) ?: [];
            foreach ($parts as $part) {
                $clean = trim($part);
                if ($clean === '' || !filter_var($clean, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $lower = strtolower($clean);
                if (!isset($seenLower[$lower])) {
                    $seenLower[$lower] = true;
                    $validEmails[] = $clean;
                }
            }
        }

        return $validEmails;
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function getTemplateWithFallback(string $templateName): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT `subject`, `body` FROM `email_templates` WHERE `template_name` = ? LIMIT 1");
            $stmt->execute([$templateName]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['subject']) && !empty($row['body'])) {
                return [
                    'subject' => (string)$row['subject'],
                    'body' => str_replace('\n', "\n", (string)$row['body']),
                ];
            }
        } catch (PDOException) {
            // Fall through to built-in templates
        }

        return match ($templateName) {
            'order_updated' => [
                'subject' => 'Your Pizza Order #{{order_number}} Has Been Updated',
                'body' => "Hello {{first_name}},\n\nThis is a confirmation that your pizza order has been updated.\n\nOrder Number: {{order_number}}\nPickup Time: {{pickup_time}}\nNew Total Amount: ¥{{total_amount}}\n\nUpdated Order Details:\n{{order_items}}\n\nBest regards,\nFidel's Pizza Team",
            ],
            'admin_order_updated_notification' => [
                'subject' => 'Order Updated - #{{order_number}}',
                'body' => "An existing pizza order has been updated:\n\nOrder Number: {{order_number}}\nCustomer: {{customer_name}} ({{customer_email}})\nPickup Time: {{pickup_time}}\nNew Total Amount: ¥{{total_amount}}\n\nUpdated Order Details:\n{{order_items}}\n\nPlease log in to the admin panel to review this order.",
            ],
            'order_cancelled' => [
                'subject' => 'Your Pizza Order #{{order_number}} Has Been Cancelled',
                'body' => "Hello {{first_name}},\n\nYour pizza order #{{order_number}} has been cancelled.\n\nOrder Number: {{order_number}}\nPickup Time: {{pickup_time}}\nCancelled Items:\n{{order_items}}\n\nBest regards,\nFidel's Pizza Team",
            ],
            'admin_order_cancelled_notification' => [
                'subject' => 'Order Cancelled - #{{order_number}}',
                'body' => "A pizza order has been cancelled:\n\nOrder Number: {{order_number}}\nCustomer: {{customer_name}} ({{customer_email}})\nPickup Time: {{pickup_time}}\nTotal Amount: ¥{{total_amount}}\n\nOrder Details:\n{{order_items}}\n\nPlease log in to the admin panel to view your updated schedule.",
            ],
            'admin_order_notification' => [
                'subject' => 'New Pizza Order Received - #{{order_number}}',
                'body' => "A new pizza order has been received:\n\nOrder Number: {{order_number}}\nCustomer: {{customer_name}} ({{customer_email}})\nPickup Time: {{pickup_time}}\nTotal Amount: ¥{{total_amount}}\n\nOrder Details:\n{{order_items}}\n\nPlease log in to the admin panel to manage this order.",
            ],
            default => [
                'subject' => 'Your Pizza Order Confirmation - #{{order_number}}',
                'body' => "Hello {{first_name}},\n\nThank you for your pizza order!\n\nOrder Number: {{order_number}}\nPickup Time: {{pickup_time}}\nTotal Amount: ¥{{total_amount}}\nEvent Date: {{event_date}}\nLocation: {{event_location}}\n\nOrder Details:\n{{order_items}}\n\nPlease bring your order number and ¥{{total_amount}} when picking up your pizza.\n\nBest regards,\nFidel's Pizza Team",
            ],
        };
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

    private function deliverPlainEmail(string $toEmail, string $toName, string $subject, string $body): bool
    {
        $safeTo = str_replace(["\r", "\n"], '', trim($toEmail));
        $safeSubject = str_replace(["\r", "\n"], '', trim($subject));

        if (function_exists('sendEmail')) {
            return (bool)@sendEmail($safeTo, $safeSubject, $body);
        }

        try {
            $htmlBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
            return $this->emailSender->send($safeTo, $toName, $safeSubject, $htmlBody, $body);
        } catch (\Throwable) {
            return false;
        }
    }
}

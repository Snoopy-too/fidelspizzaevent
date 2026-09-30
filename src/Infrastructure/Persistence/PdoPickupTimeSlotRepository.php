<?php
declare(strict_types=1);

namespace FidelsPizza\Infrastructure\Persistence;

use FidelsPizza\Domain\Model\PickupTimeSlot;
use FidelsPizza\Domain\Repository\PickupTimeSlotRepositoryInterface;
use PDO;
use PDOException;

final class PdoPickupTimeSlotRepository implements PickupTimeSlotRepositoryInterface
{
    private static bool $schemaEnsured = false;

    public function __construct(
        private readonly PDO $pdo
    ) {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        if (self::$schemaEnsured || $this->pdo->inTransaction()) {
            return;
        }

        try {
            $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'pickup_time_slots'");
            $tableExists = $tableCheck !== false && $tableCheck->rowCount() > 0;

            if (!$tableExists) {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS `pickup_time_slots` (
                        `id` INT(11) NOT NULL AUTO_INCREMENT,
                        `config_id` INT(11) NOT NULL DEFAULT 1,
                        `label` VARCHAR(100) NOT NULL DEFAULT '',
                        `slot_time` TIME NOT NULL,
                        `max_pizzas` INT(11) DEFAULT NULL,
                        `max_orders` INT(11) DEFAULT NULL,
                        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                        `sort_order` INT(11) NOT NULL DEFAULT 0,
                        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `idx_pts_config` (`config_id`),
                        KEY `idx_pts_active_sort` (`is_active`, `sort_order`, `slot_time`),
                        CONSTRAINT `fk_pickup_time_slots_config`
                            FOREIGN KEY (`config_id`) REFERENCES `site_config` (`id`)
                            ON DELETE CASCADE ON UPDATE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");

                $stmt = $this->pdo->prepare("
                    INSERT INTO `pickup_time_slots` (`config_id`, `label`, `slot_time`, `max_pizzas`, `max_orders`, `is_active`, `sort_order`)
                    VALUES
                        (1, 'Pickup Time A', '11:30:00', NULL, NULL, 1, 0),
                        (1, 'Pickup Time B', '12:30:00', NULL, NULL, 1, 1),
                        (1, 'Pickup Time C', '13:30:00', NULL, NULL, 1, 2)
                ");
                $stmt->execute();
            }

            $colCheck = $this->pdo->query("SHOW COLUMNS FROM `orders` LIKE 'pickup_slot_id'");
            if ($colCheck !== false && $colCheck->rowCount() === 0) {
                $this->pdo->exec("
                    ALTER TABLE `orders`
                        ADD COLUMN `pickup_slot_id` INT(11) DEFAULT NULL AFTER `pickup_time`,
                        ADD KEY `idx_orders_pickup_slot` (`pickup_slot_id`),
                        ADD CONSTRAINT `fk_orders_pickup_slot`
                            FOREIGN KEY (`pickup_slot_id`) REFERENCES `pickup_time_slots` (`id`)
                            ON DELETE SET NULL ON UPDATE CASCADE;
                ");
            }

            self::$schemaEnsured = true;
        } catch (PDOException) {
            // Ignore if DB driver does not support SHOW TABLES or lacks DDL privilege
        }
    }

    public function findAll(int $configId = 1, ?string $eventDate = null, ?int $excludeOrderId = null): array
    {
        return $this->querySlots($configId, false, $eventDate, $excludeOrderId);
    }

    public function findActiveWithAvailability(int $configId = 1, ?string $eventDate = null, ?int $excludeOrderId = null): array
    {
        return $this->querySlots($configId, true, $eventDate, $excludeOrderId);
    }

    public function findById(int $id, ?string $eventDate = null, ?int $excludeOrderId = null): ?PickupTimeSlot
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM `pickup_time_slots` WHERE `id` = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            $stats = $this->fetchBookingStats((int)$row['config_id'], $eventDate, $excludeOrderId);
            $slotId = (int)$row['id'];
            $timeKey = substr((string)$row['slot_time'], 0, 5);

            $bookedOrders = $stats['by_slot_or_time'][$slotId]['orders']
                ?? $stats['by_time'][$timeKey]['orders']
                ?? 0;
            $bookedPizzas = $stats['by_slot_or_time'][$slotId]['pizzas']
                ?? $stats['by_time'][$timeKey]['pizzas']
                ?? 0;

            return $this->hydrate($row, $bookedPizzas, $bookedOrders);
        } catch (PDOException) {
            return null;
        }
    }

    public function findForOrder(?int $slotId, ?string $pickupDatetime, int $configId = 1): ?PickupTimeSlot
    {
        try {
            if ($slotId !== null && $slotId > 0) {
                $slot = $this->findById($slotId);
                if ($slot !== null) {
                    return $slot;
                }
            }

            if ($pickupDatetime !== null && trim($pickupDatetime) !== '') {
                $timestamp = strtotime($pickupDatetime);
                if ($timestamp !== false) {
                    $timeString = date('H:i:00', $timestamp);
                    $stmt = $this->pdo->prepare("
                        SELECT * FROM `pickup_time_slots`
                        WHERE `config_id` = ? AND `slot_time` = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$configId, $timeString]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        return $this->hydrate($row, 0, 0);
                    }
                }
            }
        } catch (PDOException) {
            return null;
        }

        return null;
    }

    public function syncSlots(int $configId, array $slots): void
    {
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmt = $this->pdo->prepare("SELECT `id` FROM `pickup_time_slots` WHERE `config_id` = ?");
            $stmt->execute([$configId]);
            $existingIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            $keptIds = [];

            $updateStmt = $this->pdo->prepare("
                UPDATE `pickup_time_slots`
                SET `label` = ?, `slot_time` = ?, `max_pizzas` = ?, `max_orders` = ?, `is_active` = ?, `sort_order` = ?
                WHERE `id` = ? AND `config_id` = ?
            ");

            $insertStmt = $this->pdo->prepare("
                INSERT INTO `pickup_time_slots`
                    (`config_id`, `label`, `slot_time`, `max_pizzas`, `max_orders`, `is_active`, `sort_order`)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($slots as $slot) {
                $slotId = $slot->getId();
                if ($slotId !== null && in_array($slotId, $existingIds, true)) {
                    $updateStmt->execute([
                        $slot->getLabel(),
                        $slot->getSlotTimeForDb(),
                        $slot->getMaxPizzas(),
                        $slot->getMaxOrders(),
                        $slot->isActive() ? 1 : 0,
                        $slot->getSortOrder(),
                        $slotId,
                        $configId,
                    ]);
                    $keptIds[] = $slotId;
                } else {
                    $insertStmt->execute([
                        $configId,
                        $slot->getLabel(),
                        $slot->getSlotTimeForDb(),
                        $slot->getMaxPizzas(),
                        $slot->getMaxOrders(),
                        $slot->isActive() ? 1 : 0,
                        $slot->getSortOrder(),
                    ]);
                    $keptIds[] = (int)$this->pdo->lastInsertId();
                }
            }

            $idsToDelete = array_diff($existingIds, $keptIds);
            if (!empty($idsToDelete)) {
                $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
                $deleteStmt = $this->pdo->prepare(
                    "DELETE FROM `pickup_time_slots` WHERE `config_id` = ? AND `id` IN ($placeholders)"
                );
                $deleteStmt->execute(array_merge([$configId], array_values($idsToDelete)));
            }

            if ($ownTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return PickupTimeSlot[]
     */
    private function querySlots(
        int $configId,
        bool $onlyActive,
        ?string $eventDate,
        ?int $excludeOrderId
    ): array {
        try {
            $sql = "SELECT * FROM `pickup_time_slots` WHERE `config_id` = ?";
            if ($onlyActive) {
                $sql .= " AND `is_active` = 1";
            }
            $sql .= " ORDER BY `sort_order` ASC, `slot_time` ASC, `id` ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$configId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return [];
            }

            $stats = $this->fetchBookingStats($configId, $eventDate, $excludeOrderId);

            $result = [];
            foreach ($rows as $row) {
                $slotId = (int)$row['id'];
                $timeKey = substr((string)$row['slot_time'], 0, 5);

                $bookedOrders = $stats['by_slot_or_time'][$slotId]['orders']
                    ?? $stats['by_time'][$timeKey]['orders']
                    ?? 0;
                $bookedPizzas = $stats['by_slot_or_time'][$slotId]['pizzas']
                    ?? $stats['by_time'][$timeKey]['pizzas']
                    ?? 0;

                $result[] = $this->hydrate($row, $bookedPizzas, $bookedOrders);
            }

            return $result;
        } catch (PDOException) {
            // Gracefully return empty array if table has not been migrated yet
            return [];
        }
    }

    /**
     * Calculate booked orders and pizzas per time slot for the active event date.
     *
     * @return array{
     *   by_time: array<string, array{orders: int, pizzas: int}>,
     *   by_slot_or_time: array<int, array{orders: int, pizzas: int}>
     * }
     */
    private function fetchBookingStats(int $configId, ?string $eventDate, ?int $excludeOrderId): array
    {
        $byTime = [];
        $bySlotOrTime = [];

        $cleanDate = $eventDate !== null ? trim($eventDate) : '';
        if ($cleanDate === '') {
            $cfgStmt = $this->pdo->prepare("SELECT `event_date` FROM `site_config` WHERE `id` = ? LIMIT 1");
            $cfgStmt->execute([$configId]);
            $cleanDate = trim((string)($cfgStmt->fetchColumn() ?: ''));
        }

        $params = [];
        $where = ["o.`status` NOT IN ('cancelled', 'archived')", "o.`pickup_time` IS NOT NULL"];

        if ($cleanDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $cleanDate)) {
            $where[] = "DATE(o.`pickup_time`) = ?";
            $params[] = $cleanDate;
        }

        if ($excludeOrderId !== null && $excludeOrderId > 0) {
            $where[] = "o.`id` != ?";
            $params[] = $excludeOrderId;
        }

        $whereSql = implode(' AND ', $where);
        $sql = "
            SELECT
                DATE_FORMAT(o.`pickup_time`, '%H:%i') AS `time_key`,
                COUNT(DISTINCT o.`id`) AS `order_count`,
                COALESCE(SUM(oi.`quantity`), 0) AS `pizza_count`
            FROM `orders` o
            LEFT JOIN `order_items` oi ON o.`id` = oi.`order_id`
            WHERE $whereSql
            GROUP BY DATE_FORMAT(o.`pickup_time`, '%H:%i')
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $timeKey = (string)$row['time_key'];
            $byTime[$timeKey] = [
                'orders' => (int)$row['order_count'],
                'pizzas' => (int)$row['pizza_count'],
            ];
        }

        return [
            'by_time' => $byTime,
            'by_slot_or_time' => $bySlotOrTime,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row, int $bookedPizzas, int $bookedOrders): PickupTimeSlot
    {
        return new PickupTimeSlot(
            id: (int)$row['id'],
            configId: (int)$row['config_id'],
            label: (string)($row['label'] ?? ''),
            slotTime: substr((string)$row['slot_time'], 0, 5),
            maxPizzas: $row['max_pizzas'] !== null ? (int)$row['max_pizzas'] : null,
            maxOrders: $row['max_orders'] !== null ? (int)$row['max_orders'] : null,
            isActive: (bool)$row['is_active'],
            sortOrder: (int)$row['sort_order'],
            bookedPizzas: $bookedPizzas,
            bookedOrders: $bookedOrders,
            createdAt: isset($row['created_at']) ? (string)$row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string)$row['updated_at'] : null
        );
    }
}

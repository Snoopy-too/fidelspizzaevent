<?php
declare(strict_types=1);

namespace FidelsPizza\Infrastructure\Persistence;

use FidelsPizza\Domain\Model\CustomerOrder;
use FidelsPizza\Domain\Repository\OrderRepositoryInterface;
use Override;
use PDO;

final class PdoOrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    #[Override]
    public function getActiveMenuItems(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM menu_items WHERE is_active = 1 ORDER BY sort_order, name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    #[Override]
    public function findOrderByIdAndUserId(int $orderId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND status IN ('pending', 'confirmed') LIMIT 1");
        $stmt->execute([$orderId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    #[Override]
    public function findOrderItemsByOrderId(int $orderId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT oi.id AS order_item_id, oi.quantity, oi.unit_price, oi.subtotal, mi.id AS menu_item_id, mi.name, mi.price
            FROM order_items oi
            JOIN menu_items mi ON oi.menu_item_id = mi.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    #[Override]
    public function getUserEmail(int $userId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $email = $stmt->fetchColumn();
        return $email !== false ? (string)$email : null;
    }

    #[Override]
    public function saveOrder(CustomerOrder $order): int
    {
        $startedTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $hasPickupSlotCol = false;
            try {
                $colCheck = $this->pdo->query("SHOW COLUMNS FROM `orders` LIKE 'pickup_slot_id'");
                $hasPickupSlotCol = ($colCheck && $colCheck->rowCount() > 0);
            } catch (\Throwable) {
                // Ignore schema column check failure on SQLite or restricted DB
                $hasPickupSlotCol = false;
            }

            if ($hasPickupSlotCol) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO orders (user_id, order_number, total_amount, status, notes, pickup_time, pickup_slot_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $order->userId,
                    $order->orderNumber,
                    $order->totalAmount,
                    $order->status,
                    $order->notes,
                    $order->pickupTime,
                    $order->pickupSlotId
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO orders (user_id, order_number, total_amount, status, notes, pickup_time, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $order->userId,
                    $order->orderNumber,
                    $order->totalAmount,
                    $order->status,
                    $order->notes,
                    $order->pickupTime
                ]);
            }

            $orderId = (int)$this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare("
                INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($order->items as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item->menuItemId,
                    $item->quantity,
                    $item->unitPrice,
                    $item->subtotal
                ]);
            }

            if ($startedTransaction) {
                $this->pdo->commit();
            }

            return $orderId;
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    #[Override]
    public function updateOrder(
        int $orderId,
        float $totalAmount,
        ?string $pickupTime,
        ?int $pickupSlotId,
        array $itemsToUpdate,
        array $itemsToDelete
    ): void {
        $startedTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            // 1. Delete removed items
            if (!empty($itemsToDelete)) {
                $deleteStmt = $this->pdo->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?");
                foreach ($itemsToDelete as $delId) {
                    $deleteStmt->execute([$delId, $orderId]);
                }
            }

            // 2. Update item quantities and subtotals
            if (!empty($itemsToUpdate)) {
                $updateItemStmt = $this->pdo->prepare("UPDATE order_items SET quantity = ?, subtotal = ? WHERE id = ? AND order_id = ?");
                foreach ($itemsToUpdate as $upd) {
                    $updateItemStmt->execute([$upd['quantity'], $upd['subtotal'], $upd['order_item_id'], $orderId]);
                }
            }

            // 3. Update orders table
            $hasPickupSlotCol = false;
            try {
                $colCheck = $this->pdo->query("SHOW COLUMNS FROM `orders` LIKE 'pickup_slot_id'");
                $hasPickupSlotCol = ($colCheck && $colCheck->rowCount() > 0);
            } catch (\Throwable) {
                $hasPickupSlotCol = false;
            }

            if ($hasPickupSlotCol) {
                $orderStmt = $this->pdo->prepare("UPDATE orders SET total_amount = ?, pickup_time = ?, pickup_slot_id = ?, updated_at = NOW() WHERE id = ?");
                $orderStmt->execute([$totalAmount, $pickupTime, $pickupSlotId, $orderId]);
            } else {
                $orderStmt = $this->pdo->prepare("UPDATE orders SET total_amount = ?, pickup_time = ?, updated_at = NOW() WHERE id = ?");
                $orderStmt->execute([$totalAmount, $pickupTime, $orderId]);
            }

            if ($startedTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    #[Override]
    public function cancelOrder(int $orderId): void
    {
        $stmt = $this->pdo->prepare("UPDATE orders SET total_amount = 0, status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$orderId]);
    }
}

<?php
declare(strict_types=1);

namespace FidelsPizza\Infrastructure\Persistence;

use FidelsPizza\Domain\Model\CompedOrder;
use FidelsPizza\Domain\Model\CompedOrderItem;
use FidelsPizza\Domain\Repository\CompedOrderRepositoryInterface;
use PDO;

final class PdoCompedOrderRepository implements CompedOrderRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function save(CompedOrder $order): int
    {
        $startedTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTransaction = true;
        }

        $now = date('Y-m-d H:i:s');

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO `orders` (
                    `user_id`, 
                    `order_number`, 
                    `total_amount`, 
                    `status`, 
                    `notes`, 
                    `pickup_time`, 
                    `pickup_slot_id`, 
                    `created_at`
                ) VALUES (?, ?, ?, 'comped', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $order->getUserId(),
                $order->getOrderNumber(),
                0.0,
                $order->getNotes(),
                $order->getPickupTime(),
                $order->getPickupSlotId(),
                $now
            ]);

            $orderId = (int)$this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare("
                INSERT INTO `order_items` (
                    `order_id`, 
                    `menu_item_id`, 
                    `quantity`, 
                    `unit_price`, 
                    `subtotal`
                ) VALUES (?, ?, ?, 0.00, 0.00)
            ");

            foreach ($order->getItems() as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item->getMenuItemId(),
                    $item->getQuantity()
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

    public function resolveAdminUserId(int $adminId): int
    {
        // 1. Fetch admin record
        $stmt = $this->pdo->prepare("SELECT id, username, email FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            throw new \RuntimeException("Admin record #$adminId not found.");
        }

        $email = trim((string)$admin['email']);
        $username = trim((string)$admin['username']);

        // 2. Check if a user already exists with this email
        $userStmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $userStmt->execute([$email]);
        $existingUserId = $userStmt->fetchColumn();

        if ($existingUserId) {
            return (int)$existingUserId;
        }

        // 3. Provision a user record for the admin to satisfy InnoDB Foreign Key constraints
        $now = date('Y-m-d H:i:s');
        $insertUser = $this->pdo->prepare("
            INSERT INTO users (
                email, 
                password_hash, 
                first_name, 
                last_name, 
                phone, 
                is_confirmed, 
                accepts_marketing, 
                created_at
            ) VALUES (?, '', ?, '(Admin)', '', 1, 0, ?)
        ");
        $insertUser->execute([
            $email,
            $username,
            $now
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function getCompedOrders(?string $eventDate = null): array
    {
        $params = [];
        $whereSql = "WHERE o.status = 'comped'";

        if ($eventDate !== null && $eventDate !== '' && $eventDate !== 'all') {
            $whereSql .= " AND (DATE(o.pickup_time) = ? OR o.pickup_time IS NULL)";
            $params[] = $eventDate;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                o.id,
                o.user_id,
                o.order_number,
                o.total_amount,
                o.status,
                o.notes,
                o.pickup_time,
                o.pickup_slot_id,
                o.created_at,
                u.first_name,
                u.last_name,
                u.email
            FROM orders o
            JOIN users u ON o.user_id = u.id
            $whereSql
            ORDER BY o.created_at DESC, o.id DESC
        ");
        $stmt->execute($params);
        $orderRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($orderRows)) {
            return [];
        }

        // Fetch order items for these orders
        $orderIds = array_column($orderRows, 'id');
        $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';

        $itemsStmt = $this->pdo->prepare("
            SELECT 
                oi.order_id,
                oi.menu_item_id,
                oi.quantity,
                mi.name
            FROM order_items oi
            JOIN menu_items mi ON oi.menu_item_id = mi.id
            WHERE oi.order_id IN ($placeholders)
            ORDER BY oi.id ASC
        ");
        $itemsStmt->execute($orderIds);
        $itemRows = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $itemsByOrder = [];
        foreach ($itemRows as $row) {
            $itemsByOrder[(int)$row['order_id']][] = new CompedOrderItem(
                (int)$row['menu_item_id'],
                (string)$row['name'],
                (int)$row['quantity']
            );
        }

        $orders = [];
        foreach ($orderRows as $row) {
            $orderId = (int)$row['id'];
            $orders[] = new CompedOrder(
                id: $orderId,
                userId: (int)$row['user_id'],
                orderNumber: (string)$row['order_number'],
                totalAmount: (float)$row['total_amount'],
                status: (string)$row['status'],
                notes: $row['notes'] !== null ? (string)$row['notes'] : null,
                pickupTime: $row['pickup_time'] !== null ? (string)$row['pickup_time'] : null,
                pickupSlotId: $row['pickup_slot_id'] !== null ? (int)$row['pickup_slot_id'] : null,
                createdAt: (string)$row['created_at'],
                adminName: trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
                adminEmail: (string)$row['email'],
                items: $itemsByOrder[$orderId] ?? []
            );
        }

        return $orders;
    }

    public function getCompedSummary(?string $eventDate = null): array
    {
        $params = [];
        $whereSql = "WHERE o.status = 'comped'";

        if ($eventDate !== null && $eventDate !== '' && $eventDate !== 'all') {
            $whereSql .= " AND (DATE(o.pickup_time) = ? OR o.pickup_time IS NULL)";
            $params[] = $eventDate;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT o.id) AS total_orders,
                COALESCE(SUM(oi.quantity), 0) AS total_pizzas
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            $whereSql
        ");
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        $breakdownStmt = $this->pdo->prepare("
            SELECT 
                mi.name,
                COALESCE(SUM(oi.quantity), 0) AS item_qty
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN menu_items mi ON oi.menu_item_id = mi.id
            $whereSql
            GROUP BY mi.id, mi.name
            ORDER BY item_qty DESC
        ");
        $breakdownStmt->execute($params);
        $breakdownRows = $breakdownStmt->fetchAll(PDO::FETCH_ASSOC);

        $breakdown = [];
        foreach ($breakdownRows as $row) {
            $breakdown[(string)$row['name']] = (int)$row['item_qty'];
        }

        return [
            'total_comped_pizzas' => (int)($summary['total_pizzas'] ?? 0),
            'total_comped_orders' => (int)($summary['total_orders'] ?? 0),
            'items_breakdown'     => $breakdown
        ];
    }

    public function getActiveMenuItems(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, name, price, image_path 
            FROM menu_items 
            WHERE is_active = 1 
            ORDER BY sort_order ASC, id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class CompedOrder
{
    /**
     * @param int|null $id
     * @param int $userId
     * @param string $orderNumber
     * @param float $totalAmount
     * @param string $status
     * @param string|null $notes
     * @param string|null $pickupTime
     * @param int|null $pickupSlotId
     * @param string|null $createdAt
     * @param string|null $adminName
     * @param string|null $adminEmail
     * @param array<CompedOrderItem> $items
     */
    public function __construct(
        public private(set) ?int $id,
        public private(set) int $userId,
        public private(set) string $orderNumber,
        public private(set) float $totalAmount,
        public private(set) string $status,
        public private(set) ?string $notes,
        public private(set) ?string $pickupTime,
        public private(set) ?int $pickupSlotId,
        public private(set) ?string $createdAt = null,
        public private(set) ?string $adminName = null,
        public private(set) ?string $adminEmail = null,
        public private(set) array $items = []
    ) {
    }

    /**
     * PHP 8.5 Property Hook: Total pizzas calculation
     */
    public int $totalPizzas {
        get => $this->getTotalPizzas();
    }

    /**
     * PHP 8.5 Property Hook: Items summary string
     */
    public string $itemsSummary {
        get => $this->getItemsSummary();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getPickupTime(): ?string
    {
        return $this->pickupTime;
    }

    public function getPickupSlotId(): ?int
    {
        return $this->pickupSlotId;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getAdminName(): ?string
    {
        return $this->adminName;
    }

    public function getAdminEmail(): ?string
    {
        return $this->adminEmail;
    }

    /**
     * @return array<CompedOrderItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotalPizzas(): int
    {
        $sum = 0;
        foreach ($this->items as $item) {
            $sum += $item->quantity;
        }
        return $sum;
    }

    public function getItemsSummary(): string
    {
        $parts = [];
        foreach ($this->items as $item) {
            $parts[] = $item->name . ' x' . $item->quantity;
        }
        return implode(', ', $parts);
    }
}

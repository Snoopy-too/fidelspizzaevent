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
        private readonly ?int $id,
        private readonly int $userId,
        private readonly string $orderNumber,
        private readonly float $totalAmount,
        private readonly string $status,
        private readonly ?string $notes,
        private readonly ?string $pickupTime,
        private readonly ?int $pickupSlotId,
        private readonly ?string $createdAt = null,
        private readonly ?string $adminName = null,
        private readonly ?string $adminEmail = null,
        private readonly array $items = []
    ) {
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
            $sum += $item->getQuantity();
        }
        return $sum;
    }

    public function getItemsSummary(): string
    {
        $parts = [];
        foreach ($this->items as $item) {
            $parts[] = $item->getName() . ' x' . $item->getQuantity();
        }
        return implode(', ', $parts);
    }
}

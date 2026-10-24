<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class CustomerOrder
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
     * @param array<CustomerOrderItem> $items
     * @param string|null $createdAt
     * @param string|null $updatedAt
     */
    public function __construct(
        public private(set) ?int $id,
        public private(set) int $userId,
        public private(set) string $orderNumber,
        public private(set) float $totalAmount,
        public private(set) string $status = 'pending',
        public private(set) ?string $notes = null,
        public private(set) ?string $pickupTime = null,
        public private(set) ?int $pickupSlotId = null,
        public private(set) array $items = [],
        public private(set) ?string $createdAt = null,
        public private(set) ?string $updatedAt = null
    ) {
    }

    /**
     * PHP 8.5 Property Hook: Total pizzas calculation
     */
    public int $totalPizzas {
        get => $this->getTotalPizzas();
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

    /**
     * @return array<CustomerOrderItem>
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

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }
}

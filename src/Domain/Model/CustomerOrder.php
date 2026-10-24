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
        public readonly ?int $id,
        public readonly int $userId,
        public readonly string $orderNumber,
        public readonly float $totalAmount,
        public readonly string $status = 'pending',
        public readonly ?string $notes = null,
        public readonly ?string $pickupTime = null,
        public readonly ?int $pickupSlotId = null,
        public readonly array $items = [],
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
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

    public function __get(string $name): mixed
    {
        return match ($name) {
            'totalPizzas' => $this->getTotalPizzas(),
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return $name === 'totalPizzas';
    }
}

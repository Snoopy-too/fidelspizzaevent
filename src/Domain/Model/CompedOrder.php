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
        public readonly ?int $id,
        public readonly int $userId,
        public readonly string $orderNumber,
        public readonly float $totalAmount,
        public readonly string $status,
        public readonly ?string $notes,
        public readonly ?string $pickupTime,
        public readonly ?int $pickupSlotId,
        public readonly ?string $createdAt = null,
        public readonly ?string $adminName = null,
        public readonly ?string $adminEmail = null,
        public readonly array $items = []
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

    public function __get(string $name): mixed
    {
        return match ($name) {
            'totalPizzas' => $this->getTotalPizzas(),
            'itemsSummary' => $this->getItemsSummary(),
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, ['totalPizzas', 'itemsSummary'], true);
    }
}

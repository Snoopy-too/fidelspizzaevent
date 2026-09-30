<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

use InvalidArgumentException;

final class PickupTimeSlot
{
    private readonly string $normalizedSlotTime;
    private readonly string $cleanLabel;

    public function __construct(
        private readonly ?int $id,
        private readonly int $configId,
        string $label,
        string $slotTime,
        private readonly ?int $maxPizzas = null,
        private readonly ?int $maxOrders = null,
        private readonly bool $isActive = true,
        private readonly int $sortOrder = 0,
        private readonly int $bookedPizzas = 0,
        private readonly int $bookedOrders = 0,
        private readonly ?string $createdAt = null,
        private readonly ?string $updatedAt = null
    ) {
        $trimmedLabel = trim($label);
        if (preg_match('/[\r\n]/', $trimmedLabel)) {
            throw new InvalidArgumentException('Pickup time slot label cannot contain line breaks.');
        }
        if (mb_strlen($trimmedLabel) > 100) {
            throw new InvalidArgumentException('Pickup time slot label cannot exceed 100 characters.');
        }
        $this->cleanLabel = $trimmedLabel;

        $trimmedTime = trim($slotTime);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $trimmedTime)) {
            throw new InvalidArgumentException('Invalid pickup time format. Expected HH:MM (24-hour).');
        }
        $this->normalizedSlotTime = substr($trimmedTime, 0, 5);

        if ($this->maxPizzas !== null && $this->maxPizzas < 1) {
            throw new InvalidArgumentException('Maximum pizzas per slot must be at least 1 or left blank for unlimited.');
        }

        if ($this->maxOrders !== null && $this->maxOrders < 1) {
            throw new InvalidArgumentException('Maximum orders per slot must be at least 1 or left blank for unlimited.');
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConfigId(): int
    {
        return $this->configId;
    }

    public function getLabel(): string
    {
        return $this->cleanLabel;
    }

    /**
     * Returns time in HH:MM format (e.g., "12:30").
     */
    public function getSlotTime(): string
    {
        return $this->normalizedSlotTime;
    }

    /**
     * Returns time in HH:MM:SS format for MySQL TIME comparison/storage.
     */
    public function getSlotTimeForDb(): string
    {
        return $this->normalizedSlotTime . ':00';
    }

    public function getMaxPizzas(): ?int
    {
        return $this->maxPizzas;
    }

    public function getMaxOrders(): ?int
    {
        return $this->maxOrders;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getBookedPizzas(): int
    {
        return $this->bookedPizzas;
    }

    public function getBookedOrders(): int
    {
        return $this->bookedOrders;
    }

    public function getRemainingPizzas(): ?int
    {
        if ($this->maxPizzas === null) {
            return null;
        }
        return max(0, $this->maxPizzas - $this->bookedPizzas);
    }

    public function getRemainingOrders(): ?int
    {
        if ($this->maxOrders === null) {
            return null;
        }
        return max(0, $this->maxOrders - $this->bookedOrders);
    }

    public function canAccommodate(int $pizzaCount = 1): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if ($this->maxOrders !== null && ($this->bookedOrders + 1) > $this->maxOrders) {
            return false;
        }

        if ($this->maxPizzas !== null && ($this->bookedPizzas + max(1, $pizzaCount)) > $this->maxPizzas) {
            return false;
        }

        return true;
    }

    public function isFull(): bool
    {
        return !$this->canAccommodate(1);
    }

    public function getDisplayName(): string
    {
        if ($this->cleanLabel !== '') {
            return $this->cleanLabel . ' (' . $this->normalizedSlotTime . ')';
        }
        return $this->normalizedSlotTime;
    }

    public function toDatetimeString(string $eventDate): string
    {
        $cleanDate = trim($eventDate);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cleanDate)) {
            $cleanDate = date('Y-m-d');
        }
        return $cleanDate . ' ' . $this->getSlotTimeForDb();
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'config_id' => $this->configId,
            'label' => $this->cleanLabel,
            'slot_time' => $this->normalizedSlotTime,
            'max_pizzas' => $this->maxPizzas,
            'max_orders' => $this->maxOrders,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
            'booked_pizzas' => $this->bookedPizzas,
            'booked_orders' => $this->bookedOrders,
            'remaining_pizzas' => $this->getRemainingPizzas(),
            'remaining_orders' => $this->getRemainingOrders(),
            'is_full' => $this->isFull(),
            'display_name' => $this->getDisplayName(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

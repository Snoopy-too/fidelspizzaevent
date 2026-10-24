<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

use InvalidArgumentException;

final class PickupTimeSlot
{
    public readonly string $cleanLabel;
    public readonly string $normalizedSlotTime;
    public readonly string $normalizedEndTime;

    public function __construct(
        public readonly ?int $id,
        public readonly int $configId,
        string $label,
        string $slotTime,
        string $endTime,
        public readonly ?int $maxPizzas = null,
        public readonly ?int $maxOrders = null,
        public readonly bool $isActive = true,
        public readonly int $sortOrder = 0,
        public readonly int $bookedPizzas = 0,
        public readonly int $bookedOrders = 0,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {
        $trimmedLabel = trim($label);
        if (preg_match('/[\r\n]/', $trimmedLabel)) {
            throw new InvalidArgumentException('Pickup time slot label cannot contain line breaks.');
        }
        if (mb_strlen($trimmedLabel) > 100) {
            throw new InvalidArgumentException('Pickup time slot label cannot exceed 100 characters.');
        }
        $this->cleanLabel = $trimmedLabel;

        $trimmedStart = trim($slotTime);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $trimmedStart)) {
            throw new InvalidArgumentException('Invalid start time format. Expected HH:MM (24-hour).');
        }
        $this->normalizedSlotTime = substr($trimmedStart, 0, 5);

        $trimmedEnd = trim($endTime);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $trimmedEnd)) {
            throw new InvalidArgumentException('Invalid end time format. Expected HH:MM (24-hour).');
        }
        $this->normalizedEndTime = substr($trimmedEnd, 0, 5);

        if ($this->normalizedEndTime <= $this->normalizedSlotTime) {
            throw new InvalidArgumentException(
                sprintf('End time (%s) must be after start time (%s).', $this->normalizedEndTime, $this->normalizedSlotTime)
            );
        }

        if ($this->maxPizzas !== null && $this->maxPizzas < 1) {
            throw new InvalidArgumentException('Maximum pizzas per slot must be at least 1 or left blank for unlimited.');
        }

        if ($this->maxOrders !== null && $this->maxOrders < 1) {
            throw new InvalidArgumentException('Maximum orders per slot must be at least 1 or left blank for unlimited.');
        }
    }

    public function isAvailable(): bool
    {
        return $this->canAccommodate(1);
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
     * Returns start time in HH:MM format (e.g., "11:30").
     */
    public function getSlotTime(): string
    {
        return $this->normalizedSlotTime;
    }

    /**
     * Returns end time in HH:MM format (e.g., "12:30").
     */
    public function getEndTime(): string
    {
        return $this->normalizedEndTime;
    }

    /**
     * Returns start time in HH:MM:SS format for MySQL TIME comparison/storage.
     */
    public function getSlotTimeForDb(): string
    {
        return $this->normalizedSlotTime . ':00';
    }

    /**
     * Returns end time in HH:MM:SS format for MySQL TIME storage.
     */
    public function getEndTimeForDb(): string
    {
        return $this->normalizedEndTime . ':00';
    }

    /**
     * Returns formatted time range (e.g., "11:30 to 12:30").
     */
    public function getTimeRange(): string
    {
        return $this->normalizedSlotTime . ' to ' . $this->normalizedEndTime;
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
            return $this->cleanLabel . ' (' . $this->getTimeRange() . ')';
        }
        return $this->getTimeRange();
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

    public function __get(string $name): mixed
    {
        return match ($name) {
            'label' => $this->cleanLabel,
            'slotTime' => $this->normalizedSlotTime,
            'endTime' => $this->normalizedEndTime,
            'isAvailable' => $this->isAvailable(),
            'remainingPizzas' => $this->getRemainingPizzas(),
            'remainingOrders' => $this->getRemainingOrders(),
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, ['label', 'slotTime', 'endTime', 'isAvailable', 'remainingPizzas', 'remainingOrders'], true);
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
            'end_time' => $this->normalizedEndTime,
            'time_range' => $this->getTimeRange(),
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

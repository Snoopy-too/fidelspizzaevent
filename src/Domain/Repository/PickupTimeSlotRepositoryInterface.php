<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Repository;

use FidelsPizza\Domain\Model\PickupTimeSlot;

interface PickupTimeSlotRepositoryInterface
{
    /**
     * Retrieve all configured pickup time slots (active and inactive) with booking stats.
     *
     * @return PickupTimeSlot[]
     */
    public function findAll(int $configId = 1, ?string $eventDate = null, ?int $excludeOrderId = null): array;

    /**
     * Retrieve only active pickup time slots with booking stats for customer selection.
     *
     * @return PickupTimeSlot[]
     */
    public function findActiveWithAvailability(int $configId = 1, ?string $eventDate = null, ?int $excludeOrderId = null): array;

    /**
     * Find a specific pickup time slot by ID with live booking stats.
     */
    public function findById(int $id, ?string $eventDate = null, ?int $excludeOrderId = null): ?PickupTimeSlot;

    /**
     * Find a pickup time slot matching a specific order's slot ID or datetime string.
     */
    public function findForOrder(?int $slotId, ?string $pickupDatetime, int $configId = 1): ?PickupTimeSlot;

    /**
     * Synchronize the full list of pickup time slots for a site configuration.
     *
     * @param int $configId
     * @param PickupTimeSlot[] $slots
     */
    public function syncSlots(int $configId, array $slots): void;
}

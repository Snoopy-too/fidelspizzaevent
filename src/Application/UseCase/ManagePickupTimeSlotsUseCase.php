<?php
declare(strict_types=1);

namespace FidelsPizza\Application\UseCase;

use FidelsPizza\Domain\Model\PickupTimeSlot;
use FidelsPizza\Domain\Repository\PickupTimeSlotRepositoryInterface;
use InvalidArgumentException;

final class ManagePickupTimeSlotsUseCase
{
    public function __construct(
        private readonly PickupTimeSlotRepositoryInterface $slotRepository
    ) {
    }

    /**
     * Retrieve all slots (including inactive) for the admin control panel.
     *
     * @return PickupTimeSlot[]
     */
    public function getAllSlots(int $configId = 1, ?string $eventDate = null): array
    {
        return $this->slotRepository->findAll($configId, $eventDate);
    }

    /**
     * Retrieve active slots with current booking numbers for customer order/edit forms.
     *
     * @return PickupTimeSlot[]
     */
    public function getAvailableSlotsForOrder(?string $eventDate = null, ?int $excludeOrderId = null, int $configId = 1): array
    {
        return $this->slotRepository->findActiveWithAvailability($configId, $eventDate, $excludeOrderId);
    }

    /**
     * Resolve the PickupTimeSlot associated with an existing order.
     */
    public function getSlotForOrder(?int $slotId, ?string $pickupDatetime, int $configId = 1): ?PickupTimeSlot
    {
        return $this->slotRepository->findForOrder($slotId, $pickupDatetime, $configId);
    }

    /**
     * Validate a customer's selected pickup time slot against active status and capacity limits.
     */
    public function validateSlotForOrder(
        int $slotId,
        int $requestedPizzas,
        ?string $eventDate = null,
        ?int $excludeOrderId = null
    ): PickupTimeSlot {
        if ($slotId <= 0) {
            throw new InvalidArgumentException('Please select a pickup time.');
        }

        $slot = $this->slotRepository->findById($slotId, $eventDate, $excludeOrderId);
        if ($slot === null || !$slot->isActive()) {
            throw new InvalidArgumentException('The selected pickup time is not available.');
        }

        if ($slot->getMaxOrders() !== null && ($slot->getBookedOrders() + 1) > $slot->getMaxOrders()) {
            throw new InvalidArgumentException(
                sprintf('Pickup time %s has reached its maximum number of orders.', $slot->getDisplayName())
            );
        }

        if ($slot->getMaxPizzas() !== null && ($slot->getBookedPizzas() + $requestedPizzas) > $slot->getMaxPizzas()) {
            $remaining = $slot->getRemainingPizzas() ?? 0;
            throw new InvalidArgumentException(
                sprintf(
                    'Pickup time %s only has %d pizza slot(s) remaining, but your order has %d pizza(s).',
                    $slot->getDisplayName(),
                    $remaining,
                    $requestedPizzas
                )
            );
        }

        return $slot;
    }

    /**
     * Validate and persist pickup time slots submitted from the Administrator Settings page.
     *
     * @param array<int, array<string, mixed>> $rawSlots
     */
    public function saveAdminSlots(array $rawSlots, int $configId = 1): void
    {
        $domainSlots = [];
        $seenTimes = [];
        $sortIndex = 0;

        foreach ($rawSlots as $row) {
            $rawTime = trim((string)($row['slot_time'] ?? ''));
            if ($rawTime === '') {
                continue;
            }

            $id = isset($row['id']) && (int)$row['id'] > 0 ? (int)$row['id'] : null;
            $label = trim((string)($row['label'] ?? ''));

            $maxPizzasRaw = trim((string)($row['max_pizzas'] ?? ''));
            $maxPizzas = $maxPizzasRaw !== '' ? (int)$maxPizzasRaw : null;

            $maxOrdersRaw = trim((string)($row['max_orders'] ?? ''));
            $maxOrders = $maxOrdersRaw !== '' ? (int)$maxOrdersRaw : null;

            $isActive = !empty($row['is_active']);

            $slot = new PickupTimeSlot(
                id: $id,
                configId: $configId,
                label: $label,
                slotTime: $rawTime,
                maxPizzas: $maxPizzas,
                maxOrders: $maxOrders,
                isActive: $isActive,
                sortOrder: $sortIndex
            );

            $normalizedTime = $slot->getSlotTime();
            if (isset($seenTimes[$normalizedTime])) {
                throw new InvalidArgumentException(
                    sprintf('Duplicate pickup time "%s". Each pickup time slot must have a unique time.', $normalizedTime)
                );
            }
            $seenTimes[$normalizedTime] = true;

            $domainSlots[] = $slot;
            $sortIndex++;
        }

        $this->slotRepository->syncSlots($configId, $domainSlots);
    }
}

<?php
declare(strict_types=1);

/**
 * Shared POST handler for creating comped orders.
 *
 * @param \FidelsPizza\Infrastructure\Container\ServiceContainer $container
 * @param string|null $errorMessage Reference to capture error messages
 * @return string|null Success message if created, or null
 */
function handleCompedOrderSubmission(
    \FidelsPizza\Infrastructure\Container\ServiceContainer $container,
    ?string &$errorMessage = null
): ?string {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['create_comped_order'])) {
        return null;
    }

    if (!verifyCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $errorMessage = __('invalid_csrf_token') ?: 'Invalid security token.';
        return null;
    }

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0) {
        $errorMessage = __('error_invalid_credentials');
        return null;
    }

    $rawQuantities = isset($_POST['item_quantities']) && is_array($_POST['item_quantities'])
        ? $_POST['item_quantities']
        : [];
    $itemQuantities = [];
    foreach ($rawQuantities as $itemId => $qty) {
        $itemQuantities[(int)$itemId] = max(0, (int)$qty);
    }

    $notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : null;
    $pickupSlotId = !empty($_POST['pickup_slot_id']) ? (int)$_POST['pickup_slot_id'] : null;

    $request = new \FidelsPizza\Application\DTO\CreateCompedOrderRequest(
        adminId: $adminId,
        itemQuantities: $itemQuantities,
        notes: $notes,
        pickupSlotId: $pickupSlotId
    );

    $useCase = $container->getCreateCompedOrderUseCase();
    $result = $useCase->execute($request);

    if ($result->success) {
        return sprintf(
            (string)__('comped_order_created_success'),
            (string)$result->orderNumber,
            (int)$result->totalPizzas
        );
    }

    $errorMessage = $result->errorMessage ?? 'Failed to create comped order.';
    return null;
}

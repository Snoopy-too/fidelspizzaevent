<?php
declare(strict_types=1);

namespace FidelsPizza\Application\DTO;

final class CreateOrderResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?int $orderId = null,
        public readonly ?string $orderNumber = null,
        public readonly float $totalAmount = 0.0,
        public readonly ?string $pickupTime = null,
        public readonly ?string $pickupDisplay = null,
        public readonly bool $emailSent = false,
        public readonly ?string $errorMessage = null,
        public readonly ?string $userEmail = null
    ) {
    }
}

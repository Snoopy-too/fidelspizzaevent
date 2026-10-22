<?php
declare(strict_types=1);

namespace FidelsPizza\Application\DTO;

final class UpdateOrderResult
{
    public function __construct(
        public readonly bool $success,
        public readonly bool $isCancelled = false,
        public readonly float $totalAmount = 0.0,
        public readonly ?string $pickupTime = null,
        public readonly bool $emailSent = false,
        public readonly ?string $errorMessage = null
    ) {
    }
}

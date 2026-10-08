<?php
declare(strict_types=1);

namespace FidelsPizza\Application\DTO;

final class CompedOrderResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?int $orderId = null,
        public readonly ?string $orderNumber = null,
        public readonly int $totalPizzas = 0,
        public readonly ?string $errorMessage = null
    ) {
    }
}

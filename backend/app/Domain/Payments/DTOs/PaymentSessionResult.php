<?php

namespace App\Domain\Payments\DTOs;

final readonly class PaymentSessionResult
{
    public function __construct(
        public string $provider,
        public string $providerReference,
        public ?string $checkoutUrl,
        public string $status = 'pending',
    ) {}
}

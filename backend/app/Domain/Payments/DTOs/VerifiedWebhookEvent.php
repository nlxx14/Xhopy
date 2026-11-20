<?php

namespace App\Domain\Payments\DTOs;

final readonly class VerifiedWebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $provider,
        public string $providerEventId,
        public string $eventType,
        public ?string $orderNumber,
        public ?string $providerReference,
        public array $payload,
    ) {}
}

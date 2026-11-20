<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\DTOs\PaymentSessionResult;
use App\Domain\Payments\DTOs\VerifiedWebhookEvent;

interface PaymentGateway
{
    public function createCheckoutSession(Order $order): PaymentSessionResult;

    public function verifyAndParseWebhook(string $rawPayload, string $signatureHeader): VerifiedWebhookEvent;
}

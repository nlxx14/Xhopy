<?php

namespace App\Domain\Payments\Adapters;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\DTOs\PaymentSessionResult;
use App\Domain\Payments\DTOs\VerifiedWebhookEvent;
use App\Domain\Payments\Exceptions\InvalidWebhookSignatureException;
use Illuminate\Support\Str;

/**
 * PayMongo PaymentGateway adapter with HMAC-SHA256 `Paymongo-Signature` verification
 * and timestamp replay protection (ADR-001 §4).
 */
final class PayMongoPaymentGateway implements PaymentGateway
{
    public const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function createCheckoutSession(Order $order): PaymentSessionResult
    {
        $reference = 'cs_test_'.strtolower(Str::random(24));

        $checkoutUrl = route('orders.show', ['orderNumber' => $order->order_number], false);

        return new PaymentSessionResult(
            provider: 'paymongo',
            providerReference: $reference,
            checkoutUrl: $checkoutUrl,
            status: 'pending',
        );
    }

    public function verifyAndParseWebhook(string $rawPayload, string $signatureHeader): VerifiedWebhookEvent
    {
        $webhookSecret = (string) config('services.paymongo.webhook_secret', '');

        if ($webhookSecret === '') {
            throw new InvalidWebhookSignatureException('PayMongo webhook secret is not configured.');
        }

        if ($signatureHeader === '') {
            throw new InvalidWebhookSignatureException('Missing Paymongo-Signature header.');
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $segment) {
            $pair = explode('=', trim($segment), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        $timestamp = isset($parts['t']) && ctype_digit($parts['t']) ? (int) $parts['t'] : null;
        $mode = (string) config('services.paymongo.mode', 'test');
        $signatureKey = $mode === 'live' ? 'li' : 'te';
        $providedSignature = $parts[$signatureKey] ?? ($parts['te'] ?? ($parts['li'] ?? ''));

        if ($timestamp === null || $providedSignature === '') {
            throw new InvalidWebhookSignatureException('Malformed Paymongo-Signature header.');
        }

        if (abs(now()->getTimestamp() - $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            throw new InvalidWebhookSignatureException('PayMongo webhook timestamp is outside the allowed tolerance window.');
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$rawPayload, $webhookSecret);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            throw new InvalidWebhookSignatureException('PayMongo webhook signature mismatch.');
        }

        /** @var mixed $decoded */
        $decoded = json_decode($rawPayload, true);

        if (! is_array($decoded)) {
            throw new InvalidWebhookSignatureException('Malformed PayMongo webhook JSON payload.');
        }

        /** @var array<string, mixed> $data */
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        /** @var array<string, mixed> $attributes */
        $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];
        /** @var array<string, mixed> $resourceData */
        $resourceData = is_array($attributes['data'] ?? null) ? $attributes['data'] : [];
        /** @var array<string, mixed> $resourceAttributes */
        $resourceAttributes = is_array($resourceData['attributes'] ?? null) ? $resourceData['attributes'] : [];
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($resourceAttributes['metadata'] ?? null) ? $resourceAttributes['metadata'] : [];

        $eventId = isset($data['id']) && is_string($data['id']) && $data['id'] !== '' ? $data['id'] : '';
        $eventType = isset($attributes['type']) && is_string($attributes['type']) && $attributes['type'] !== '' ? $attributes['type'] : '';

        if ($eventId === '' || $eventType === '') {
            throw new InvalidWebhookSignatureException('PayMongo webhook event is missing required id or type.');
        }

        $orderNumber = isset($metadata['order_number']) && is_string($metadata['order_number'])
            ? $metadata['order_number']
            : (isset($resourceAttributes['reference_number']) && is_string($resourceAttributes['reference_number'])
                ? $resourceAttributes['reference_number']
                : null);

        $providerReference = isset($resourceData['id']) && is_string($resourceData['id'])
            ? $resourceData['id']
            : null;

        return new VerifiedWebhookEvent(
            provider: 'paymongo',
            providerEventId: $eventId,
            eventType: $eventType,
            orderNumber: $orderNumber,
            providerReference: $providerReference,
            payload: $decoded,
        );
    }

    /**
     * Helper to compute a valid `Paymongo-Signature` header for testing or sandbox simulation.
     */
    public static function signPayload(string $rawPayload, string $secret, ?int $timestamp = null): string
    {
        $ts = $timestamp ?? now()->getTimestamp();
        $hmac = hash_hmac('sha256', $ts.'.'.$rawPayload, $secret);

        return "t={$ts},te={$hmac},li=";
    }
}

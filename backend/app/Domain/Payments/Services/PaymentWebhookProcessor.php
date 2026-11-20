<?php

namespace App\Domain\Payments\Services;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\PaymentStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderStateTransitionService;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use Illuminate\Support\Facades\DB;

/**
 * Verifies payment webhooks cryptographically and processes events idempotently.
 */
final class PaymentWebhookProcessor
{
    private const PAID_EVENT_TYPES = [
        'checkout_session.payment.paid',
        'payment.paid',
    ];

    private const FAILED_EVENT_TYPES = [
        'payment.failed',
    ];

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OrderStateTransitionService $transitions,
    ) {}

    public function process(string $rawPayload, string $signatureHeader): PaymentEvent
    {
        $verified = $this->gateway->verifyAndParseWebhook($rawPayload, $signatureHeader);

        return DB::transaction(function () use ($verified): PaymentEvent {
            $existing = PaymentEvent::query()
                ->where('provider_event_id', $verified->providerEventId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $order = $this->resolveOrder($verified->orderNumber, $verified->providerReference);

            $event = PaymentEvent::query()->create([
                'provider' => $verified->provider,
                'provider_event_id' => $verified->providerEventId,
                'event_type' => $verified->eventType,
                'order_id' => $order?->id,
                'payload' => $verified->payload,
                'processed_at' => now(),
            ]);

            if ($order === null) {
                return $event;
            }

            if (in_array($verified->eventType, self::PAID_EVENT_TYPES, true)) {
                $this->markOrderPaid($order, $verified->providerReference);
            } elseif (in_array($verified->eventType, self::FAILED_EVENT_TYPES, true)) {
                $this->markOrderPaymentFailed($order, $verified->providerReference);
            }

            return $event;
        });
    }

    private function resolveOrder(?string $orderNumber, ?string $providerReference): ?Order
    {
        if ($orderNumber !== null && $orderNumber !== '') {
            $order = Order::query()
                ->where('order_number', $orderNumber)
                ->lockForUpdate()
                ->first();

            if ($order !== null) {
                return $order;
            }
        }

        if ($providerReference !== null && $providerReference !== '') {
            $payment = Payment::query()
                ->where('provider_reference', $providerReference)
                ->lockForUpdate()
                ->first();

            if ($payment !== null) {
                return Order::query()
                    ->whereKey($payment->order_id)
                    ->lockForUpdate()
                    ->first();
            }
        }

        return null;
    }

    private function markOrderPaid(Order $order, ?string $providerReference): void
    {
        $payment = $order->payments()->lockForUpdate()->first();

        if ($payment !== null) {
            $payment->status = 'paid';
            $payment->paid_at = $payment->paid_at ?? now();
            if ($providerReference !== null && $payment->provider_reference === null) {
                $payment->provider_reference = $providerReference;
            }
            $payment->save();
        }

        $order->payment_status = PaymentStatus::Paid;
        $order->paid_at = $order->paid_at ?? now();
        $order->save();

        if ($order->status->canTransitionTo(OrderStatus::Paid)) {
            $this->transitions->transition(
                $order,
                OrderStatus::Paid,
                null,
                'Payment confirmed via verified PayMongo webhook.',
            );
        }
    }

    private function markOrderPaymentFailed(Order $order, ?string $providerReference): void
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return;
        }

        $payment = $order->payments()->lockForUpdate()->first();
        if ($payment !== null) {
            $payment->status = 'failed';
            if ($providerReference !== null && $payment->provider_reference === null) {
                $payment->provider_reference = $providerReference;
            }
            $payment->save();
        }

        $order->payment_status = PaymentStatus::Failed;
        $order->save();
    }
}

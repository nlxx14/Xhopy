<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\PaymentStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderStateTransitionService;
use App\Domain\Payments\Adapters\PayMongoPaymentGateway;
use App\Domain\Payments\Models\PaymentEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function placeTestOrder(User $user, Product $product, int $quantity = 1): Order
{
    $address = CustomerAddress::factory()->for($user)->defaultShipping()->create();

    test()->actingAs($user)->post(route('cart.items.store'), [
        'product_id' => $product->id,
        'quantity' => $quantity,
    ]);

    test()->actingAs($user)->post(route('checkout.store'), [
        'payment_method' => 'paymongo_checkout',
        'address_id' => $address->id,
    ]);

    return Order::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
}

test('visiting order detail page never marks an unpaid order as paid without webhook confirmation', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 5]);
    $order = placeTestOrder($user, $product);

    $this->actingAs($user)
        ->get(route('orders.show', ['orderNumber' => $order->order_number, 'paid' => 1, 'success' => 'true']))
        ->assertOk();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('verified paymongo webhook marks order paid and duplicate webhook deliveries are idempotent', function () {
    config([
        'services.paymongo.webhook_secret' => 'whsec_test_secret_key',
        'services.paymongo.mode' => 'test',
    ]);

    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => '2500.00', 'stock_quantity' => 5]);
    $order = placeTestOrder($user, $product);
    $payment = $order->payments()->firstOrFail();

    $payload = json_encode([
        'data' => [
            'id' => 'evt_paymongo_001',
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'data' => [
                    'id' => $payment->provider_reference,
                    'attributes' => [
                        'reference_number' => $order->order_number,
                        'metadata' => [
                            'order_number' => $order->order_number,
                        ],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $signature = PayMongoPaymentGateway::signPayload($payload, 'whsec_test_secret_key');

    $this->call(
        'POST',
        route('webhooks.paymongo'),
        [],
        [],
        [],
        ['HTTP_PAYMONGO_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    )->assertOk()->assertJson(['status' => 'ok', 'event_id' => 'evt_paymongo_001']);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and($payment->fresh()->status)->toBe('paid')
        ->and($order->statusHistories()->count())->toBe(2);

    // Replay the exact same webhook event ID: must be idempotent
    $this->call(
        'POST',
        route('webhooks.paymongo'),
        [],
        [],
        [],
        ['HTTP_PAYMONGO_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    )->assertOk();

    expect(PaymentEvent::query()->where('provider_event_id', 'evt_paymongo_001')->count())->toBe(1)
        ->and($order->statusHistories()->count())->toBe(2);
});

test('paymongo webhook rejects invalid signature or expired timestamp', function () {
    config([
        'services.paymongo.webhook_secret' => 'whsec_test_secret_key',
        'services.paymongo.mode' => 'test',
    ]);

    $user = User::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 5]);
    $order = placeTestOrder($user, $product);

    $payload = json_encode([
        'data' => [
            'id' => 'evt_forged_999',
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'data' => [
                    'id' => 'cs_forged',
                    'attributes' => [
                        'metadata' => ['order_number' => $order->order_number],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    // Wrong HMAC signature
    $this->call(
        'POST',
        route('webhooks.paymongo'),
        [],
        [],
        [],
        ['HTTP_PAYMONGO_SIGNATURE' => 't='.now()->getTimestamp().',te=deadbeef,li='],
        $payload,
    )->assertStatus(400);

    // Expired timestamp (10 minutes old > 300s tolerance)
    $expiredSignature = PayMongoPaymentGateway::signPayload(
        $payload,
        'whsec_test_secret_key',
        now()->subMinutes(10)->getTimestamp(),
    );

    $this->call(
        'POST',
        route('webhooks.paymongo'),
        [],
        [],
        [],
        ['HTTP_PAYMONGO_SIGNATURE' => $expiredSignature],
        $payload,
    )->assertStatus(400);

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('customer can cancel their own pending order and reserved stock is restored, while invalid transitions are rejected', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 10]);

    $order = placeTestOrder($user, $product, 3);
    expect($product->fresh()->stock_quantity)->toBe(7);

    // Another customer cannot view or cancel this order
    $this->actingAs($otherUser)
        ->get(route('orders.show', $order->order_number))
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->post(route('orders.cancel', $order->order_number))
        ->assertForbidden();

    // Owner cancels their pending order
    $this->actingAs($user)
        ->post(route('orders.cancel', $order->order_number))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->cancelled_at)->not->toBeNull()
        ->and($product->fresh()->stock_quantity)->toBe(10);

    // Terminal state: cancelled order cannot transition to Paid
    expect(fn () => app(OrderStateTransitionService::class)->transition($order, OrderStatus::Paid))
        ->toThrow(InvalidArgumentException::class);
});

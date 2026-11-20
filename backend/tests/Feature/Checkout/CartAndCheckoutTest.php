<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\PaymentStatus;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('cart calculates server-authoritative totals and enforces stock limits', function () {
    $product = Product::factory()->create([
        'name' => 'Studio Monitor Headphones',
        'price' => '3250.00',
        'stock_quantity' => 3,
    ]);

    $this->post(route('cart.items.store'), [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertSessionHasNoErrors();

    $this->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cart/Index')
            ->where('cart.itemCount', 2)
            ->where('cart.subtotalFormatted', '₱6,500.00')
            ->where('cart.items.0.lineTotalFormatted', '₱6,500.00')
        );

    // Exceeding available stock (2 already in cart + 2 more > 3 in stock) is rejected
    $this->post(route('cart.items.store'), [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertSessionHasErrors('quantity');
});

test('draft and out-of-stock products cannot be added to cart', function () {
    $draft = Product::factory()->draft()->create();
    $soldOut = Product::factory()->outOfStock()->create();

    $this->post(route('cart.items.store'), ['product_id' => $draft->id, 'quantity' => 1])
        ->assertSessionHasErrors('product_id');

    $this->post(route('cart.items.store'), ['product_id' => $soldOut->id, 'quantity' => 1])
        ->assertSessionHasErrors('quantity');
});

test('users cannot modify or remove another shoppers cart items', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 10]);

    $this->actingAs($owner)->post(route('cart.items.store'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $cartItemId = (int) $this->actingAs($owner)
        ->get(route('cart.index'))
        ->inertiaProps('cart.items.0.id');

    $this->actingAs($intruder)
        ->patch(route('cart.items.update', $cartItemId), ['quantity' => 5])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('cart.items.destroy', $cartItemId))
        ->assertForbidden();
});

test('checkout reserves stock, snapshots product prices, creates payment session, and clears cart', function () {
    $user = User::factory()->create();
    $address = CustomerAddress::factory()->for($user)->defaultShipping()->create([
        'recipient_name' => 'Noly Sereño',
        'city' => 'Makati City',
    ]);
    $product = Product::factory()->create([
        'name' => 'Mechanical Keyboard',
        'sku' => 'KB-75-PRO',
        'price' => '4500.00',
        'stock_quantity' => 5,
    ]);

    $this->actingAs($user)->post(route('cart.items.store'), [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $response = $this->actingAs($user)->post(route('checkout.store'), [
        'idempotency_key' => 'idem-order-001',
        'payment_method' => 'paymongo_checkout',
        'address_id' => $address->id,
    ]);

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();

    $response->assertRedirect(route('orders.show', $order->order_number));

    // Stock deducted atomically (5 - 2 = 3)
    expect($product->fresh()->stock_quantity)->toBe(3)
        ->and($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($order->subtotal_amount)->toBe('9000.00')
        ->and($order->total_amount)->toBe('9000.00')
        ->and($order->shipping_recipient_name)->toBe('Noly Sereño');

    // Catalog mutation after order placement must NOT alter historical order item snapshots
    $product->update([
        'name' => 'Renamed Keyboard V2',
        'price' => '9999.00',
    ]);

    $orderItem = $order->items()->firstOrFail();
    expect($orderItem->product_name)->toBe('Mechanical Keyboard')
        ->and($orderItem->sku)->toBe('KB-75-PRO')
        ->and($orderItem->unit_price)->toBe('4500.00')
        ->and($orderItem->line_total)->toBe('9000.00');

    // Cart was cleared
    $this->actingAs($user)
        ->get(route('cart.index'))
        ->assertInertia(fn (Assert $page) => $page->where('cart.itemCount', 0));
});

test('checkout idempotency key prevents duplicate orders and double stock deduction on retry', function () {
    $user = User::factory()->create();
    $address = CustomerAddress::factory()->for($user)->create();
    $product = Product::factory()->create([
        'price' => '1200.00',
        'stock_quantity' => 4,
    ]);

    $this->actingAs($user)->post(route('cart.items.store'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $payload = [
        'idempotency_key' => 'retry-safe-key-123',
        'payment_method' => 'paymongo_checkout',
        'address_id' => $address->id,
    ];

    $this->actingAs($user)->post(route('checkout.store'), $payload);
    $this->actingAs($user)->post(route('checkout.store'), $payload);

    expect(Order::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and($product->fresh()->stock_quantity)->toBe(3);
});

test('checkout aborts and rolls back if stock becomes insufficient before confirmation', function () {
    $user = User::factory()->create();
    $address = CustomerAddress::factory()->for($user)->create();
    $product = Product::factory()->create([
        'name' => 'Limited Edition DAC',
        'price' => '5000.00',
        'stock_quantity' => 2,
    ]);

    $this->actingAs($user)->post(route('cart.items.store'), [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    // Concurrent buyer purchases 1 unit before this user confirms checkout
    $product->update(['stock_quantity' => 1]);

    $this->actingAs($user)
        ->from(route('checkout.index'))
        ->post(route('checkout.store'), [
            'payment_method' => 'paymongo_checkout',
            'address_id' => $address->id,
        ])
        ->assertSessionHasErrors('cart');

    expect(Order::query()->count())->toBe(0)
        ->and($product->fresh()->stock_quantity)->toBe(1);
});

<?php

namespace App\Domain\Checkout\Actions;

use App\Domain\Cart\Models\CartItem;
use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Support\Money;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\PaymentStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Server-authoritative checkout and order placement.
 *
 * - Supports idempotency keys so retries do not create duplicate orders.
 * - Locks product rows (`lockForUpdate()`) in deterministic ID order to prevent overselling and deadlocks.
 * - Revalidates product visibility, price, and stock immediately before order confirmation.
 * - Snapshots product names/SKUs/prices and the shipping address onto the order.
 */
final class PlaceOrderAction
{
    public const ALLOWED_PAYMENT_METHODS = [
        'paymongo_checkout',
        'cash_on_delivery',
    ];

    public function __construct(
        private readonly CartService $cartService,
        private readonly PaymentGateway $paymentGateway,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Request $request, User $user, array $validated): Order
    {
        $idempotencyKey = isset($validated['idempotency_key']) && is_string($validated['idempotency_key']) && $validated['idempotency_key'] !== ''
            ? trim($validated['idempotency_key'])
            : null;

        if ($idempotencyKey !== null) {
            $existingOrder = Order::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingOrder !== null) {
                return $existingOrder;
            }
        }

        $address = $this->resolveShippingAddress($user, $validated);
        $paymentMethod = (string) ($validated['payment_method'] ?? 'paymongo_checkout');

        return DB::transaction(function () use ($request, $user, $address, $paymentMethod, $idempotencyKey): Order {
            if ($idempotencyKey !== null) {
                $existingInTx = Order::query()
                    ->where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existingInTx !== null) {
                    return $existingInTx;
                }
            }

            $cart = $this->cartService->findActiveCart($request);
            if ($cart === null) {
                throw ValidationException::withMessages([
                    'cart' => __('Your shopping cart is empty.'),
                ]);
            }

            /** @var Collection<int, CartItem> $cartItems */
            $cartItems = $cart->items()->orderBy('product_id')->get();

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => __('Your shopping cart is empty.'),
                ]);
            }

            $productIds = $cartItems->pluck('product_id')->unique()->sort()->values()->all();

            /** @var Collection<int, Product> $lockedProducts */
            $lockedProducts = Product::query()
                ->whereIn('id', $productIds)
                ->with('category')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotalCents = 0;
            $preparedItems = [];

            foreach ($cartItems as $cartItem) {
                /** @var Product|null $product */
                $product = $lockedProducts->get($cartItem->product_id);

                if (
                    $product === null
                    || $product->status !== ProductStatus::Active
                    || $product->published_at === null
                    || $product->published_at->isFuture()
                    || ! $product->category->is_active
                ) {
                    throw ValidationException::withMessages([
                        'cart' => __('One or more products in your cart are no longer available.'),
                    ]);
                }

                $variantStock = $product->stockForVariant($cartItem->selected_color, $cartItem->selected_size);

                if ($variantStock < $cartItem->quantity) {
                    $variantLabel = implode(' / ', array_filter([
                        $cartItem->selected_color,
                        $cartItem->selected_size,
                    ]));
                    $displayName = $variantLabel !== ''
                        ? "{$product->name} ({$variantLabel})"
                        : $product->name;

                    throw ValidationException::withMessages([
                        'cart' => __('Insufficient stock for :name (only :available available).', [
                            'name' => $displayName,
                            'available' => $variantStock,
                        ]),
                    ]);
                }

                $unitCents = Money::cents($product->price);
                $lineCents = $unitCents * $cartItem->quantity;
                $subtotalCents += $lineCents;

                // Deduct reserved stock inside the row-locked transaction
                $previousStock = $product->stock_quantity;
                if ($product->hasVariantStocks()) {
                    $product->adjustVariantStock(
                        $cartItem->selected_color,
                        $cartItem->selected_size,
                        -$cartItem->quantity,
                    );
                } else {
                    $product->stock_quantity -= $cartItem->quantity;
                }
                $product->save();

                $preparedItems[] = [
                    'product' => $product,
                    'previous_stock' => $previousStock,
                    'resulting_stock' => $product->stock_quantity,
                    'item' => [
                        'product_id' => $product->id,
                        'store_id' => $product->store_id,
                        'product_name' => $product->name,
                        'product_slug' => $product->slug,
                        'sku' => $product->sku,
                        'selected_color' => $cartItem->selected_color,
                        'selected_size' => $cartItem->selected_size,
                        'unit_price' => $this->centsToDecimal($unitCents),
                        'currency' => $product->currency,
                        'quantity' => $cartItem->quantity,
                        'line_total' => $this->centsToDecimal($lineCents),
                    ],
                ];
            }

            $discountCents = 0;
            $shippingCents = 0;
            $totalCents = $subtotalCents - $discountCents + $shippingCents;

            $initialStatus = $paymentMethod === 'cash_on_delivery'
                ? OrderStatus::Processing
                : OrderStatus::PendingPayment;

            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $user->id,
                'store_id' => null,
                'idempotency_key' => $idempotencyKey,
                'status' => $initialStatus,
                'payment_status' => PaymentStatus::Unpaid,
                'payment_method' => $paymentMethod,
                'currency' => 'PHP',
                'subtotal_amount' => $this->centsToDecimal($subtotalCents),
                'discount_amount' => $this->centsToDecimal($discountCents),
                'shipping_amount' => $this->centsToDecimal($shippingCents),
                'total_amount' => $this->centsToDecimal($totalCents),
                'shipping_recipient_name' => $address['recipient_name'],
                'shipping_phone' => $address['phone'],
                'shipping_line_1' => $address['line_1'],
                'shipping_line_2' => $address['line_2'],
                'shipping_barangay' => $address['barangay'],
                'shipping_city' => $address['city'],
                'shipping_province' => $address['province'],
                'shipping_postal_code' => $address['postal_code'],
                'shipping_country_code' => $address['country_code'],
                'shipping_notes' => $address['delivery_notes'],
                'placed_at' => now(),
            ]);

            foreach ($preparedItems as $prepared) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    ...$prepared['item'],
                ]);

                $this->inventory->recordMovementForLockedProduct(
                    $prepared['product'],
                    $prepared['previous_stock'],
                    $prepared['resulting_stock'],
                    StockMovementType::OrderReservation,
                    $user,
                    "Reserved for order {$order->order_number}",
                    $order,
                );
            }

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => $initialStatus->value,
                'actor_id' => $user->id,
                'note' => 'Order placed by customer.',
                'occurred_at' => now(),
            ]);

            if ($paymentMethod === 'paymongo_checkout') {
                $session = $this->paymentGateway->createCheckoutSession($order);

                Payment::query()->create([
                    'order_id' => $order->id,
                    'provider' => $session->provider,
                    'provider_reference' => $session->providerReference,
                    'checkout_url' => $session->checkoutUrl,
                    'status' => $session->status,
                    'amount' => $order->total_amount,
                    'currency' => $order->currency,
                ]);
            }

            $cart->items()->delete();

            return $order;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *     recipient_name: string,
     *     phone: string,
     *     line_1: string,
     *     line_2: string|null,
     *     barangay: string|null,
     *     city: string,
     *     province: string,
     *     postal_code: string,
     *     country_code: string,
     *     delivery_notes: string|null
     * }
     */
    private function resolveShippingAddress(User $user, array $validated): array
    {
        if (isset($validated['address_id']) && $validated['address_id'] !== '') {
            $saved = CustomerAddress::query()
                ->where('user_id', $user->id)
                ->find((int) $validated['address_id']);

            if ($saved === null) {
                throw ValidationException::withMessages([
                    'address_id' => __('The selected shipping address was not found.'),
                ]);
            }

            return [
                'recipient_name' => $saved->recipient_name,
                'phone' => $saved->phone,
                'line_1' => $saved->line_1,
                'line_2' => $saved->line_2,
                'barangay' => $saved->barangay,
                'city' => $saved->city,
                'province' => $saved->province,
                'postal_code' => $saved->postal_code,
                'country_code' => $saved->country_code,
                'delivery_notes' => isset($validated['shipping_notes']) && $validated['shipping_notes'] !== ''
                    ? (string) $validated['shipping_notes']
                    : $saved->delivery_notes,
            ];
        }

        return [
            'recipient_name' => (string) $validated['recipient_name'],
            'phone' => (string) $validated['phone'],
            'line_1' => (string) $validated['line_1'],
            'line_2' => isset($validated['line_2']) && $validated['line_2'] !== '' ? (string) $validated['line_2'] : null,
            'barangay' => isset($validated['barangay']) && $validated['barangay'] !== '' ? (string) $validated['barangay'] : null,
            'city' => (string) $validated['city'],
            'province' => (string) $validated['province'],
            'postal_code' => (string) $validated['postal_code'],
            'country_code' => strtoupper((string) ($validated['country_code'] ?? 'PH')),
            'delivery_notes' => isset($validated['shipping_notes']) && $validated['shipping_notes'] !== ''
                ? (string) $validated['shipping_notes']
                : null,
        ];
    }

    private function centsToDecimal(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), abs($cents) % 100);
    }

    private function generateOrderNumber(): string
    {
        return 'XH-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}

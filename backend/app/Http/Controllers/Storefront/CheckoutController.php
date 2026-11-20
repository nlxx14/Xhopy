<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Support\StorefrontShell;
use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Customer\Models\CustomerAddress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutOrderRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function index(
        Request $request,
        CartService $cartService,
        StorefrontShell $shell,
    ): Response|RedirectResponse {
        $cart = $cartService->present($request);

        if ($cart['itemCount'] === 0) {
            return to_route('cart.index');
        }

        $savedAddresses = array_values(
            $request->user()
                ->addresses()
                ->get()
                ->map(fn (CustomerAddress $address): array => $address->toFrontendArray())
                ->all(),
        );

        return Inertia::render('checkout/Index', [
            'announcement' => null,
            'categories' => $shell->categories(),
            'cartSummary' => [
                'itemCount' => $cart['itemCount'],
                'subtotalFormatted' => $cart['subtotalFormatted'],
            ],
            'cart' => $cart,
            'savedAddresses' => $savedAddresses,
            'idempotencyKey' => (string) Str::uuid(),
            'paymentMethods' => [
                [
                    'value' => 'paymongo_checkout',
                    'label' => 'PayMongo Online Checkout (GCash, Maya, Cards)',
                    'description' => 'Payment status is confirmed asynchronously via verified PayMongo webhook.',
                ],
                [
                    'value' => 'cash_on_delivery',
                    'label' => 'Cash on Delivery (COD)',
                    'description' => 'Pay in cash upon verified delivery to your shipping address.',
                ],
            ],
        ]);
    }

    public function store(
        CheckoutOrderRequest $request,
        PlaceOrderAction $placeOrder,
    ): RedirectResponse {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $order = $placeOrder->execute($request, $request->user(), $validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Order :number placed.', ['number' => $order->order_number]),
        ]);

        return to_route('orders.show', ['orderNumber' => $order->order_number]);
    }
}

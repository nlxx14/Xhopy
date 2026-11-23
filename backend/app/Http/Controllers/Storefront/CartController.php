<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Cart\Models\CartItem;
use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Support\StorefrontShell;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(
        Request $request,
        CartService $cartService,
        StorefrontShell $shell,
    ): Response {
        $cart = $cartService->present($request);

        return Inertia::render('cart/Index', [
            'announcement' => null,
            'categories' => $shell->categories(),
            'cartSummary' => [
                'itemCount' => $cart['itemCount'],
                'subtotalFormatted' => $cart['itemCount'] > 0 ? $cart['subtotalFormatted'] : null,
            ],
            'cart' => $cart,
        ]);
    }

    public function store(Request $request, CartService $cartService): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.CartItem::MAX_QUANTITY_PER_LINE],
            'buy_now' => ['nullable', 'boolean'],
        ]);

        $cartService->addItem(
            $request,
            (int) $validated['product_id'],
            (int) ($validated['quantity'] ?? 1),
        );

        if ($request->boolean('buy_now')) {
            return to_route('checkout.index');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Added to cart.')]);

        return back();
    }

    public function update(
        Request $request,
        CartItem $cartItem,
        CartService $cartService,
    ): RedirectResponse {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:'.CartItem::MAX_QUANTITY_PER_LINE],
        ]);

        $cartService->updateItemQuantity($request, $cartItem, (int) $validated['quantity']);

        return back();
    }

    public function destroy(
        Request $request,
        CartItem $cartItem,
        CartService $cartService,
    ): RedirectResponse {
        $cartService->removeItem($request, $cartItem);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item removed from cart.')]);

        return back();
    }

    public function clear(Request $request, CartService $cartService): RedirectResponse
    {
        $cartService->clear($request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shopping cart cleared.')]);

        return back();
    }
}

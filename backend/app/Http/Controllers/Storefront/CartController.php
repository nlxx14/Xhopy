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
            'selected_color' => ['nullable', 'string', 'max:80'],
            'selected_size' => ['nullable', 'string', 'max:80'],
            'buy_now' => ['nullable', 'boolean'],
        ]);

        $cartService->addItem(
            $request,
            (int) $validated['product_id'],
            (int) ($validated['quantity'] ?? 1),
            isset($validated['selected_color']) ? (string) $validated['selected_color'] : null,
            isset($validated['selected_size']) ? (string) $validated['selected_size'] : null,
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
            'quantity' => ['nullable', 'integer', 'min:0', 'max:'.CartItem::MAX_QUANTITY_PER_LINE],
            'selected_color' => ['nullable', 'string', 'max:80'],
            'selected_size' => ['nullable', 'string', 'max:80'],
        ]);

        if ($request->has('selected_color') || $request->has('selected_size')) {
            $cartService->updateItemVariant(
                $request,
                $cartItem,
                $request->has('selected_color') ? ($validated['selected_color'] !== null ? (string) $validated['selected_color'] : null) : $cartItem->selected_color,
                $request->has('selected_size') ? ($validated['selected_size'] !== null ? (string) $validated['selected_size'] : null) : $cartItem->selected_size,
                isset($validated['quantity']) ? (int) $validated['quantity'] : null,
            );

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Cart variant updated.')]);

            return back();
        }

        if (! array_key_exists('quantity', $validated) || $validated['quantity'] === null) {
            $cartService->updateItemQuantity($request, $cartItem, $cartItem->quantity);

            return back();
        }

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

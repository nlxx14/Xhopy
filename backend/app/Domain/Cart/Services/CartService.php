<?php

namespace App\Domain\Cart\Services;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Catalog\Support\Money;
use App\Domain\Catalog\Support\ProductPresenter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Server-authoritative cart management and price calculation.
 * Never trusts client-submitted prices or totals.
 */
final class CartService
{
    private const SESSION_KEY = 'xhopy.cart_token';

    public function __construct(private readonly ProductPresenter $presenter) {}

    /**
     * Resolve the active cart without creating a database row if empty.
     */
    public function findActiveCart(Request $request): ?Cart
    {
        $user = $request->user();
        $sessionToken = $request->hasSession() ? (string) $request->session()->get(self::SESSION_KEY, '') : '';

        if ($user instanceof User) {
            $userCart = Cart::query()->where('user_id', $user->id)->first();

            if ($sessionToken !== '') {
                $guestCart = Cart::query()
                    ->whereNull('user_id')
                    ->where('session_token', $sessionToken)
                    ->first();

                if ($guestCart !== null) {
                    $userCart = $this->mergeGuestCart($user, $guestCart, $userCart);
                    $request->session()->forget(self::SESSION_KEY);
                }
            }

            return $userCart;
        }

        if ($sessionToken === '') {
            return null;
        }

        return Cart::query()
            ->whereNull('user_id')
            ->where('session_token', $sessionToken)
            ->first();
    }

    /**
     * Resolve or create a persistent cart for the current user or session.
     */
    public function getOrCreateCart(Request $request): Cart
    {
        $existing = $this->findActiveCart($request);
        if ($existing !== null) {
            return $existing;
        }

        $user = $request->user();

        if ($user instanceof User) {
            return Cart::query()->create([
                'user_id' => $user->id,
                'currency' => 'PHP',
            ]);
        }

        $token = Str::random(40);
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $token);
        }

        return Cart::query()->create([
            'session_token' => $token,
            'currency' => 'PHP',
        ]);
    }

    public function addItem(
        Request $request,
        int $productId,
        int $quantity = 1,
        ?string $selectedColor = null,
        ?string $selectedSize = null,
    ): CartItem {
        $product = Product::query()->published()->find($productId);

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => __('This product is not currently available.'),
            ]);
        }

        if (! $product->isInStock()) {
            throw ValidationException::withMessages([
                'quantity' => __('This product is currently out of stock.'),
            ]);
        }

        $resolvedColor = $this->resolveOption($product->available_colors ?? [], $selectedColor, 'selected_color', 'color');
        $resolvedSize = $this->resolveOption($product->available_sizes ?? [], $selectedSize, 'selected_size', 'size');
        $variantStock = $product->stockForVariant($resolvedColor, $resolvedSize);

        if ($variantStock <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('The selected option for :name is currently out of stock.', [
                    'name' => $product->name,
                ]),
            ]);
        }

        return DB::transaction(function () use ($request, $product, $quantity, $resolvedColor, $resolvedSize, $variantStock): CartItem {
            $cart = $this->getOrCreateCart($request);

            $existing = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->where('selected_color', $resolvedColor)
                ->where('selected_size', $resolvedSize)
                ->lockForUpdate()
                ->first();

            $otherLinesQuantity = (int) CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->when($existing !== null, fn ($q) => $q->where('id', '!=', $existing->id))
                ->sum('quantity');

            $desiredQuantity = ($existing->quantity ?? 0) + max(1, $quantity);
            $availableBudget = $product->hasVariantStocks()
                ? $variantStock
                : max(0, $product->stock_quantity - $otherLinesQuantity);
            $maxAllowedForLine = min($availableBudget, CartItem::MAX_QUANTITY_PER_LINE);

            if ($desiredQuantity > $maxAllowedForLine) {
                throw ValidationException::withMessages([
                    'quantity' => __('Only :max units of :name can be added to your cart.', [
                        'max' => min($variantStock, CartItem::MAX_QUANTITY_PER_LINE),
                        'name' => $product->name,
                    ]),
                ]);
            }

            if ($existing !== null) {
                $existing->quantity = $desiredQuantity;
                $existing->save();

                return $existing;
            }

            return CartItem::query()->create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $desiredQuantity,
                'selected_color' => $resolvedColor,
                'selected_size' => $resolvedSize,
            ]);
        });
    }

    public function updateItemQuantity(Request $request, CartItem $item, int $quantity): ?CartItem
    {
        $this->ensureItemBelongsToCurrentCart($request, $item);

        if ($quantity <= 0) {
            $item->delete();

            return null;
        }

        $product = Product::query()->published()->find($item->product_id);

        if ($product === null || ! $product->isInStock()) {
            throw ValidationException::withMessages([
                'quantity' => __('This product is no longer available in stock.'),
            ]);
        }

        $variantStock = $product->stockForVariant($item->selected_color, $item->selected_size);

        if ($variantStock <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('This product is no longer available in stock.'),
            ]);
        }

        $maxAllowed = min($variantStock, CartItem::MAX_QUANTITY_PER_LINE);

        if ($quantity > $maxAllowed) {
            throw ValidationException::withMessages([
                'quantity' => __('Only :max units of :name are available.', [
                    'max' => $maxAllowed,
                    'name' => $product->name,
                ]),
            ]);
        }

        $item->quantity = $quantity;
        $item->save();

        return $item;
    }

    public function updateItemVariant(
        Request $request,
        CartItem $item,
        ?string $selectedColor,
        ?string $selectedSize,
        ?int $requestedQuantity = null,
    ): CartItem {
        $this->ensureItemBelongsToCurrentCart($request, $item);

        $product = Product::query()->published()->find($item->product_id);

        if ($product === null || ! $product->isInStock()) {
            throw ValidationException::withMessages([
                'quantity' => __('This product is no longer available in stock.'),
            ]);
        }

        $resolvedColor = $this->resolveOption($product->available_colors ?? [], $selectedColor, 'selected_color', 'color');
        $resolvedSize = $this->resolveOption($product->available_sizes ?? [], $selectedSize, 'selected_size', 'size');
        $variantStock = $product->stockForVariant($resolvedColor, $resolvedSize);

        if ($variantStock <= 0) {
            throw ValidationException::withMessages([
                'selected_color' => __('The selected option for :name is currently out of stock.', [
                    'name' => $product->name,
                ]),
            ]);
        }

        return DB::transaction(function () use ($item, $product, $resolvedColor, $resolvedSize, $variantStock, $requestedQuantity): CartItem {
            $duplicate = CartItem::query()
                ->where('cart_id', $item->cart_id)
                ->where('product_id', $product->id)
                ->where('selected_color', $resolvedColor)
                ->where('selected_size', $resolvedSize)
                ->where('id', '!=', $item->id)
                ->lockForUpdate()
                ->first();

            $otherLinesQuantity = $product->hasVariantStocks()
                ? 0
                : (int) CartItem::query()
                    ->where('cart_id', $item->cart_id)
                    ->where('product_id', $product->id)
                    ->where('id', '!=', $item->id)
                    ->when($duplicate !== null, fn ($q) => $q->where('id', '!=', $duplicate->id))
                    ->sum('quantity');

            $availableBudget = $product->hasVariantStocks()
                ? $variantStock
                : max(0, $product->stock_quantity - $otherLinesQuantity);
            $maxAllowed = max(1, min($availableBudget, CartItem::MAX_QUANTITY_PER_LINE));

            if ($duplicate !== null) {
                $duplicate->quantity = min($maxAllowed, $duplicate->quantity + $item->quantity);
                $duplicate->save();
                $item->delete();

                return $duplicate;
            }

            $nextQuantity = $requestedQuantity !== null
                ? min($maxAllowed, max(1, $requestedQuantity))
                : min($maxAllowed, $item->quantity);

            $item->selected_color = $resolvedColor;
            $item->selected_size = $resolvedSize;
            $item->quantity = $nextQuantity;
            $item->save();

            return $item;
        });
    }

    public function removeItem(Request $request, CartItem $item): void
    {
        $this->ensureItemBelongsToCurrentCart($request, $item);
        $item->delete();
    }

    public function clear(Request $request): void
    {
        $cart = $this->findActiveCart($request);
        $cart?->items()->delete();
    }

    /**
     * Compute server-authoritative cart lines and totals from current database records.
     *
     * @return array{
     *     items: list<array<string, mixed>>,
     *     itemCount: int,
     *     currency: string,
     *     subtotalCents: int,
     *     subtotalFormatted: string,
     *     hasUnavailableItems: bool
     * }
     */
    public function present(Request $request): array
    {
        $cart = $this->findActiveCart($request);

        if ($cart === null) {
            return [
                'items' => [],
                'itemCount' => 0,
                'currency' => 'PHP',
                'subtotalCents' => 0,
                'subtotalFormatted' => Money::formatCents(0, 'PHP'),
                'hasUnavailableItems' => false,
            ];
        }

        $cart->load(['items.product.category', 'items.product.primaryImage', 'items.product.images']);

        $items = [];
        $itemCount = 0;
        $subtotalCents = 0;
        $hasUnavailableItems = false;

        foreach ($cart->items as $cartItem) {
            $product = $cartItem->product;

            if ($product === null) {
                $hasUnavailableItems = true;

                continue;
            }

            $isPublished = $product->status === ProductStatus::Active
                && $product->published_at !== null
                && $product->published_at->lte(now())
                && $product->category->is_active;

            $availableStock = $isPublished ? $product->stockForVariant($cartItem->selected_color, $cartItem->selected_size) : 0;
            $isAvailable = $isPublished && $availableStock >= $cartItem->quantity;

            if (! $isAvailable) {
                $hasUnavailableItems = true;
            }

            $unitCents = Money::cents($product->price);
            $lineCents = $unitCents * $cartItem->quantity;

            if ($isAvailable) {
                $subtotalCents += $lineCents;
            }

            $itemCount += $cartItem->quantity;
            $selectedColor = $cartItem->selected_color;
            $matchedColorImage = $selectedColor !== null && $selectedColor !== ''
                ? $product->images->first(
                    fn (ProductImage $img): bool => $img->color_name !== null
                        && strcasecmp($img->color_name, $selectedColor) === 0,
                )
                : null;
            $image = $matchedColorImage ?? $product->primaryImage;

            $items[] = [
                'id' => $cartItem->id,
                'productId' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'categoryName' => $product->category->name,
                'unitPriceFormatted' => Money::formatCents($unitCents, $product->currency),
                'lineTotalFormatted' => Money::formatCents($lineCents, $product->currency),
                'quantity' => $cartItem->quantity,
                'maxQuantity' => max(1, min($availableStock, CartItem::MAX_QUANTITY_PER_LINE)),
                'availableStock' => $availableStock,
                'isAvailable' => $isAvailable,
                'availabilityLabel' => ! $isPublished
                    ? 'No longer available'
                    : ($availableStock === 0
                        ? 'Out of stock'
                        : ($availableStock < $cartItem->quantity
                            ? "Only {$availableStock} left in stock"
                            : 'In Stock')),
                'imageUrl' => $this->presenter->imageUrl($image?->path),
                'imageAlt' => $image?->alt_text ?: $product->name,
                'selectedColor' => $cartItem->selected_color,
                'selectedSize' => $cartItem->selected_size,
                'availableColors' => $product->available_colors ?? [],
                'availableSizes' => $product->available_sizes ?? [],
                'variantStocks' => $product->variant_stocks,
            ];
        }

        return [
            'items' => $items,
            'itemCount' => $itemCount,
            'currency' => $cart->currency,
            'subtotalCents' => $subtotalCents,
            'subtotalFormatted' => Money::formatCents($subtotalCents, $cart->currency),
            'hasUnavailableItems' => $hasUnavailableItems,
        ];
    }

    /**
     * Header shell summary.
     *
     * @return array{itemCount: int, subtotalFormatted: string|null}
     */
    public function summary(Request $request): array
    {
        $presented = $this->present($request);

        return [
            'itemCount' => $presented['itemCount'],
            'subtotalFormatted' => $presented['itemCount'] > 0 ? $presented['subtotalFormatted'] : null,
        ];
    }

    private function ensureItemBelongsToCurrentCart(Request $request, CartItem $item): void
    {
        $cart = $this->findActiveCart($request);

        if ($cart === null || $item->cart_id !== $cart->id) {
            abort(403);
        }
    }

    /**
     * @param  list<string>  $availableOptions
     */
    private function resolveOption(
        array $availableOptions,
        ?string $selected,
        string $fieldKey,
        string $label,
    ): ?string {
        if ($availableOptions === []) {
            return null;
        }

        $trimmed = $selected !== null ? trim($selected) : '';

        if ($trimmed === '') {
            return $availableOptions[0];
        }

        if (! in_array($trimmed, $availableOptions, true)) {
            throw ValidationException::withMessages([
                $fieldKey => __('Please select a valid :label option.', ['label' => $label]),
            ]);
        }

        return $trimmed;
    }

    private function mergeGuestCart(User $user, Cart $guestCart, ?Cart $userCart): Cart
    {
        return DB::transaction(function () use ($user, $guestCart, $userCart): Cart {
            if ($userCart === null) {
                $guestCart->update([
                    'user_id' => $user->id,
                    'session_token' => null,
                ]);

                return $guestCart;
            }

            $guestCart->load('items');

            foreach ($guestCart->items as $guestItem) {
                $existing = CartItem::query()
                    ->where('cart_id', $userCart->id)
                    ->where('product_id', $guestItem->product_id)
                    ->where('selected_color', $guestItem->selected_color)
                    ->where('selected_size', $guestItem->selected_size)
                    ->first();

                if ($existing !== null) {
                    $existing->quantity = min(
                        CartItem::MAX_QUANTITY_PER_LINE,
                        $existing->quantity + $guestItem->quantity,
                    );
                    $existing->save();
                } else {
                    CartItem::query()->create([
                        'cart_id' => $userCart->id,
                        'product_id' => $guestItem->product_id,
                        'quantity' => min(CartItem::MAX_QUANTITY_PER_LINE, $guestItem->quantity),
                        'selected_color' => $guestItem->selected_color,
                        'selected_size' => $guestItem->selected_size,
                    ]);
                }
            }

            $guestCart->delete();

            return $userCart;
        });
    }
}

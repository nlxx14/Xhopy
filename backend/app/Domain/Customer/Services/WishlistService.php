<?php

namespace App\Domain\Customer\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Support\ProductPresenter;
use App\Domain\Customer\Models\WishlistItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class WishlistService
{
    public function __construct(private readonly ProductPresenter $presenter) {}

    /**
     * Toggle a product in the user's wishlist. Returns true when added, false when removed.
     */
    public function toggle(User $user, int $productId): bool
    {
        $existing = WishlistItem::query()
            ->where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        $product = Product::query()->published()->find($productId);

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => __('This product is not available to save to your wishlist.'),
            ]);
        }

        WishlistItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        return true;
    }

    public function remove(User $user, int $productId): void
    {
        WishlistItem::query()
            ->where('user_id', $user->id)
            ->where('product_id', $productId)
            ->delete();
    }

    /**
     * @return list<int>
     */
    public function wishlistedProductIds(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_values(
            WishlistItem::query()
                ->where('user_id', $user->id)
                ->pluck('product_id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function presentForUser(User $user): array
    {
        $publishedIds = Product::query()->published()->select('products.id');

        return array_values(
            WishlistItem::query()
                ->where('user_id', $user->id)
                ->whereIn('product_id', $publishedIds)
                ->with(['product.category:id,name,slug', 'product.primaryImage'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(12)
                ->get()
                ->map(function (WishlistItem $item): ?array {
                    $product = $item->product;
                    if ($product === null) {
                        return null;
                    }

                    return [
                        ...$this->presenter->summary($product, true),
                        'addedAtDiff' => $item->created_at?->diffForHumans() ?? 'Recently',
                    ];
                })
                ->filter()
                ->all(),
        );
    }
}

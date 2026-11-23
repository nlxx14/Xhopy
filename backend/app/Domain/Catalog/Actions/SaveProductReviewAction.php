<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductReview;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class SaveProductReviewAction
{
    public function findQualifyingOrderForProduct(User $user, int $productId): ?Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::Delivered)
            ->whereHas('items', static function ($query) use ($productId): void {
                $query->where('product_id', $productId);
            })
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->first();
    }

    public function execute(
        User $user,
        Product $product,
        int $rating,
        ?string $title = null,
        ?string $comment = null,
    ): ProductReview {
        $qualifyingOrder = $this->findQualifyingOrderForProduct($user, $product->id);

        if ($qualifyingOrder === null) {
            throw ValidationException::withMessages([
                'rating' => __('You can rate and review this product only after your order has been delivered.'),
            ]);
        }

        $normalizedTitle = $title !== null && trim($title) !== '' ? trim($title) : null;
        $normalizedComment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;

        return ProductReview::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'user_id' => $user->id,
            ],
            [
                'order_id' => $qualifyingOrder->id,
                'rating' => max(1, min(5, $rating)),
                'title' => $normalizedTitle,
                'comment' => $normalizedComment,
                'is_verified_purchase' => true,
            ],
        );
    }
}

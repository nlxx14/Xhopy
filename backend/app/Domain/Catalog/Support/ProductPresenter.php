<?php

namespace App\Domain\Catalog\Support;

use App\Domain\Catalog\Models\CampaignContent;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

/**
 * Maps catalog models to the exact prop shapes consumed by the Vue storefront.
 * Server-authoritative: prices, discounts and availability are derived here from trusted records.
 */
final class ProductPresenter
{
    /**
     * Card-level summary. Requires the `category` and `primaryImage` relations to be eager loaded.
     *
     * @return array<string, mixed>
     */
    public function summary(Product $product, bool $isWishlisted = false): array
    {
        $image = $product->primaryImage;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'categoryName' => $product->category->name,
            'categorySlug' => $product->category->slug,
            'sellerName' => null,
            ...$this->pricing($product),
            'inStock' => $product->isInStock(),
            'stockLabel' => $product->isInStock() ? 'In Stock' : 'Out of Stock',
            'imageUrl' => $this->imageUrl($image?->path),
            'imageAlt' => $this->imageAlt($image, $product),
            'isWishlisted' => $isWishlisted,
            // No reviews exist yet; never fabricate ratings.
            'ratingAverage' => null,
            'ratingCount' => null,
        ];
    }

    /**
     * Full detail view. Requires the `category` and `images` relations to be eager loaded.
     *
     * @return array<string, mixed>
     */
    public function detail(Product $product, bool $isWishlisted = false): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'categoryName' => $product->category->name,
            'categorySlug' => $product->category->slug,
            'sellerName' => null,
            ...$this->pricing($product),
            'inStock' => $product->isInStock(),
            'stockLabel' => $product->isInStock() ? 'In Stock' : 'Out of Stock',
            'shortDescription' => $product->short_description,
            'description' => $product->description,
            'images' => $product->images
                ->map(fn (ProductImage $image): array => [
                    'id' => $image->id,
                    'url' => $this->imageUrl($image->path),
                    'alt' => $this->imageAlt($image, $product),
                ])
                ->values()
                ->all(),
            'imageUrl' => $this->imageUrl($product->images->first()?->path),
            'imageAlt' => $this->imageAlt($product->images->first(), $product),
            'isWishlisted' => $isWishlisted,
            'ratingAverage' => null,
            'ratingCount' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function category(Category $category, ?int $productCount = null): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'productCount' => $productCount,
            'imageUrl' => $this->imageUrl($category->image_path),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function campaign(CampaignContent $content): array
    {
        return [
            'id' => 'campaign-'.$content->id,
            'tag' => $content->tag,
            'title' => $content->title,
            'summary' => $content->summary,
            'ctaLabel' => $content->cta_label,
            'href' => $content->href,
            'imageUrl' => $this->imageUrl($content->image_path),
            'imageAlt' => $content->image_alt,
        ];
    }

    public function imageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Whether a campaign destination is safe to render as a link: a same-site path or an https URL.
     */
    public static function isSafeHref(string $href): bool
    {
        if (str_starts_with($href, '/')) {
            return ! str_starts_with($href, '//') && ! str_contains($href, '\\');
        }

        return str_starts_with($href, 'https://');
    }

    /**
     * @return array<string, mixed>
     */
    private function pricing(Product $product): array
    {
        $discounted = $product->hasDiscount();
        $priceCents = Money::cents($product->price);

        return [
            'unitPrice' => $priceCents / 100,
            'priceFormatted' => Money::formatCents($priceCents, $product->currency),
            'originalPrice' => $discounted ? Money::cents((string) $product->compare_at_price) / 100 : null,
            'originalPriceFormatted' => $discounted
                ? Money::format((string) $product->compare_at_price, $product->currency)
                : null,
            'discountLabel' => $discounted
                ? 'Save '.Money::formatCents(Money::cents((string) $product->compare_at_price) - $priceCents, $product->currency)
                : null,
            'currency' => $product->currency,
        ];
    }

    private function imageAlt(?ProductImage $image, Product $product): string
    {
        return $image !== null && $image->alt_text !== null && $image->alt_text !== ''
            ? $image->alt_text
            : $product->name;
    }
}

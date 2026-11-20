<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Heart,
    ShieldCheck,
    ShoppingCart,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import {
    CartPreviewDialog,
    useCartPreview,
    type CartSummary,
} from '@modules/cart';
import PriceDisplay from '@modules/catalog/components/PriceDisplay.vue';
import ProductCard from '@modules/catalog/components/ProductCard.vue';
import ProductGallery from '@modules/catalog/components/ProductGallery.vue';
import QuantitySelector from '@modules/catalog/components/QuantitySelector.vue';
import RatingDisplay from '@modules/catalog/components/RatingDisplay.vue';
import type { ProductDetail, ProductSummary } from '@modules/catalog/types';
import {
    StorefrontLayout,
    type AnnouncementBanner,
    type CategorySummary,
} from '@modules/storefront';
import Breadcrumbs from '@shared/components/Breadcrumbs.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import type { BreadcrumbItem } from '@shared/types';
import { home } from '@/routes';
import { store as storeCartItem } from '@/routes/cart/items';
import { index as productsIndex, show as productShow } from '@/routes/products';
import { toggle as toggleWishlistRoute } from '@/routes/wishlist';

const props = withDefaults(
    defineProps<{
        announcement?: AnnouncementBanner | null;
        categories: CategorySummary[];
        cartSummary: CartSummary;
        product: ProductDetail;
        relatedProducts?: ProductSummary[];
    }>(),
    {
        announcement: null,
        relatedProducts: () => [],
    },
);

const cartPreview = useCartPreview();
const quantity = ref(1);
const justAdded = ref(false);
const statusAnnouncement = ref('');
let resetTimer: number | undefined;

onBeforeUnmount(() => {
    if (resetTimer !== undefined) {
        window.clearTimeout(resetTimer);
    }
});

const effectiveCart = computed<CartSummary>(() => ({
    itemCount: props.cartSummary.itemCount + cartPreview.itemCount.value,
    subtotalFormatted: props.cartSummary.subtotalFormatted,
}));

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const items: BreadcrumbItem[] = [
        { title: 'Home', href: home.url() },
        { title: 'Products', href: productsIndex.url() },
    ];

    if (props.product.categoryName && props.product.categorySlug) {
        items.push({
            title: props.product.categoryName,
            href: productsIndex.url({
                query: { category: props.product.categorySlug },
            }),
        });
    }

    items.push({
        title: props.product.name,
        href: productShow.url(props.product.slug),
    });

    return items;
});

function handleSearch(query: string): void {
    router.get(
        productsIndex.url({
            query: { q: query || undefined },
        }),
    );
}

function handleSelectCategory(slug: string | null): void {
    router.get(
        productsIndex.url({
            query: { category: slug || undefined },
        }),
    );
}

function handleAddCurrentToCart(): void {
    if (!props.product.inStock) {
        return;
    }

    for (let i = 0; i < quantity.value; i += 1) {
        cartPreview.add({
            name: props.product.name,
            priceFormatted: props.product.priceFormatted,
        });
    }

    if (typeof props.product.id === 'number') {
        router.post(
            storeCartItem.url(),
            { product_id: props.product.id, quantity: quantity.value },
            { preserveScroll: true, preserveState: true },
        );
    }

    if (resetTimer !== undefined) {
        window.clearTimeout(resetTimer);
    }

    justAdded.value = true;
    statusAnnouncement.value = `Added ${quantity.value} × ${props.product.name} to cart.`;

    resetTimer = window.setTimeout(() => {
        justAdded.value = false;
        statusAnnouncement.value = '';
    }, 1800);
}

function handleAddRelatedToCart(item: ProductSummary): void {
    cartPreview.add({
        name: item.name,
        priceFormatted: item.priceFormatted,
    });

    if (typeof item.id === 'number') {
        router.post(
            storeCartItem.url(),
            { product_id: item.id, quantity: 1 },
            { preserveScroll: true, preserveState: true },
        );
    }
}

function handleToggleWishlist(): void {
    if (typeof props.product.id !== 'number') {
        return;
    }

    router.post(
        toggleWishlistRoute.url(),
        { product_id: props.product.id },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head :title="`${product.name} — XHopy`" />

    <StorefrontLayout
        :announcement="announcement"
        :categories="categories"
        :cart="effectiveCart"
        :selected-category="product.categorySlug ?? null"
        @search="handleSearch"
        @select-category="handleSelectCategory"
        @open-cart="cartPreview.open()"
    >
        <div
            class="mx-auto max-w-7xl space-y-12 px-4 py-8 sm:px-6 sm:py-10 lg:px-8"
        >
            <!-- Top navigation & Breadcrumbs -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />

                <Link
                    :href="productsIndex.url()"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                >
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    <span>Back to catalog</span>
                </Link>
            </div>

            <!-- Product Main Grid: Media Gallery + Product Details -->
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:gap-12">
                <div class="lg:col-span-6">
                    <ProductGallery
                        :images="product.images"
                        :product-name="product.name"
                    />
                </div>

                <div
                    class="flex flex-col justify-between space-y-6 lg:col-span-6"
                >
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                v-if="
                                    product.categoryName && product.categorySlug
                                "
                                :href="
                                    productsIndex.url({
                                        query: {
                                            category: product.categorySlug,
                                        },
                                    })
                                "
                                class="text-xs font-semibold tracking-wider text-primary uppercase hover:underline"
                            >
                                {{ product.categoryName }}
                            </Link>

                            <Badge
                                :variant="
                                    product.inStock ? 'secondary' : 'outline'
                                "
                                class="text-xs font-medium"
                            >
                                {{ product.stockLabel }}
                            </Badge>

                            <span
                                v-if="product.sku"
                                class="text-xs text-muted-foreground"
                            >
                                SKU: {{ product.sku }}
                            </span>
                        </div>

                        <h1
                            class="text-2xl leading-tight font-extrabold tracking-tight text-foreground sm:text-3xl"
                        >
                            {{ product.name }}
                        </h1>

                        <RatingDisplay
                            :rating-average="product.ratingAverage"
                            :rating-count="product.ratingCount"
                        />

                        <p
                            v-if="product.sellerName"
                            class="text-xs text-muted-foreground"
                        >
                            Sold by {{ product.sellerName }}
                        </p>

                        <div class="border-y border-border py-4">
                            <PriceDisplay
                                size="lg"
                                :price-formatted="product.priceFormatted"
                                :original-price-formatted="
                                    product.originalPriceFormatted
                                "
                                :discount-label="product.discountLabel"
                            />
                        </div>

                        <p
                            v-if="product.shortDescription"
                            class="text-sm leading-relaxed text-foreground/90 sm:text-base"
                        >
                            {{ product.shortDescription }}
                        </p>

                        <!-- Purchase Controls -->
                        <div class="space-y-4 pt-2">
                            <div
                                class="flex flex-wrap items-center gap-3 sm:flex-nowrap"
                            >
                                <QuantitySelector
                                    v-model="quantity"
                                    :disabled="!product.inStock"
                                />

                                <Button
                                    type="button"
                                    size="lg"
                                    class="h-11 flex-1 gap-2 text-sm font-semibold"
                                    :variant="
                                        product.inStock
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    :disabled="!product.inStock"
                                    :aria-label="
                                        product.inStock
                                            ? `Add ${product.name} to cart`
                                            : `${product.name} is out of stock`
                                    "
                                    @click="handleAddCurrentToCart"
                                >
                                    <Check
                                        v-if="justAdded"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <ShoppingCart
                                        v-else
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span>
                                        {{
                                            !product.inStock
                                                ? 'Out of Stock'
                                                : justAdded
                                                  ? 'Added to Cart'
                                                  : 'Add to Cart'
                                        }}
                                    </span>
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    class="size-11 shrink-0 rounded-xl"
                                    :aria-label="
                                        product.isWishlisted
                                            ? `Remove ${product.name} from wishlist`
                                            : `Save ${product.name} to wishlist`
                                    "
                                    :aria-pressed="
                                        Boolean(product.isWishlisted)
                                    "
                                    @click="handleToggleWishlist"
                                >
                                    <Heart
                                        :class="[
                                            'size-4',
                                            product.isWishlisted &&
                                                'fill-primary text-primary',
                                        ]"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </div>

                            <span class="sr-only" aria-live="polite">
                                {{ statusAnnouncement }}
                            </span>
                        </div>

                        <!-- Description Section -->
                        <section
                            v-if="product.description"
                            aria-labelledby="product-description-heading"
                            class="space-y-2 border-t border-border pt-6"
                        >
                            <h2
                                id="product-description-heading"
                                class="text-sm font-semibold tracking-wider text-foreground uppercase"
                            >
                                Product Details
                            </h2>
                            <p
                                class="text-sm leading-relaxed whitespace-pre-line text-muted-foreground"
                            >
                                {{ product.description }}
                            </p>
                        </section>
                    </div>

                    <div
                        class="flex items-center gap-2 rounded-xl border border-border bg-secondary/40 px-4 py-3 text-xs text-muted-foreground"
                    >
                        <ShieldCheck
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span>
                            Server-authoritative pricing and stock availability
                            are re-verified at checkout.
                        </span>
                    </div>
                </div>
            </div>

            <!-- Related Products Section -->
            <section
                v-if="relatedProducts.length > 0"
                aria-labelledby="related-products-heading"
                class="space-y-5 border-t border-border pt-10"
            >
                <div class="flex items-end justify-between gap-2">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wider text-primary uppercase"
                        >
                            More in This Category
                        </p>
                        <h2
                            id="related-products-heading"
                            class="mt-1 text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                        >
                            Related Products
                        </h2>
                    </div>

                    <Link
                        v-if="product.categorySlug"
                        :href="
                            productsIndex.url({
                                query: { category: product.categorySlug },
                            })
                        "
                        class="text-xs font-semibold text-primary hover:underline"
                    >
                        View all
                    </Link>
                </div>

                <div
                    class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <ProductCard
                        v-for="item in relatedProducts"
                        :key="item.id"
                        :product="item"
                        @add-to-cart="handleAddRelatedToCart"
                    />
                </div>
            </section>
        </div>

        <CartPreviewDialog />
    </StorefrontLayout>
</template>

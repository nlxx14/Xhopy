<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    CheckCircle2,
    Heart,
    MessageSquareText,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Star,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
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
import {
    hasCustomVariantStocks,
    isOptionOptionInStock,
    pickInitialVariant,
    resolveVariantStock,
} from '@modules/catalog/utils/variantStock';
import type { ProductDetail, ProductSummary } from '@modules/catalog/types';
import {
    StorefrontLayout,
    type AnnouncementBanner,
    type CategorySummary,
} from '@modules/storefront';
import Breadcrumbs from '@shared/components/Breadcrumbs.vue';
import InputError from '@shared/components/InputError.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import type { BreadcrumbItem } from '@shared/types';
import { home, login } from '@/routes';
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

const initialVariant = pickInitialVariant(props.product);
const cartPreview = useCartPreview();
const quantity = ref(1);
const selectedColor = ref<string | null>(initialVariant.color);
const selectedSize = ref<string | null>(initialVariant.size);
const justAdded = ref(false);
const statusAnnouncement = ref('');
let resetTimer: number | undefined;

const activeVariantStock = computed<number>(() =>
    resolveVariantStock(props.product, selectedColor.value, selectedSize.value),
);

const activeVariantInStock = computed<boolean>(
    () => props.product.inStock && activeVariantStock.value > 0,
);

const maxOrderQuantity = computed<number>(() =>
    Math.min(20, Math.max(1, activeVariantStock.value)),
);

const activeStockBadgeLabel = computed<string>(() => {
    if (!activeVariantInStock.value) {
        return 'Out of Stock';
    }
    if (hasCustomVariantStocks(props.product)) {
        return `${activeVariantStock.value} in stock`;
    }
    if (typeof props.product.stockQuantity === 'number') {
        return `In Stock (${props.product.stockQuantity})`;
    }
    return props.product.stockLabel;
});

watch(activeVariantStock, (nextStock) => {
    const cap = Math.min(20, Math.max(1, nextStock));
    if (quantity.value > cap) {
        quantity.value = cap;
    }
});

watch(
    () => props.product.id,
    () => {
        const picked = pickInitialVariant(props.product);
        selectedColor.value = picked.color;
        selectedSize.value = picked.size;
        quantity.value = 1;
    },
);

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
    if (!activeVariantInStock.value) {
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
            {
                product_id: props.product.id,
                quantity: quantity.value,
                selected_color: selectedColor.value ?? undefined,
                selected_size: selectedSize.value ?? undefined,
            },
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

const isBuyingNow = ref(false);

function handleBuyCurrentNow(): void {
    if (
        !activeVariantInStock.value ||
        isBuyingNow.value ||
        typeof props.product.id !== 'number'
    ) {
        return;
    }

    isBuyingNow.value = true;

    router.post(
        storeCartItem.url(),
        {
            product_id: props.product.id,
            quantity: quantity.value,
            selected_color: selectedColor.value ?? undefined,
            selected_size: selectedSize.value ?? undefined,
            buy_now: true,
        },
        {
            onFinish: () => {
                isBuyingNow.value = false;
            },
        },
    );
}

function handleAddRelatedToCart(item: ProductSummary): void {
    cartPreview.add({
        name: item.name,
        priceFormatted: item.priceFormatted,
    });

    if (typeof item.id === 'number') {
        router.post(
            storeCartItem.url(),
            {
                product_id: item.id,
                quantity: 1,
                selected_color: item.selectedColor ?? undefined,
                selected_size: item.selectedSize ?? undefined,
            },
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

const page = usePage();
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const productReviews = computed(() => props.product.reviews ?? []);

const reviewForm = useForm({
    rating: props.product.userReview?.rating ?? 5,
    title: props.product.userReview?.title ?? '',
    comment: props.product.userReview?.comment ?? '',
});

watch(
    () => props.product.userReview,
    (nextReview) => {
        if (nextReview) {
            reviewForm.rating = nextReview.rating;
            reviewForm.title = nextReview.title ?? '';
            reviewForm.comment = nextReview.comment ?? '';
        }
    },
);

const starLabels: Record<number, string> = {
    1: 'Poor',
    2: 'Fair',
    3: 'Good',
    4: 'Very Good',
    5: 'Excellent',
};

function handleSubmitReview(): void {
    if (typeof props.product.id !== 'number') {
        return;
    }

    reviewForm.post(`/products/${props.product.id}/reviews`, {
        preserveScroll: true,
    });
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
                        :selected-color="selectedColor"
                        @select-color="selectedColor = $event"
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
                                    activeVariantInStock
                                        ? 'secondary'
                                        : 'outline'
                                "
                                class="text-xs font-medium"
                            >
                                {{ activeStockBadgeLabel }}
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

                        <!-- Color & Size Selectors -->
                        <div
                            v-if="
                                (product.availableColors &&
                                    product.availableColors.length > 0) ||
                                (product.availableSizes &&
                                    product.availableSizes.length > 0)
                            "
                            class="space-y-4 rounded-2xl border border-border bg-secondary/25 p-4"
                        >
                            <div
                                v-if="
                                    product.availableColors &&
                                    product.availableColors.length > 0
                                "
                                class="space-y-2"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Select Color
                                    </span>
                                    <span
                                        class="text-xs font-bold text-foreground"
                                    >
                                        {{ selectedColor }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="color in product.availableColors"
                                        :key="color"
                                        type="button"
                                        :class="[
                                            'rounded-xl border px-3.5 py-2 text-xs font-semibold transition-all',
                                            selectedColor === color
                                                ? 'border-primary bg-brand-subtle/70 text-primary shadow-2xs ring-1 ring-primary/30'
                                                : 'border-border bg-card text-foreground/80 hover:border-primary/40 hover:text-foreground',
                                            !isOptionOptionInStock(
                                                product,
                                                'color',
                                                color,
                                            ) && 'line-through opacity-50',
                                        ]"
                                        :aria-pressed="selectedColor === color"
                                        @click="selectedColor = color"
                                    >
                                        {{ color }}
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="
                                    product.availableSizes &&
                                    product.availableSizes.length > 0
                                "
                                class="space-y-2"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Select Size / Spec
                                    </span>
                                    <span
                                        class="text-xs font-bold text-foreground"
                                    >
                                        {{ selectedSize }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="size in product.availableSizes"
                                        :key="size"
                                        type="button"
                                        :class="[
                                            'rounded-xl border px-3.5 py-2 text-xs font-semibold transition-all',
                                            selectedSize === size
                                                ? 'border-primary bg-brand-subtle/70 text-primary shadow-2xs ring-1 ring-primary/30'
                                                : 'border-border bg-card text-foreground/80 hover:border-primary/40 hover:text-foreground',
                                            !isOptionOptionInStock(
                                                product,
                                                'size',
                                                size,
                                            ) && 'line-through opacity-50',
                                        ]"
                                        :aria-pressed="selectedSize === size"
                                        @click="selectedSize = size"
                                    >
                                        {{ size }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Purchase Controls -->
                        <div class="space-y-4 pt-2">
                            <div
                                class="flex flex-wrap items-center gap-3 sm:flex-nowrap"
                            >
                                <QuantitySelector
                                    v-model="quantity"
                                    :max="maxOrderQuantity"
                                    :disabled="!activeVariantInStock"
                                />

                                <Button
                                    type="button"
                                    size="lg"
                                    class="h-11 flex-1 gap-2 rounded-xl text-sm font-semibold"
                                    :variant="
                                        activeVariantInStock
                                            ? 'outline'
                                            : 'secondary'
                                    "
                                    :disabled="
                                        !activeVariantInStock || isBuyingNow
                                    "
                                    :aria-label="
                                        activeVariantInStock
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
                                            !activeVariantInStock
                                                ? 'Out of Stock'
                                                : justAdded
                                                  ? 'Added to Cart'
                                                  : 'Add to Cart'
                                        }}
                                    </span>
                                </Button>

                                <Button
                                    v-if="activeVariantInStock"
                                    type="button"
                                    size="lg"
                                    class="h-11 flex-1 gap-2 rounded-xl text-sm font-semibold"
                                    :disabled="isBuyingNow"
                                    :aria-label="`Buy ${product.name} now`"
                                    @click="handleBuyCurrentNow"
                                >
                                    <ShoppingBag
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span>
                                        {{
                                            isBuyingNow
                                                ? 'Redirecting...'
                                                : 'Buy Now'
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

            <!-- Customer Ratings & Verified Feedback Section -->
            <section
                aria-labelledby="product-reviews-heading"
                class="space-y-6 border-t border-border pt-10"
            >
                <div
                    class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wider text-primary uppercase"
                        >
                            Verified Customer Feedback
                        </p>
                        <h2
                            id="product-reviews-heading"
                            class="mt-1 text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                        >
                            Ratings &amp; Reviews
                        </h2>
                    </div>

                    <p
                        v-if="product.ratingCount"
                        class="text-sm text-muted-foreground"
                    >
                        Based on
                        <strong class="text-foreground">{{
                            product.ratingCount
                        }}</strong>
                        verified customer
                        {{ product.ratingCount === 1 ? 'review' : 'reviews' }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
                    <!-- Left Column: Overall Score + Verified Buyer Form -->
                    <div class="space-y-5 lg:col-span-5">
                        <div
                            class="rounded-2xl border border-border bg-card p-6 shadow-2xs"
                        >
                            <div class="flex items-center gap-4">
                                <div
                                    class="text-4xl font-extrabold text-foreground"
                                >
                                    {{
                                        typeof product.ratingAverage ===
                                        'number'
                                            ? product.ratingAverage.toFixed(1)
                                            : '—'
                                    }}
                                </div>
                                <div class="space-y-1">
                                    <div
                                        class="flex items-center gap-1"
                                        aria-hidden="true"
                                    >
                                        <Star
                                            v-for="star in 5"
                                            :key="star"
                                            :class="[
                                                'size-4',
                                                typeof product.ratingAverage ===
                                                    'number' &&
                                                star <=
                                                    Math.round(
                                                        product.ratingAverage,
                                                    )
                                                    ? 'fill-amber-500 text-amber-500'
                                                    : 'text-muted-foreground/30',
                                            ]"
                                        />
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            product.ratingCount
                                                ? `${product.ratingCount} verified ${product.ratingCount === 1 ? 'rating' : 'ratings'}`
                                                : 'No verified ratings yet'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Review Submission Form (Only for Verified Purchasers) -->
                        <div
                            v-if="product.canReview"
                            class="rounded-2xl border border-border bg-card p-6 shadow-2xs"
                        >
                            <div class="mb-4 space-y-1">
                                <div
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-500"
                                >
                                    <CheckCircle2
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span>Verified Purchaser</span>
                                </div>
                                <h3 class="text-base font-bold text-foreground">
                                    {{
                                        product.userReview
                                            ? 'Update Your Rating & Feedback'
                                            : 'Leave a Rating & Feedback'
                                    }}
                                </h3>
                            </div>

                            <form
                                class="space-y-4"
                                @submit.prevent="handleSubmitReview"
                            >
                                <div class="space-y-2">
                                    <Label>Your Rating</Label>
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="flex items-center gap-1"
                                            role="radiogroup"
                                            aria-label="Select a star rating from 1 to 5"
                                        >
                                            <button
                                                v-for="star in 5"
                                                :key="star"
                                                type="button"
                                                class="inline-flex size-10 items-center justify-center rounded-xl border border-border/70 bg-secondary/30 transition-colors hover:border-amber-500/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                :aria-label="`Rate ${star} out of 5 stars`"
                                                :aria-checked="
                                                    reviewForm.rating === star
                                                "
                                                role="radio"
                                                @click="
                                                    reviewForm.rating = star
                                                "
                                            >
                                                <Star
                                                    :class="[
                                                        'size-5 transition-transform',
                                                        star <=
                                                        reviewForm.rating
                                                            ? 'scale-105 fill-amber-500 text-amber-500'
                                                            : 'text-muted-foreground/40',
                                                    ]"
                                                    aria-hidden="true"
                                                />
                                            </button>
                                        </div>
                                        <span
                                            class="text-xs font-semibold text-muted-foreground"
                                        >
                                            {{ reviewForm.rating }}/5 —
                                            {{ starLabels[reviewForm.rating] }}
                                        </span>
                                    </div>
                                    <InputError
                                        :message="reviewForm.errors.rating"
                                    />
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="product-review-title">
                                        Headline (Optional)
                                    </Label>
                                    <Input
                                        id="product-review-title"
                                        v-model="reviewForm.title"
                                        maxlength="120"
                                        placeholder="Summarize your experience"
                                    />
                                    <InputError
                                        :message="reviewForm.errors.title"
                                    />
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="product-review-comment">
                                        Your Feedback (Optional)
                                    </Label>
                                    <textarea
                                        id="product-review-comment"
                                        v-model="reviewForm.comment"
                                        rows="4"
                                        maxlength="1500"
                                        placeholder="Share your honest thoughts on quality, performance, and value..."
                                        class="flex min-h-24 w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-2xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    <InputError
                                        :message="reviewForm.errors.comment"
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    class="h-10 w-full rounded-xl text-xs font-semibold"
                                    :disabled="reviewForm.processing"
                                >
                                    {{
                                        reviewForm.processing
                                            ? 'Saving...'
                                            : product.userReview
                                              ? 'Update Verified Review'
                                              : 'Submit Verified Review'
                                    }}
                                </Button>
                            </form>
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-border/80 bg-secondary/25 p-5 text-xs text-muted-foreground"
                        >
                            <p class="font-semibold text-foreground">
                                Post-Delivery Review Policy
                            </p>
                            <p class="mt-1 leading-relaxed">
                                Only customers who have received a
                                <strong class="text-foreground"
                                    >Delivered</strong
                                >
                                order for
                                <strong class="text-foreground">{{
                                    product.name
                                }}</strong>
                                can submit a rating and feedback, ensuring every
                                score reflects a completed delivery.
                            </p>
                            <Link
                                v-if="!isAuthenticated"
                                :href="login.url()"
                                class="mt-3 inline-flex font-semibold text-primary hover:underline"
                            >
                                Log in to check your delivery eligibility →
                            </Link>
                        </div>
                    </div>

                    <!-- Right Column: Verified Reviews List -->
                    <div class="lg:col-span-7">
                        <div v-if="productReviews.length > 0" class="space-y-4">
                            <article
                                v-for="review in productReviews"
                                :key="review.id"
                                class="space-y-2.5 rounded-2xl border border-border bg-card p-5 shadow-2xs"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="flex items-center gap-0.5"
                                            :aria-label="`Rated ${review.rating} out of 5 stars`"
                                        >
                                            <Star
                                                v-for="star in 5"
                                                :key="star"
                                                :class="[
                                                    'size-4',
                                                    star <= review.rating
                                                        ? 'fill-amber-500 text-amber-500'
                                                        : 'text-muted-foreground/30',
                                                ]"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        <span
                                            v-if="review.title"
                                            class="text-sm font-bold text-foreground"
                                        >
                                            {{ review.title }}
                                        </span>
                                    </div>

                                    <span
                                        v-if="review.isVerifiedPurchase"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-medium text-emerald-500"
                                    >
                                        <CheckCircle2
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                        <span>Verified Purchase</span>
                                    </span>
                                </div>

                                <p
                                    v-if="review.comment"
                                    class="text-sm leading-relaxed whitespace-pre-line text-foreground/90"
                                >
                                    {{ review.comment }}
                                </p>

                                <p class="text-xs text-muted-foreground">
                                    By
                                    <strong
                                        class="font-medium text-foreground"
                                        >{{ review.authorName }}</strong
                                    >
                                    · {{ review.createdAtFormatted }} ({{
                                        review.createdAtDiff
                                    }})
                                </p>
                            </article>
                        </div>

                        <div
                            v-else
                            class="flex min-h-48 flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-secondary/15 p-8 text-center"
                        >
                            <MessageSquareText
                                class="size-8 text-muted-foreground/60"
                                aria-hidden="true"
                            />
                            <p
                                class="mt-3 text-sm font-semibold text-foreground"
                            >
                                No verified reviews yet
                            </p>
                            <p
                                class="mt-1 max-w-sm text-xs text-muted-foreground"
                            >
                                Be the first verified customer to share your
                                experience after purchasing this item.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

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

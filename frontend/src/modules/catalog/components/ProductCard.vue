<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Briefcase,
    Check,
    Headphones,
    Heart,
    Home,
    Monitor,
    Package,
    ShoppingBag,
    ShoppingCart,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, type Component } from 'vue';
import PriceDisplay from '@modules/catalog/components/PriceDisplay.vue';
import RatingDisplay from '@modules/catalog/components/RatingDisplay.vue';
import {
    hasCustomVariantStocks,
    isOptionOptionInStock,
    pickInitialVariant,
    resolveVariantStock,
} from '@modules/catalog/utils/variantStock';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { store as storeCartItemRoute } from '@/routes/cart/items';
import { show } from '@/routes/products';
import { toggle as toggleWishlistRoute } from '@/routes/wishlist';
import type { ProductSummary } from '@modules/catalog/types';

const props = withDefaults(
    defineProps<{
        product: ProductSummary;
        linkable?: boolean;
    }>(),
    { linkable: true },
);

const emit = defineEmits<{
    addToCart: [product: ProductSummary];
}>();

const initialVariant = pickInitialVariant(props.product);
const isAdding = ref(false);
const justAdded = ref(false);
const statusAnnouncement = ref('');
const selectedColor = ref<string | null>(initialVariant.color);
const selectedSize = ref<string | null>(initialVariant.size);

const activeVariantStock = computed<number>(() =>
    resolveVariantStock(props.product, selectedColor.value, selectedSize.value),
);

const activeVariantInStock = computed<boolean>(
    () => props.product.inStock && activeVariantStock.value > 0,
);

const activeStockBadgeLabel = computed<string>(() => {
    if (!activeVariantInStock.value) {
        return 'Out of Stock';
    }
    if (hasCustomVariantStocks(props.product)) {
        return `${activeVariantStock.value} in stock`;
    }
    return props.product.stockLabel;
});

const activeImageUrl = computed<string | null>(() => {
    if (selectedColor.value && props.product.colorImages) {
        const exact = props.product.colorImages[selectedColor.value];
        if (exact) {
            return exact;
        }
        const targetLower = selectedColor.value.toLowerCase();
        for (const [key, url] of Object.entries(props.product.colorImages)) {
            if (key.toLowerCase() === targetLower && url) {
                return url;
            }
        }
    }

    return props.product.imageUrl ?? null;
});

let addTimer: number | undefined;
let resetTimer: number | undefined;

onBeforeUnmount(() => {
    if (addTimer !== undefined) {
        window.clearTimeout(addTimer);
    }
    if (resetTimer !== undefined) {
        window.clearTimeout(resetTimer);
    }
});

const departmentIcon = computed<Component>(() => {
    const haystack =
        `${props.product.categorySlug ?? ''} ${props.product.categoryName ?? ''} ${props.product.name}`.toLowerCase();

    if (
        haystack.includes('audio') ||
        haystack.includes('headphone') ||
        haystack.includes('dac') ||
        haystack.includes('speaker')
    ) {
        return Headphones;
    }
    if (
        haystack.includes('workspace') ||
        haystack.includes('desk') ||
        haystack.includes('keyboard') ||
        haystack.includes('monitor') ||
        haystack.includes('laptop')
    ) {
        return Monitor;
    }
    if (
        haystack.includes('carry') ||
        haystack.includes('sling') ||
        haystack.includes('backpack') ||
        haystack.includes('wallet') ||
        haystack.includes('pouch')
    ) {
        return Briefcase;
    }
    if (haystack.includes('home') || haystack.includes('living')) {
        return Home;
    }

    return Package;
});

function handleAddToCart(): void {
    if (!activeVariantInStock.value || isAdding.value) {
        return;
    }

    if (addTimer !== undefined) {
        window.clearTimeout(addTimer);
    }
    if (resetTimer !== undefined) {
        window.clearTimeout(resetTimer);
    }

    isAdding.value = true;
    emit('addToCart', {
        ...props.product,
        selectedColor: selectedColor.value,
        selectedSize: selectedSize.value,
    });

    addTimer = window.setTimeout(() => {
        isAdding.value = false;
        justAdded.value = true;
        statusAnnouncement.value = `${props.product.name} added to cart.`;

        resetTimer = window.setTimeout(() => {
            justAdded.value = false;
            statusAnnouncement.value = '';
        }, 1600);
    }, 150);
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

const isBuyingNow = ref(false);

function handleBuyNow(): void {
    if (!activeVariantInStock.value || isBuyingNow.value) {
        return;
    }

    if (props.linkable && typeof props.product.id === 'number') {
        isBuyingNow.value = true;
        router.post(
            storeCartItemRoute.url(),
            {
                product_id: props.product.id,
                quantity: 1,
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
        return;
    }

    handleAddToCart();
}
</script>

<template>
    <article
        class="group flex h-full flex-col overflow-hidden rounded-2xl border border-border/80 bg-card transition-all hover:border-primary/40 hover:shadow-sm"
    >
        <!-- Reserved aspect-square container prevents layout shift (CLS) -->
        <div
            class="relative aspect-square w-full overflow-hidden bg-linear-to-b from-secondary/40 to-secondary/80"
        >
            <component
                :is="linkable ? Link : 'div'"
                v-bind="linkable ? { href: show.url(product.slug) } : {}"
                :tabindex="linkable ? -1 : undefined"
                :aria-hidden="linkable ? 'true' : undefined"
                class="block size-full"
            >
                <img
                    v-if="activeImageUrl"
                    :src="activeImageUrl"
                    :alt="linkable ? '' : product.imageAlt"
                    loading="lazy"
                    decoding="async"
                    width="400"
                    height="400"
                    class="size-full object-cover transition-transform duration-300 motion-safe:group-hover:scale-105"
                />
                <div
                    v-else
                    class="flex size-full flex-col items-center justify-center gap-3 p-6 text-center"
                    aria-hidden="true"
                >
                    <div
                        class="flex size-14 items-center justify-center rounded-2xl border border-border/70 bg-background/90 text-foreground/80 shadow-2xs transition-transform duration-300 motion-safe:group-hover:scale-105 motion-safe:group-hover:border-primary/40 motion-safe:group-hover:text-primary"
                    >
                        <component :is="departmentIcon" class="size-6" />
                    </div>
                    <span
                        v-if="product.sku"
                        class="rounded-md bg-background/80 px-2 py-0.5 font-mono text-[10px] tracking-wider text-muted-foreground uppercase"
                    >
                        {{ product.sku }}
                    </span>
                </div>
            </component>

            <!-- Stock availability badge (server-authoritative) -->
            <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                <Badge
                    :variant="activeVariantInStock ? 'secondary' : 'outline'"
                    :class="[
                        'px-2.5 py-0.5 text-[11px] font-medium backdrop-blur-xs',
                        activeVariantInStock
                            ? 'bg-background/90 text-foreground'
                            : 'bg-background/90 text-muted-foreground',
                    ]"
                >
                    {{ activeStockBadgeLabel }}
                </Badge>
            </div>

            <!-- Wishlist Heart Button -->
            <button
                v-if="linkable && typeof product.id === 'number'"
                type="button"
                class="absolute top-3 right-3 inline-flex size-9 items-center justify-center rounded-full border border-border/70 bg-background/90 text-muted-foreground shadow-2xs backdrop-blur-xs transition-colors hover:border-primary/50 hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :aria-label="
                    product.isWishlisted
                        ? `Remove ${product.name} from wishlist`
                        : `Save ${product.name} to wishlist`
                "
                :aria-pressed="Boolean(product.isWishlisted)"
                @click="handleToggleWishlist"
            >
                <Heart
                    :class="[
                        'size-4 transition-transform',
                        product.isWishlisted &&
                            'scale-110 fill-primary text-primary',
                    ]"
                    aria-hidden="true"
                />
            </button>
        </div>

        <div class="flex flex-1 flex-col justify-between p-4">
            <div>
                <div
                    class="flex items-center justify-between gap-2 text-xs text-muted-foreground"
                >
                    <span v-if="product.categoryName" class="truncate">
                        {{ product.categoryName }}
                    </span>
                    <div class="flex shrink-0 items-center gap-2">
                        <span
                            v-if="product.soldCount && product.soldCount > 0"
                            class="text-[11px] font-medium text-muted-foreground"
                        >
                            {{ product.soldCount }} sold
                        </span>
                        <RatingDisplay
                            :rating-average="product.ratingAverage"
                            :rating-count="product.ratingCount"
                        />
                    </div>
                </div>

                <h3
                    class="mt-1.5 line-clamp-2 text-sm leading-snug font-semibold text-foreground"
                >
                    <Link
                        v-if="linkable"
                        :href="show.url(product.slug)"
                        class="rounded-sm hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {{ product.name }}
                    </Link>
                    <template v-else>{{ product.name }}</template>
                </h3>

                <p
                    v-if="product.sellerName"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    Sold by {{ product.sellerName }}
                </p>

                <!-- Color & Size quick selectors when product defines options -->
                <div
                    v-if="
                        (product.availableColors &&
                            product.availableColors.length > 0) ||
                        (product.availableSizes &&
                            product.availableSizes.length > 0)
                    "
                    class="mt-2.5 space-y-2"
                >
                    <div
                        v-if="
                            product.availableColors &&
                            product.availableColors.length > 0
                        "
                        class="space-y-1"
                    >
                        <p
                            class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Color:
                            <span class="text-foreground">{{
                                selectedColor
                            }}</span>
                        </p>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="color in product.availableColors"
                                :key="color"
                                type="button"
                                :class="[
                                    'rounded-md border px-2 py-0.5 text-[11px] font-medium transition-colors',
                                    selectedColor === color
                                        ? 'border-primary bg-brand-subtle/60 text-primary'
                                        : 'border-border bg-secondary/40 text-muted-foreground hover:border-primary/40 hover:text-foreground',
                                    !isOptionOptionInStock(
                                        product,
                                        'color',
                                        color,
                                    ) && 'line-through opacity-50',
                                ]"
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
                        class="space-y-1"
                    >
                        <p
                            class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Size:
                            <span class="text-foreground">{{
                                selectedSize
                            }}</span>
                        </p>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="size in product.availableSizes"
                                :key="size"
                                type="button"
                                :class="[
                                    'rounded-md border px-2 py-0.5 text-[11px] font-medium transition-colors',
                                    selectedSize === size
                                        ? 'border-primary bg-brand-subtle/60 text-primary'
                                        : 'border-border bg-secondary/40 text-muted-foreground hover:border-primary/40 hover:text-foreground',
                                    !isOptionOptionInStock(
                                        product,
                                        'size',
                                        size,
                                    ) && 'line-through opacity-50',
                                ]"
                                @click="selectedSize = size"
                            >
                                {{ size }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 space-y-3 border-t border-border/50 pt-3">
                <PriceDisplay
                    :price-formatted="product.priceFormatted"
                    :original-price-formatted="product.originalPriceFormatted"
                    :discount-label="product.discountLabel"
                />

                <div v-if="activeVariantInStock" class="grid grid-cols-2 gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-10 w-full gap-1.5 rounded-xl border-primary/40 px-2.5 text-xs font-semibold hover:border-primary hover:bg-brand-subtle/40"
                        :disabled="isAdding || isBuyingNow"
                        :aria-label="`Add ${product.name} to cart`"
                        @click="handleAddToCart"
                    >
                        <Check
                            v-if="justAdded"
                            class="size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        <ShoppingCart
                            v-else
                            class="size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="truncate">
                            {{ justAdded ? 'Added' : 'Add to Cart' }}
                        </span>
                    </Button>

                    <Button
                        type="button"
                        variant="default"
                        class="h-10 w-full gap-1.5 rounded-xl px-2.5 text-xs font-semibold"
                        :disabled="isBuyingNow || isAdding"
                        :aria-label="`Buy ${product.name} now`"
                        @click="handleBuyNow"
                    >
                        <ShoppingBag
                            class="size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="truncate">
                            {{ isBuyingNow ? 'Loading...' : 'Buy Now' }}
                        </span>
                    </Button>
                </div>

                <Button
                    v-else
                    type="button"
                    variant="secondary"
                    class="h-10 w-full gap-2 rounded-xl text-xs font-semibold"
                    disabled
                    :aria-label="`${product.name} is out of stock`"
                >
                    <ShoppingCart class="size-4" aria-hidden="true" />
                    <span>Out of Stock</span>
                </Button>

                <span class="sr-only" aria-live="polite">
                    {{ statusAnnouncement }}
                </span>
            </div>
        </div>
    </article>
</template>

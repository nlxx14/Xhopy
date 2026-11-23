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

const isAdding = ref(false);
const justAdded = ref(false);
const statusAnnouncement = ref('');

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
    if (!props.product.inStock || isAdding.value) {
        return;
    }

    if (addTimer !== undefined) {
        window.clearTimeout(addTimer);
    }
    if (resetTimer !== undefined) {
        window.clearTimeout(resetTimer);
    }

    isAdding.value = true;
    emit('addToCart', props.product);

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
    if (!props.product.inStock || isBuyingNow.value) {
        return;
    }

    if (props.linkable && typeof props.product.id === 'number') {
        isBuyingNow.value = true;
        router.post(
            storeCartItemRoute.url(),
            {
                product_id: props.product.id,
                quantity: 1,
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
                    v-if="product.imageUrl"
                    :src="product.imageUrl"
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
                    :variant="product.inStock ? 'secondary' : 'outline'"
                    :class="[
                        'px-2.5 py-0.5 text-[11px] font-medium backdrop-blur-xs',
                        product.inStock
                            ? 'bg-background/90 text-foreground'
                            : 'bg-background/90 text-muted-foreground',
                    ]"
                >
                    {{ product.stockLabel }}
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
                    <RatingDisplay
                        :rating-average="product.ratingAverage"
                        :rating-count="product.ratingCount"
                    />
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
            </div>

            <div class="mt-4 space-y-3 border-t border-border/50 pt-3">
                <PriceDisplay
                    :price-formatted="product.priceFormatted"
                    :original-price-formatted="product.originalPriceFormatted"
                    :discount-label="product.discountLabel"
                />

                <div v-if="product.inStock" class="grid grid-cols-2 gap-2">
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

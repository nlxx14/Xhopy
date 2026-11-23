<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    Eye,
    Layers,
    ShoppingBag,
    Sparkles,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CategoryCard from '@modules/storefront/components/CategoryCard.vue';
import DailyInterestModule from '@modules/storefront/components/DailyInterestModule.vue';
import EmptyState from '@shared/components/EmptyState.vue';
import { ProductCard, type ProductSummary } from '@modules/catalog';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@shared/components/ui/dialog';
import StorefrontLayout from '@modules/storefront/layouts/StorefrontLayout.vue';
import { home } from '@/routes';
import { index as cartIndex } from '@/routes/cart';
import { store as storeCartItem } from '@/routes/cart/items';
import { index as checkoutIndex } from '@/routes/checkout';
import { index as productsIndex, show as showProduct } from '@/routes/products';
import type { CartSummary } from '@modules/cart';
import type {
    CategorySummary,
    StorefrontHomeProps,
} from '@modules/storefront/types';

const props = withDefaults(defineProps<StorefrontHomeProps>(), {
    announcement: null,
    hero: null,
    categories: () => [],
    topProducts: () => [],
    dailyInterest: null,
    cartSummary: () => ({ itemCount: 0, subtotalFormatted: null }),
    searchQuery: '',
    selectedCategory: null,
    isDemoPreview: false,
});

const localCartCount = ref(props.cartSummary.itemCount);
const cartDialogOpen = ref(false);
const lastSelectedProduct = ref<ProductSummary | null>(null);

watch(
    () => props.cartSummary.itemCount,
    (nextCount) => {
        localCartCount.value = nextCount;
    },
);

const effectiveCart = computed<CartSummary>(() => ({
    itemCount: localCartCount.value,
    subtotalFormatted: props.cartSummary.subtotalFormatted,
}));

const filteredProducts = computed(() => {
    return props.topProducts;
});

const allCategoriesCard = computed<CategorySummary>(() => {
    const counts = props.categories
        .map((cat) => cat.productCount)
        .filter((count): count is number => typeof count === 'number');

    return {
        id: 'all-categories',
        name: 'All Categories',
        slug: '',
        description: 'Browse all published departments and products.',
        productCount:
            counts.length > 0
                ? counts.reduce((sum, count) => sum + count, 0)
                : undefined,
    };
});

const spotlightProduct = computed<ProductSummary | null>(() => {
    if (props.topProducts.length === 0) {
        return null;
    }

    return (
        props.topProducts.find((product) => product.inStock) ??
        props.topProducts[0] ??
        null
    );
});

function handleSearch(query: string): void {
    router.get(
        home.url({
            query: {
                q: query || undefined,
                category: props.selectedCategory || undefined,
                preview_demo: props.isDemoPreview ? '1' : undefined,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function handleSelectCategory(slug: string | null): void {
    router.get(
        home.url({
            query: {
                q: props.searchQuery || undefined,
                category: slug || undefined,
                preview_demo: props.isDemoPreview ? '1' : undefined,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function handleAddToCart(product: ProductSummary): void {
    localCartCount.value += 1;
    lastSelectedProduct.value = product;

    if (!props.isDemoPreview && typeof product.id === 'number') {
        router.post(
            storeCartItem.url(),
            { product_id: product.id, quantity: 1 },
            { preserveScroll: true, preserveState: true },
        );
    }
}

function clearFilters(): void {
    router.get(
        home.url({
            query: {
                preview_demo: props.isDemoPreview ? '1' : undefined,
            },
        }),
    );
}
</script>

<template>
    <Head title="XHopy Storefront — Quality Essentials" />

    <StorefrontLayout
        :announcement="announcement"
        :categories="categories"
        :cart="effectiveCart"
        :search-query="searchQuery"
        :selected-category="selectedCategory"
        @search="handleSearch"
        @select-category="handleSelectCategory"
        @open-cart="cartDialogOpen = true"
    >
        <div
            class="mx-auto max-w-7xl space-y-12 px-4 py-8 sm:px-6 sm:py-10 lg:px-8"
        >
            <!-- Explicit Non-Production Demo Preview Indicator (Rule #4: No fabricated facts) -->
            <Alert
                v-if="isDemoPreview"
                class="border-primary/40 bg-brand-subtle/60"
            >
                <Eye class="size-4 text-primary" aria-hidden="true" />
                <AlertTitle class="font-semibold text-foreground">
                    Development Demo Preview Active
                </AlertTitle>
                <AlertDescription
                    class="flex flex-col justify-between gap-3 text-xs text-muted-foreground sm:flex-row sm:items-center"
                >
                    <span>
                        Showing clearly labeled <strong>[DEMO]</strong> preview
                        records to verify the Batch 2 design system. These
                        records are never shown in production.
                    </span>
                    <Button
                        as="a"
                        :href="home.url()"
                        variant="outline"
                        size="sm"
                        class="h-8 shrink-0 text-xs"
                    >
                        Exit Demo Preview
                    </Button>
                </AlertDescription>
            </Alert>

            <!-- 1. Minimal 2-Column Split Hero Section -->
            <section
                v-if="hero"
                aria-labelledby="hero-heading"
                class="relative overflow-hidden rounded-3xl border border-border bg-gradient-to-br from-charcoal via-charcoal to-primary/85 p-6 text-charcoal-foreground shadow-xs sm:p-10 lg:p-12"
            >
                <div
                    class="relative z-10 grid grid-cols-1 items-center gap-8 lg:grid-cols-12"
                >
                    <div
                        :class="[
                            'space-y-5',
                            spotlightProduct
                                ? 'lg:col-span-7'
                                : 'max-w-2xl lg:col-span-12',
                        ]"
                    >
                        <div class="flex flex-wrap items-center gap-2.5">
                            <Badge
                                v-if="hero.badgeText"
                                class="bg-primary px-3 py-1 text-xs font-semibold text-primary-foreground"
                            >
                                {{ hero.badgeText }}
                            </Badge>
                            <span
                                v-if="hero.eyebrow"
                                class="text-xs font-medium tracking-wider text-charcoal-foreground/75 uppercase"
                            >
                                {{ hero.eyebrow }}
                            </span>
                        </div>

                        <h1
                            id="hero-heading"
                            class="text-3xl leading-tight font-extrabold tracking-tight sm:text-4xl lg:text-[2.75rem]"
                        >
                            {{ hero.title }}
                        </h1>

                        <p
                            class="max-w-xl text-sm leading-relaxed text-charcoal-foreground/80 sm:text-base"
                        >
                            {{ hero.subtitle }}
                        </p>

                        <div class="flex flex-wrap items-center gap-3 pt-1">
                            <Button
                                as="a"
                                :href="hero.primaryCtaHref"
                                size="lg"
                                class="h-11 gap-2 rounded-full px-6 text-sm font-semibold shadow-2xs"
                            >
                                <span>{{ hero.primaryCtaLabel }}</span>
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </Button>

                            <Button
                                v-if="
                                    hero.secondaryCtaLabel &&
                                    hero.secondaryCtaHref
                                "
                                as="a"
                                :href="hero.secondaryCtaHref"
                                variant="outline"
                                size="lg"
                                class="h-11 rounded-full border-white/20 bg-white/5 px-6 text-sm font-semibold text-white hover:bg-white/15 hover:text-white"
                            >
                                {{ hero.secondaryCtaLabel }}
                            </Button>
                        </div>
                    </div>

                    <!-- Right Column: Minimal Featured Spotlight Card (only rendered when real/demo products exist) -->
                    <div v-if="spotlightProduct" class="lg:col-span-5">
                        <div
                            class="flex flex-col justify-between rounded-2xl border border-white/15 bg-white/[0.06] p-5 backdrop-blur-xs sm:p-6"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="inline-flex items-center gap-1.5 text-[11px] font-semibold tracking-wider text-white/80 uppercase"
                                >
                                    <Sparkles
                                        class="size-3.5 text-primary"
                                        aria-hidden="true"
                                    />
                                    <span>Featured Spotlight</span>
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-0.5 text-[11px] font-medium text-white/90"
                                >
                                    <span
                                        :class="[
                                            'size-1.5 rounded-full',
                                            spotlightProduct.inStock
                                                ? 'bg-emerald-400'
                                                : 'bg-white/40',
                                        ]"
                                    />
                                    {{ spotlightProduct.stockLabel }}
                                </span>
                            </div>

                            <div class="mt-5 space-y-1.5">
                                <p
                                    v-if="spotlightProduct.categoryName"
                                    class="text-xs font-medium text-white/65"
                                >
                                    {{ spotlightProduct.categoryName }}
                                </p>
                                <p
                                    class="line-clamp-2 text-lg leading-snug font-bold tracking-tight text-white sm:text-xl"
                                >
                                    {{ spotlightProduct.name }}
                                </p>
                            </div>

                            <div
                                class="mt-6 flex flex-wrap items-end justify-between gap-3 border-t border-white/10 pt-4"
                            >
                                <div>
                                    <p
                                        class="text-[11px] tracking-wider text-white/60 uppercase"
                                    >
                                        Verified Price
                                    </p>
                                    <div
                                        class="mt-0.5 flex items-baseline gap-2"
                                    >
                                        <span
                                            class="text-xl font-extrabold tracking-tight text-white"
                                        >
                                            {{
                                                spotlightProduct.priceFormatted
                                            }}
                                        </span>
                                        <span
                                            v-if="
                                                spotlightProduct.originalPriceFormatted
                                            "
                                            class="text-xs text-white/50 line-through"
                                        >
                                            {{
                                                spotlightProduct.originalPriceFormatted
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <Link
                                    v-if="!isDemoPreview"
                                    :href="
                                        showProduct.url(spotlightProduct.slug)
                                    "
                                    class="inline-flex h-9 items-center gap-1.5 rounded-full bg-white px-4 text-xs font-semibold text-charcoal transition-opacity hover:opacity-90"
                                >
                                    <span>View Item</span>
                                    <ArrowUpRight
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </Link>
                                <a
                                    v-else
                                    href="#top-products-heading"
                                    class="inline-flex h-9 items-center gap-1.5 rounded-full bg-white px-4 text-xs font-semibold text-charcoal transition-opacity hover:opacity-90"
                                >
                                    <span>View in Catalog</span>
                                    <ArrowRight
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 2. Categories Section -->
            <section aria-labelledby="categories-heading" class="space-y-4">
                <div
                    class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wider text-primary uppercase"
                        >
                            Departments
                        </p>
                        <h2
                            id="categories-heading"
                            class="mt-0.5 text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                        >
                            Featured Categories
                        </h2>
                    </div>
                    <Button
                        v-if="selectedCategory"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-8 self-start text-xs font-semibold text-primary sm:self-auto"
                        @click="handleSelectCategory(null)"
                    >
                        Clear category filter
                    </Button>
                </div>

                <div
                    v-if="categories.length > 0"
                    class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5"
                >
                    <CategoryCard
                        :category="allCategoriesCard"
                        :active="!selectedCategory"
                        @select="handleSelectCategory(null)"
                    />
                    <CategoryCard
                        v-for="cat in categories"
                        :key="cat.id"
                        :category="cat"
                        :active="selectedCategory === cat.slug"
                        @select="handleSelectCategory"
                    />
                </div>

                <EmptyState
                    v-else
                    :icon="Layers"
                    title="No product categories published yet"
                    description="Browse categories will appear here as soon as items are published to the storefront."
                />
            </section>

            <!-- 3. Top Products Collection -->
            <section aria-labelledby="top-products-heading" class="space-y-5">
                <div
                    class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"
                >
                    <div>
                        <div
                            class="inline-flex items-center gap-1.5 text-xs font-semibold tracking-wider text-primary uppercase"
                        >
                            <Sparkles class="size-3.5" aria-hidden="true" />
                            <span>Verified Catalog</span>
                        </div>
                        <h2
                            id="top-products-heading"
                            class="mt-0.5 text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                        >
                            Top Products
                        </h2>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <p
                            v-if="searchQuery || selectedCategory"
                            class="text-sm text-muted-foreground"
                        >
                            Showing filtered results
                            <span v-if="searchQuery">
                                for
                                <strong class="text-foreground"
                                    >"{{ searchQuery }}"</strong
                                >
                            </span>
                            <span v-if="selectedCategory">
                                in
                                <strong class="text-foreground">{{
                                    selectedCategory
                                }}</strong>
                            </span>
                        </p>

                        <Link
                            :href="
                                productsIndex.url({
                                    query: {
                                        q: searchQuery || undefined,
                                        category: selectedCategory || undefined,
                                    },
                                })
                            "
                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                        >
                            <span>View full catalog</span>
                            <ArrowRight class="size-3.5" aria-hidden="true" />
                        </Link>
                    </div>
                </div>

                <div
                    v-if="filteredProducts.length > 0"
                    class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <ProductCard
                        v-for="product in filteredProducts"
                        :key="product.id"
                        :product="product"
                        :linkable="!isDemoPreview"
                        @add-to-cart="handleAddToCart"
                    />
                </div>

                <EmptyState
                    v-else
                    :icon="ShoppingBag"
                    :title="
                        searchQuery || selectedCategory
                            ? 'No matching products found'
                            : 'Catalog is ready for products'
                    "
                    :description="
                        searchQuery || selectedCategory
                            ? 'Try adjusting your search keywords or clearing the active category filter.'
                            : 'No products are currently published. Check back soon for new arrivals.'
                    "
                    :action-label="
                        searchQuery || selectedCategory ? 'Reset filters' : null
                    "
                    @action="clearFilters"
                />
            </section>

            <!-- 4. Daily Interest (Editorial Banner + Supporting Campaign Tiles) -->
            <DailyInterestModule
                v-if="dailyInterest"
                :daily-interest="dailyInterest"
            />
        </div>

        <!-- Cart Preview Dialog -->
        <Dialog v-model:open="cartDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Shopping Cart</DialogTitle>
                    <DialogDescription>
                        Review the items selected during this browsing session.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4">
                    <div
                        v-if="effectiveCart.itemCount > 0"
                        class="space-y-2 rounded-xl border border-border bg-secondary/40 p-4 text-sm"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-semibold text-foreground">
                                {{ effectiveCart.itemCount }}
                                {{
                                    effectiveCart.itemCount === 1
                                        ? 'item'
                                        : 'items'
                                }}
                                selected in current session
                            </p>
                            <span
                                v-if="effectiveCart.subtotalFormatted"
                                class="text-sm font-bold text-primary"
                            >
                                {{ effectiveCart.subtotalFormatted }}
                            </span>
                        </div>
                        <p
                            v-if="lastSelectedProduct"
                            class="text-xs text-muted-foreground"
                        >
                            Last added: {{ lastSelectedProduct.name }} ({{
                                lastSelectedProduct.priceFormatted
                            }})
                        </p>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        Your shopping cart is currently empty.
                    </p>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11"
                        @click="cartDialogOpen = false"
                    >
                        Continue Shopping
                    </Button>
                    <Button
                        v-if="!isDemoPreview"
                        as-child
                        variant="secondary"
                        class="h-11"
                    >
                        <Link
                            :href="cartIndex.url()"
                            @click="cartDialogOpen = false"
                        >
                            View Full Cart
                        </Link>
                    </Button>
                    <Button
                        v-if="!isDemoPreview && effectiveCart.itemCount > 0"
                        as-child
                        class="h-11"
                    >
                        <Link
                            :href="checkoutIndex.url()"
                            @click="cartDialogOpen = false"
                        >
                            Checkout
                        </Link>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </StorefrontLayout>
</template>

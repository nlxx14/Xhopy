<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    ImageOff,
    ShieldCheck,
    ShoppingBag,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import type {
    CartDetail,
    CartLineItem,
    CartSummary,
} from '@modules/cart/types';
import { QuantitySelector } from '@modules/catalog';
import {
    StorefrontLayout,
    type AnnouncementBanner,
    type CategorySummary,
} from '@modules/storefront';
import Breadcrumbs from '@shared/components/Breadcrumbs.vue';
import EmptyState from '@shared/components/EmptyState.vue';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import type { BreadcrumbItem } from '@shared/types';
import { home } from '@/routes';
import { clear as clearCartRoute, index as cartIndex } from '@/routes/cart';
import {
    destroy as destroyCartItemRoute,
    update as updateCartItemRoute,
} from '@/routes/cart/items';
import { index as checkoutIndex } from '@/routes/checkout';
import { index as productsIndex, show as productShow } from '@/routes/products';

withDefaults(
    defineProps<{
        announcement?: AnnouncementBanner | null;
        categories: CategorySummary[];
        cartSummary: CartSummary;
        cart: CartDetail;
    }>(),
    {
        announcement: null,
    },
);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Home', href: home.url() },
    { title: 'Shopping Cart', href: cartIndex.url() },
]);

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

function handleUpdateQuantity(item: CartLineItem, quantity: number): void {
    router.patch(
        updateCartItemRoute.url(item.id),
        { quantity },
        { preserveScroll: true },
    );
}

function handleRemoveItem(item: CartLineItem): void {
    router.delete(destroyCartItemRoute.url(item.id), {
        preserveScroll: true,
    });
}

function handleClearCart(): void {
    router.delete(clearCartRoute.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Shopping Cart — XHopy" />

    <StorefrontLayout
        :announcement="announcement"
        :categories="categories"
        :cart="cartSummary"
        @search="handleSearch"
        @select-category="handleSelectCategory"
        @open-cart="() => {}"
    >
        <div
            class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 sm:py-10 lg:px-8"
        >
            <div class="space-y-3 border-b border-border pb-6">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />

                <div
                    class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wider text-primary uppercase"
                        >
                            Server-Verified Cart
                        </p>
                        <h1
                            class="mt-1 text-2xl font-extrabold tracking-tight text-foreground sm:text-3xl"
                        >
                            Your Shopping Cart
                        </h1>
                    </div>

                    <Button
                        v-if="cart.items.length > 0"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-9 gap-1.5 self-start text-xs text-destructive hover:text-destructive sm:self-auto"
                        @click="handleClearCart"
                    >
                        <Trash2 class="size-3.5" aria-hidden="true" />
                        <span>Clear cart</span>
                    </Button>
                </div>
            </div>

            <Alert
                v-if="cart.hasUnavailableItems"
                class="border-destructive/40 bg-destructive/10"
            >
                <AlertTriangle
                    class="size-4 text-destructive"
                    aria-hidden="true"
                />
                <AlertTitle class="font-semibold text-foreground">
                    Some items in your cart require attention
                </AlertTitle>
                <AlertDescription class="text-xs text-muted-foreground">
                    One or more items have limited stock or are no longer
                    available. Please adjust quantities or remove unavailable
                    items before proceeding to checkout.
                </AlertDescription>
            </Alert>

            <div
                v-if="cart.items.length > 0"
                class="grid grid-cols-1 gap-8 lg:grid-cols-12"
            >
                <!-- Cart Items List -->
                <div class="space-y-4 lg:col-span-8">
                    <article
                        v-for="item in cart.items"
                        :key="item.id"
                        class="flex flex-col justify-between gap-4 rounded-2xl border border-border bg-card p-4 shadow-2xs sm:flex-row sm:items-center sm:p-5"
                    >
                        <div class="flex items-start gap-4">
                            <Link
                                :href="productShow.url(item.slug)"
                                class="relative size-20 shrink-0 overflow-hidden rounded-xl border border-border bg-secondary/70 sm:size-24"
                            >
                                <img
                                    v-if="item.imageUrl"
                                    :src="item.imageUrl"
                                    :alt="item.imageAlt"
                                    width="96"
                                    height="96"
                                    loading="lazy"
                                    class="size-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex size-full items-center justify-center text-muted-foreground"
                                >
                                    <ImageOff
                                        class="size-6 opacity-60"
                                        aria-hidden="true"
                                    />
                                </div>
                            </Link>

                            <div class="space-y-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        {{ item.categoryName }}
                                    </span>
                                    <Badge
                                        :variant="
                                            item.isAvailable
                                                ? 'secondary'
                                                : 'outline'
                                        "
                                        :class="[
                                            'text-[11px]',
                                            !item.isAvailable &&
                                                'border-destructive/50 text-destructive',
                                        ]"
                                    >
                                        {{ item.availabilityLabel }}
                                    </Badge>
                                </div>

                                <h2 class="text-base font-bold text-foreground">
                                    <Link
                                        :href="productShow.url(item.slug)"
                                        class="hover:text-primary"
                                    >
                                        {{ item.name }}
                                    </Link>
                                </h2>

                                <p class="text-xs text-muted-foreground">
                                    SKU: {{ item.sku }} · Unit price:
                                    <strong class="text-foreground">{{
                                        item.unitPriceFormatted
                                    }}</strong>
                                </p>

                                <div
                                    v-if="
                                        item.selectedColor || item.selectedSize
                                    "
                                    class="flex flex-wrap gap-1.5 pt-0.5"
                                >
                                    <span
                                        v-if="item.selectedColor"
                                        class="rounded-md border border-border bg-secondary/60 px-2 py-0.5 text-[11px] font-medium text-foreground"
                                    >
                                        Color: {{ item.selectedColor }}
                                    </span>
                                    <span
                                        v-if="item.selectedSize"
                                        class="rounded-md border border-border bg-secondary/60 px-2 py-0.5 text-[11px] font-medium text-foreground"
                                    >
                                        Size: {{ item.selectedSize }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex flex-wrap items-center justify-between gap-4 border-t border-border/60 pt-3 sm:flex-col sm:items-end sm:border-t-0 sm:pt-0"
                        >
                            <p
                                class="text-base font-bold tracking-tight text-foreground"
                            >
                                {{ item.lineTotalFormatted }}
                            </p>

                            <div class="flex items-center gap-2">
                                <QuantitySelector
                                    :model-value="item.quantity"
                                    :min="1"
                                    :max="item.maxQuantity"
                                    :disabled="!item.isAvailable"
                                    @update:model-value="
                                        handleUpdateQuantity(item, $event)
                                    "
                                />

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-11 text-muted-foreground hover:text-destructive"
                                    :aria-label="`Remove ${item.name} from cart`"
                                    @click="handleRemoveItem(item)"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                </Button>
                            </div>
                        </div>
                    </article>
                </div>

                <!-- Order Summary Column -->
                <aside class="lg:col-span-4">
                    <div
                        class="sticky top-28 space-y-5 rounded-2xl border border-border bg-card p-6 shadow-2xs"
                    >
                        <h2
                            class="text-lg font-bold tracking-tight text-foreground"
                        >
                            Order Summary
                        </h2>

                        <dl class="space-y-3 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-muted-foreground">
                                    Items ({{ cart.itemCount }})
                                </dt>
                                <dd class="font-semibold text-foreground">
                                    {{ cart.subtotalFormatted }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-muted-foreground">Shipping</dt>
                                <dd class="text-xs text-muted-foreground">
                                    Calculated at checkout
                                </dd>
                            </div>
                            <div
                                class="flex items-center justify-between border-t border-border pt-3 text-base font-bold text-foreground"
                            >
                                <dt>Estimated Total</dt>
                                <dd>{{ cart.subtotalFormatted }}</dd>
                            </div>
                        </dl>

                        <Button
                            as-child
                            size="lg"
                            class="h-12 w-full gap-2 rounded-xl text-sm font-semibold"
                            :disabled="cart.hasUnavailableItems"
                        >
                            <Link :href="checkoutIndex.url()">
                                <span>Proceed to Checkout</span>
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </Link>
                        </Button>

                        <Button
                            as-child
                            variant="outline"
                            class="h-11 w-full rounded-xl text-xs font-semibold"
                        >
                            <Link :href="productsIndex.url()">
                                Continue Shopping
                            </Link>
                        </Button>

                        <div
                            class="flex items-center gap-2 rounded-xl bg-secondary/50 p-3 text-xs text-muted-foreground"
                        >
                            <ShieldCheck
                                class="size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span>
                                All prices and inventory quantities are verified
                                on the server before payment.
                            </span>
                        </div>
                    </div>
                </aside>
            </div>

            <EmptyState
                v-else
                :icon="ShoppingBag"
                title="Your shopping cart is empty"
                description="Browse our catalog to add verified products to your cart."
                action-label="Browse Products"
                @action="router.get(productsIndex.url())"
            />
        </div>
    </StorefrontLayout>
</template>

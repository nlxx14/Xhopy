<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    CheckCircle2,
    Heart,
    ImageOff,
    MapPin,
    Package,
    RotateCcw,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Store,
    Trash2,
    Truck,
} from '@lucide/vue';
import { computed } from 'vue';
import type { CustomerAddress, OrderSummary } from '@modules/account/types';
import type { ProductSummary } from '@modules/catalog';
import EmptyState from '@shared/components/EmptyState.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { dashboard } from '@/routes';
import { index as addressesIndex } from '@/routes/addresses';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as cartIndex } from '@/routes/cart';
import { store as storeCartItemRoute } from '@/routes/cart/items';
import { index as checkoutIndex } from '@/routes/checkout';
import { index as ordersIndex, show as orderShow } from '@/routes/orders';
import { index as productsIndex, show as productShow } from '@/routes/products';
import { destroy as destroyWishlistRoute } from '@/routes/wishlist';

export interface ActiveOrderTracker extends OrderSummary {
    currentStepIndex: number;
    steps: Array<{
        key: string;
        label: string;
    }>;
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const props = withDefaults(
    defineProps<{
        orderStats?: {
            totalOrders: number;
            activeOrders: number;
        };
        cartOverview?: {
            itemCount: number;
            subtotalFormatted: string;
        };
        addressOverview?: {
            savedCount: number;
            defaultShipping: CustomerAddress | null;
        };
        recentOrders?: OrderSummary[];
        activeOrderTracker?: ActiveOrderTracker | null;
        wishlistItems?: ProductSummary[];
        buyAgainItems?: ProductSummary[];
        accountSecurity?: {
            emailVerified: boolean;
            twoFactorEnabled: boolean;
            role: string;
            roleLabel: string;
            canAccessAdmin: boolean;
        };
    }>(),
    {
        orderStats: () => ({ totalOrders: 0, activeOrders: 0 }),
        cartOverview: () => ({ itemCount: 0, subtotalFormatted: '₱0.00' }),
        addressOverview: () => ({ savedCount: 0, defaultShipping: null }),
        recentOrders: () => [],
        activeOrderTracker: null,
        wishlistItems: () => [],
        buyAgainItems: () => [],
        accountSecurity: () => ({
            emailVerified: true,
            twoFactorEnabled: false,
            role: 'customer',
            roleLabel: 'Customer',
            canAccessAdmin: false,
        }),
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);

function handleAddToCart(product: ProductSummary): void {
    if (typeof product.id !== 'number') {
        return;
    }

    router.post(
        storeCartItemRoute.url(),
        { product_id: product.id, quantity: 1 },
        { preserveScroll: true },
    );
}

function handleRemoveFromWishlist(product: ProductSummary): void {
    if (typeof product.id !== 'number') {
        return;
    }

    router.delete(destroyWishlistRoute.url(product.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Account Overview — XHopy" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
        <!-- 1. Welcome & Account Status Banner -->
        <section
            aria-labelledby="dashboard-welcome-heading"
            class="relative overflow-hidden rounded-2xl border border-border bg-gradient-to-br from-charcoal via-charcoal to-primary/85 p-6 text-charcoal-foreground shadow-2xs sm:p-8"
        >
            <div
                class="flex flex-col justify-between gap-6 lg:flex-row lg:items-center"
            >
                <div class="space-y-2.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            class="bg-primary text-xs font-semibold text-primary-foreground"
                        >
                            {{ props.accountSecurity.roleLabel }}
                        </Badge>
                        <Badge
                            v-if="props.accountSecurity.emailVerified"
                            variant="outline"
                            class="gap-1 border-white/25 bg-white/10 text-xs text-white"
                        >
                            <CheckCircle2
                                class="size-3.5 text-green-400"
                                aria-hidden="true"
                            />
                            <span>Verified Email</span>
                        </Badge>
                        <Badge
                            v-if="props.accountSecurity.twoFactorEnabled"
                            variant="outline"
                            class="gap-1 border-white/25 bg-white/10 text-xs text-white"
                        >
                            <ShieldCheck
                                class="size-3.5 text-green-400"
                                aria-hidden="true"
                            />
                            <span>2FA Active</span>
                        </Badge>
                    </div>

                    <h1
                        id="dashboard-welcome-heading"
                        class="text-2xl font-extrabold tracking-tight sm:text-3xl"
                    >
                        Welcome back, {{ user.name }}
                    </h1>
                    <p class="max-w-xl text-sm text-charcoal-foreground/80">
                        Track your active deliveries, manage your saved
                        wishlist, and reorder your favorite essentials.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <Button
                        v-if="props.accountSecurity.canAccessAdmin"
                        as-child
                        variant="outline"
                        class="h-11 gap-2 rounded-xl border-white/25 bg-white/10 text-xs font-semibold text-white hover:bg-white/20 hover:text-white"
                    >
                        <Link :href="adminDashboard.url()">
                            <ShieldCheck class="size-4" aria-hidden="true" />
                            <span>Open Admin Back-Office</span>
                        </Link>
                    </Button>

                    <Button
                        as-child
                        class="h-11 gap-2 rounded-xl px-5 text-xs font-semibold"
                    >
                        <Link :href="productsIndex.url()">
                            <Store class="size-4" aria-hidden="true" />
                            <span>Browse Catalog</span>
                        </Link>
                    </Button>
                </div>
            </div>
        </section>

        <!-- 2. Top 3 Summary Cards (Orders, Cart, Default Shipping Address) -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <!-- Card A: Orders Overview -->
            <article
                class="flex flex-col justify-between rounded-2xl border border-border bg-card p-5 shadow-2xs"
            >
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Orders Overview
                        </span>
                        <div
                            class="flex size-9 items-center justify-center rounded-xl bg-brand-subtle text-primary"
                        >
                            <Package class="size-4" aria-hidden="true" />
                        </div>
                    </div>

                    <div class="flex items-baseline gap-3">
                        <span
                            class="text-3xl font-extrabold tracking-tight text-foreground"
                        >
                            {{ props.orderStats.totalOrders }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            total
                            {{
                                props.orderStats.totalOrders === 1
                                    ? 'order'
                                    : 'orders'
                            }}
                        </span>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        <strong class="font-semibold text-foreground">{{
                            props.orderStats.activeOrders
                        }}</strong>
                        currently active or in transit.
                    </p>
                </div>

                <div class="mt-5 border-t border-border/70 pt-3">
                    <Link
                        :href="ordersIndex.url()"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                    >
                        <span>View order history</span>
                        <ArrowRight class="size-3.5" aria-hidden="true" />
                    </Link>
                </div>
            </article>

            <!-- Card B: Active Shopping Cart -->
            <article
                class="flex flex-col justify-between rounded-2xl border border-border bg-card p-5 shadow-2xs"
            >
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Shopping Cart
                        </span>
                        <div
                            class="flex size-9 items-center justify-center rounded-xl bg-brand-subtle text-primary"
                        >
                            <ShoppingBag class="size-4" aria-hidden="true" />
                        </div>
                    </div>

                    <div class="flex items-baseline gap-3">
                        <span
                            class="text-3xl font-extrabold tracking-tight text-foreground"
                        >
                            {{ props.cartOverview.subtotalFormatted }}
                        </span>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        <strong class="font-semibold text-foreground">{{
                            props.cartOverview.itemCount
                        }}</strong>
                        {{
                            props.cartOverview.itemCount === 1
                                ? 'item'
                                : 'items'
                        }}
                        ready in your cart.
                    </p>
                </div>

                <div
                    class="mt-5 flex items-center justify-between border-t border-border/70 pt-3"
                >
                    <Link
                        :href="cartIndex.url()"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                    >
                        <span>Open cart</span>
                        <ArrowRight class="size-3.5" aria-hidden="true" />
                    </Link>

                    <Link
                        v-if="props.cartOverview.itemCount > 0"
                        :href="checkoutIndex.url()"
                        class="text-xs font-semibold text-foreground hover:text-primary hover:underline"
                    >
                        Checkout now →
                    </Link>
                </div>
            </article>

            <!-- Card C: Default Shipping Address -->
            <article
                class="flex flex-col justify-between rounded-2xl border border-border bg-card p-5 shadow-2xs"
            >
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Default Shipping
                        </span>
                        <div
                            class="flex size-9 items-center justify-center rounded-xl bg-brand-subtle text-primary"
                        >
                            <MapPin class="size-4" aria-hidden="true" />
                        </div>
                    </div>

                    <template v-if="props.addressOverview.defaultShipping">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-bold text-foreground">
                                    {{
                                        props.addressOverview.defaultShipping
                                            .recipientName
                                    }}
                                </p>
                                <Badge variant="secondary" class="text-[11px]">
                                    {{
                                        props.addressOverview.defaultShipping
                                            .label
                                    }}
                                </Badge>
                            </div>
                            <p
                                class="mt-1 line-clamp-2 text-xs text-muted-foreground"
                            >
                                {{
                                    props.addressOverview.defaultShipping
                                        .formattedSummary
                                }}
                            </p>
                        </div>
                    </template>
                    <template v-else>
                        <p class="text-sm font-semibold text-foreground">
                            No address saved yet
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Add a default delivery address for faster checkout.
                        </p>
                    </template>
                </div>

                <div class="mt-5 border-t border-border/70 pt-3">
                    <Link
                        :href="addressesIndex.url()"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                    >
                        <span>
                            Manage addresses ({{
                                props.addressOverview.savedCount
                            }})
                        </span>
                        <ArrowRight class="size-3.5" aria-hidden="true" />
                    </Link>
                </div>
            </article>
        </div>

        <!-- 3. Visual Active Order Progress Tracker (when an order exists) -->
        <section
            v-if="props.activeOrderTracker"
            aria-labelledby="dashboard-order-tracker-heading"
            class="space-y-5 rounded-2xl border border-border bg-card p-6 shadow-2xs"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border/70 pb-4"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-brand-subtle text-primary"
                    >
                        <Truck class="size-4" aria-hidden="true" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2
                                id="dashboard-order-tracker-heading"
                                class="text-base font-bold text-foreground"
                            >
                                Live Order Tracker ·
                                {{ props.activeOrderTracker.orderNumber }}
                            </h2>
                            <Badge class="text-[11px]">
                                {{ props.activeOrderTracker.statusLabel }}
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Placed
                            {{ props.activeOrderTracker.placedAtFormatted }} ·
                            Total {{ props.activeOrderTracker.totalFormatted }}
                        </p>
                    </div>
                </div>

                <Button
                    as-child
                    variant="outline"
                    size="sm"
                    class="h-9 rounded-xl text-xs font-semibold"
                >
                    <Link
                        :href="
                            orderShow.url(props.activeOrderTracker.orderNumber)
                        "
                    >
                        Track Full Details
                    </Link>
                </Button>
            </div>

            <ol
                class="grid grid-cols-1 gap-3 sm:grid-cols-5"
                aria-label="Order fulfillment progress"
            >
                <li
                    v-for="(step, idx) in props.activeOrderTracker.steps"
                    :key="step.key"
                    :class="[
                        'flex items-center gap-2.5 rounded-xl border px-3.5 py-2.5 text-xs transition-colors',
                        idx < props.activeOrderTracker.currentStepIndex
                            ? 'border-primary/40 bg-brand-subtle/40 font-semibold text-foreground'
                            : idx === props.activeOrderTracker.currentStepIndex
                              ? 'border-primary bg-primary font-bold text-primary-foreground shadow-2xs'
                              : 'border-border/70 bg-secondary/20 text-muted-foreground',
                    ]"
                >
                    <span
                        :class="[
                            'flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                            idx < props.activeOrderTracker.currentStepIndex
                                ? 'bg-primary text-primary-foreground'
                                : idx ===
                                    props.activeOrderTracker.currentStepIndex
                                  ? 'bg-white text-primary'
                                  : 'bg-secondary text-muted-foreground',
                        ]"
                    >
                        <Check
                            v-if="
                                idx < props.activeOrderTracker.currentStepIndex
                            "
                            class="size-3"
                            aria-hidden="true"
                        />
                        <template v-else>{{ idx + 1 }}</template>
                    </span>
                    <span class="truncate">{{ step.label }}</span>
                </li>
            </ol>
        </section>

        <!-- 4. Bottom Grid: Recent Orders (Left) + Saved Wishlist & Buy Again (Right) -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- Left Column (7 cols): Recent Orders Feed -->
            <section
                aria-labelledby="dashboard-recent-orders-heading"
                class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-2xs lg:col-span-7"
            >
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h2
                            id="dashboard-recent-orders-heading"
                            class="text-base font-bold text-foreground"
                        >
                            Recent Orders
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Your latest purchases and real-time fulfillment
                            updates
                        </p>
                    </div>

                    <Link
                        v-if="props.recentOrders.length > 0"
                        :href="ordersIndex.url()"
                        class="text-xs font-semibold text-primary hover:underline"
                    >
                        View all
                    </Link>
                </div>

                <ul
                    v-if="props.recentOrders.length > 0"
                    class="divide-y divide-border/70"
                >
                    <li
                        v-for="order in props.recentOrders"
                        :key="order.id"
                        class="flex flex-col justify-between gap-3 py-3.5 sm:flex-row sm:items-center"
                    >
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="orderShow.url(order.orderNumber)"
                                    class="text-sm font-bold text-foreground hover:text-primary"
                                >
                                    {{ order.orderNumber }}
                                </Link>
                                <Badge
                                    :variant="
                                        order.status === 'cancelled'
                                            ? 'outline'
                                            : 'default'
                                    "
                                    class="text-[11px]"
                                >
                                    {{ order.statusLabel }}
                                </Badge>
                                <Badge variant="secondary" class="text-[11px]">
                                    {{ order.paymentStatusLabel }}
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Placed {{ order.placedAtFormatted }} ·
                                {{ order.itemCount }}
                                {{ order.itemCount === 1 ? 'item' : 'items' }}
                            </p>
                        </div>

                        <div
                            class="flex items-center justify-between gap-3 sm:justify-end"
                        >
                            <span class="text-sm font-bold text-foreground">
                                {{ order.totalFormatted }}
                            </span>
                            <Button
                                as-child
                                variant="outline"
                                size="sm"
                                class="h-8 rounded-lg text-xs"
                            >
                                <Link :href="orderShow.url(order.orderNumber)">
                                    Details
                                </Link>
                            </Button>
                        </div>
                    </li>
                </ul>

                <EmptyState
                    v-else
                    :icon="Package"
                    title="No orders placed yet"
                    description="Explore our catalog to add verified essentials to your cart."
                >
                    <template #actions>
                        <Button
                            as-child
                            size="sm"
                            class="h-10 gap-1.5 rounded-xl text-xs"
                        >
                            <Link :href="productsIndex.url()">
                                <ShoppingBag
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                <span>Start Shopping</span>
                            </Link>
                        </Button>
                    </template>
                </EmptyState>
            </section>

            <!-- Right Column (5 cols): Saved Wishlist + Buy Again -->
            <div class="space-y-6 lg:col-span-5">
                <!-- Saved Wishlist Panel -->
                <section
                    aria-labelledby="dashboard-wishlist-heading"
                    class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-2xs"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <Heart
                                class="size-4 fill-primary text-primary"
                                aria-hidden="true"
                            />
                            <h2
                                id="dashboard-wishlist-heading"
                                class="text-base font-bold text-foreground"
                            >
                                Saved Wishlist ({{
                                    props.wishlistItems.length
                                }})
                            </h2>
                        </div>

                        <Link
                            :href="productsIndex.url()"
                            class="text-xs font-semibold text-primary hover:underline"
                        >
                            Explore more
                        </Link>
                    </div>

                    <ul
                        v-if="props.wishlistItems.length > 0"
                        class="divide-y divide-border/70"
                    >
                        <li
                            v-for="item in props.wishlistItems"
                            :key="item.id"
                            class="flex items-center justify-between gap-3 py-3"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <Link
                                    :href="productShow.url(item.slug)"
                                    class="relative size-12 shrink-0 overflow-hidden rounded-xl border border-border bg-secondary/70"
                                >
                                    <img
                                        v-if="item.imageUrl"
                                        :src="item.imageUrl"
                                        :alt="item.imageAlt"
                                        width="48"
                                        height="48"
                                        loading="lazy"
                                        class="size-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex size-full items-center justify-center text-muted-foreground"
                                    >
                                        <ImageOff
                                            class="size-4 opacity-60"
                                            aria-hidden="true"
                                        />
                                    </div>
                                </Link>

                                <div class="min-w-0">
                                    <Link
                                        :href="productShow.url(item.slug)"
                                        class="block truncate text-sm font-semibold text-foreground hover:text-primary"
                                    >
                                        {{ item.name }}
                                    </Link>
                                    <div
                                        class="flex items-center gap-2 text-xs"
                                    >
                                        <span class="font-bold text-foreground">
                                            {{ item.priceFormatted }}
                                        </span>
                                        <span
                                            :class="
                                                item.inStock
                                                    ? 'text-muted-foreground'
                                                    : 'text-destructive'
                                            "
                                        >
                                            · {{ item.stockLabel }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    class="h-8 gap-1 rounded-lg text-xs"
                                    :disabled="!item.inStock"
                                    :aria-label="`Add ${item.name} to cart`"
                                    @click="handleAddToCart(item)"
                                >
                                    <ShoppingCart
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span>Add</span>
                                </Button>

                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    class="size-8 text-muted-foreground hover:text-destructive"
                                    :aria-label="`Remove ${item.name} from wishlist`"
                                    @click="handleRemoveFromWishlist(item)"
                                >
                                    <Trash2
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </div>
                        </li>
                    </ul>

                    <div
                        v-else
                        class="rounded-xl border border-dashed border-border bg-secondary/20 px-4 py-6 text-center"
                    >
                        <p class="text-xs font-semibold text-foreground">
                            Your wishlist is empty
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Tap the heart icon on any product in the catalog to
                            save it here for quick access.
                        </p>
                    </div>
                </section>

                <!-- Buy Again — Quick Reorder Panel -->
                <section
                    aria-labelledby="dashboard-buy-again-heading"
                    class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-2xs"
                >
                    <div class="flex items-center gap-2">
                        <RotateCcw
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        <div>
                            <h2
                                id="dashboard-buy-again-heading"
                                class="text-base font-bold text-foreground"
                            >
                                Buy Again
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                One-click reorder from your previous purchases
                            </p>
                        </div>
                    </div>

                    <ul
                        v-if="props.buyAgainItems.length > 0"
                        class="divide-y divide-border/70"
                    >
                        <li
                            v-for="item in props.buyAgainItems"
                            :key="item.id"
                            class="flex items-center justify-between gap-3 py-3"
                        >
                            <div class="min-w-0">
                                <Link
                                    :href="productShow.url(item.slug)"
                                    class="block truncate text-sm font-semibold text-foreground hover:text-primary"
                                >
                                    {{ item.name }}
                                </Link>
                                <p class="text-xs text-muted-foreground">
                                    {{ item.priceFormatted }} ·
                                    {{ item.stockLabel }}
                                </p>
                            </div>

                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                class="h-8 shrink-0 gap-1.5 rounded-lg text-xs font-semibold"
                                :disabled="!item.inStock"
                                @click="handleAddToCart(item)"
                            >
                                <ShoppingCart
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                <span>Buy Again</span>
                            </Button>
                        </li>
                    </ul>

                    <p
                        v-else
                        class="rounded-xl border border-dashed border-border bg-secondary/20 px-4 py-5 text-center text-xs text-muted-foreground"
                    >
                        Items from your completed orders will appear here for
                        instant reordering.
                    </p>
                </section>
            </div>
        </div>
    </div>
</template>

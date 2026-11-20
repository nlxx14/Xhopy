<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Package, ShoppingBag } from '@lucide/vue';
import type { OrderSummary } from '@modules/account/types';
import EmptyState from '@shared/components/EmptyState.vue';
import Heading from '@shared/components/Heading.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { index as ordersIndex, show as orderShow } from '@/routes/orders';
import { index as productsIndex } from '@/routes/products';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Orders',
                href: ordersIndex(),
            },
        ],
    },
});

defineProps<{
    orders: OrderSummary[];
}>();
</script>

<template>
    <Head title="My Orders — XHopy" />

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-6">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <Heading
                title="My Orders"
                description="Track your order status, payment verification, and purchase history"
            />

            <Button as-child variant="outline" class="h-10 rounded-xl text-xs">
                <Link :href="productsIndex.url()">Continue Shopping</Link>
            </Button>
        </div>

        <div v-if="orders.length > 0" class="space-y-4">
            <article
                v-for="order in orders"
                :key="order.id"
                class="flex flex-col justify-between gap-4 rounded-2xl border border-border bg-card p-5 shadow-2xs sm:flex-row sm:items-center"
            >
                <div class="space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-base font-bold text-foreground">
                            {{ order.orderNumber }}
                        </span>
                        <Badge
                            :variant="
                                order.status === 'cancelled'
                                    ? 'outline'
                                    : 'default'
                            "
                            class="text-xs"
                        >
                            {{ order.statusLabel }}
                        </Badge>
                        <Badge variant="secondary" class="text-xs">
                            Payment: {{ order.paymentStatusLabel }}
                        </Badge>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Placed {{ order.placedAtFormatted }} ({{
                            order.placedAtDiff
                        }}) · {{ order.itemCount }}
                        {{ order.itemCount === 1 ? 'item' : 'items' }}
                    </p>
                </div>

                <div
                    class="flex items-center justify-between gap-4 sm:justify-end"
                >
                    <div class="text-left sm:text-right">
                        <p class="text-xs text-muted-foreground">Total</p>
                        <p class="text-base font-bold text-foreground">
                            {{ order.totalFormatted }}
                        </p>
                    </div>

                    <Button
                        as-child
                        variant="outline"
                        class="h-10 gap-1.5 rounded-xl text-xs font-semibold"
                    >
                        <Link :href="orderShow.url(order.orderNumber)">
                            <span>View Details</span>
                            <ArrowRight class="size-3.5" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>
            </article>
        </div>

        <EmptyState
            v-else
            :icon="Package"
            title="You have not placed any orders yet"
            description="When you place an order, its status history and item snapshots will appear here."
            action-label="Browse Catalog"
            @action="router.get(productsIndex.url())"
        >
            <template #actions>
                <Button as-child class="h-11 gap-2 rounded-xl">
                    <Link :href="productsIndex.url()">
                        <ShoppingBag class="size-4" aria-hidden="true" />
                        <span>Browse Products</span>
                    </Link>
                </Button>
            </template>
        </EmptyState>
    </div>
</template>

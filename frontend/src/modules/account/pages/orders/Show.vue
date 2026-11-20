<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Clock,
    CreditCard,
    MapPin,
    ShieldCheck,
    XCircle,
} from '@lucide/vue';
import type { OrderDetail } from '@modules/account/types';
import Heading from '@shared/components/Heading.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    cancel as cancelOrderRoute,
    index as ordersIndex,
} from '@/routes/orders';

const props = defineProps<{
    order: OrderDetail;
}>();

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

function handleCancelOrder(): void {
    router.post(
        cancelOrderRoute.url(props.order.orderNumber),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="`Order ${order.orderNumber} — XHopy`" />

    <div class="mx-auto max-w-5xl space-y-8 px-4 py-6">
        <div
            class="flex flex-wrap items-center justify-between gap-4 border-b border-border pb-6"
        >
            <div class="space-y-1">
                <Link
                    :href="ordersIndex.url()"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                >
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    <span>Back to My Orders</span>
                </Link>
                <Heading
                    :title="`Order ${order.orderNumber}`"
                    :description="`Placed on ${order.placedAtFormatted} (${order.placedAtDiff})`"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Badge
                    :variant="
                        order.status === 'cancelled' ? 'outline' : 'default'
                    "
                    class="px-3 py-1 text-xs font-semibold"
                >
                    {{ order.statusLabel }}
                </Badge>
                <Badge variant="secondary" class="px-3 py-1 text-xs">
                    Payment: {{ order.paymentStatusLabel }}
                </Badge>

                <Button
                    v-if="order.canCancel"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-9 gap-1.5 rounded-xl text-xs text-destructive hover:text-destructive"
                    data-test="cancel-order-button"
                    @click="handleCancelOrder"
                >
                    <XCircle class="size-3.5" aria-hidden="true" />
                    <span>Cancel Order</span>
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- Left Column: Line Item Snapshots & Status Timeline -->
            <div class="space-y-6 lg:col-span-8">
                <section
                    aria-labelledby="order-items-heading"
                    class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-2xs"
                >
                    <h2
                        id="order-items-heading"
                        class="text-base font-bold text-foreground"
                    >
                        Purchased Items ({{ order.itemCount }})
                    </h2>

                    <ul class="divide-y divide-border/70">
                        <li
                            v-for="item in order.items"
                            :key="item.id"
                            class="flex items-center justify-between gap-4 py-3.5 text-sm"
                        >
                            <div class="space-y-0.5">
                                <p class="font-semibold text-foreground">
                                    {{ item.productName }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    SKU: {{ item.sku }} · Qty
                                    {{ item.quantity }} ×
                                    {{ item.unitPriceFormatted }}
                                </p>
                            </div>
                            <span class="font-bold text-foreground">
                                {{ item.lineTotalFormatted }}
                            </span>
                        </li>
                    </ul>

                    <dl class="space-y-2 border-t border-border pt-4 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-muted-foreground">Subtotal</dt>
                            <dd class="font-semibold text-foreground">
                                {{ order.subtotalFormatted }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted-foreground">Discount</dt>
                            <dd class="font-semibold text-foreground">
                                {{ order.discountFormatted }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted-foreground">Shipping</dt>
                            <dd class="font-semibold text-foreground">
                                {{ order.shippingFormatted }}
                            </dd>
                        </div>
                        <div
                            class="flex items-center justify-between border-t border-border pt-3 text-base font-bold text-foreground"
                        >
                            <dt>Total</dt>
                            <dd>{{ order.totalFormatted }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- Status History Timeline -->
                <section
                    aria-labelledby="order-timeline-heading"
                    class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-2xs"
                >
                    <div class="flex items-center gap-2">
                        <Clock class="size-4 text-primary" aria-hidden="true" />
                        <h2
                            id="order-timeline-heading"
                            class="text-base font-bold text-foreground"
                        >
                            Order Status Timeline
                        </h2>
                    </div>

                    <ul class="space-y-3">
                        <li
                            v-for="entry in order.timeline"
                            :key="entry.id"
                            class="flex items-start justify-between gap-3 rounded-xl border border-border/70 bg-secondary/25 px-4 py-3 text-sm"
                        >
                            <div>
                                <p class="font-semibold text-foreground">
                                    {{ entry.toStatusLabel }}
                                </p>
                                <p
                                    v-if="entry.note"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ entry.note }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                {{ entry.occurredAtDiff }}
                            </span>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- Right Column: Shipping Address Snapshot & Payment Verification -->
            <div class="space-y-6 lg:col-span-4">
                <section
                    aria-labelledby="order-shipping-heading"
                    class="space-y-3 rounded-2xl border border-border bg-card p-5 shadow-2xs"
                >
                    <div class="flex items-center gap-2">
                        <MapPin
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        <h2
                            id="order-shipping-heading"
                            class="text-sm font-bold text-foreground"
                        >
                            Shipping Address Snapshot
                        </h2>
                    </div>

                    <div class="space-y-1 text-sm">
                        <p class="font-semibold text-foreground">
                            {{ order.shippingAddress.recipientName }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ order.shippingAddress.phone }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ order.shippingAddress.formattedSummary }}
                        </p>
                        <p
                            v-if="order.shippingAddress.notes"
                            class="pt-1 text-xs text-muted-foreground italic"
                        >
                            Note: {{ order.shippingAddress.notes }}
                        </p>
                    </div>
                </section>

                <section
                    aria-labelledby="order-payment-heading"
                    class="space-y-3 rounded-2xl border border-border bg-card p-5 shadow-2xs"
                >
                    <div class="flex items-center gap-2">
                        <CreditCard
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        <h2
                            id="order-payment-heading"
                            class="text-sm font-bold text-foreground"
                        >
                            Payment Verification
                        </h2>
                    </div>

                    <div class="space-y-2 text-xs text-muted-foreground">
                        <p>
                            Method:
                            <strong class="text-foreground">{{
                                order.paymentMethod === 'cash_on_delivery'
                                    ? 'Cash on Delivery (COD)'
                                    : 'PayMongo Online Checkout'
                            }}</strong>
                        </p>
                        <p v-if="order.payment?.providerReference">
                            Reference:
                            <code
                                class="rounded bg-secondary px-1.5 py-0.5 text-[11px] text-foreground"
                            >
                                {{ order.payment.providerReference }}
                            </code>
                        </p>
                    </div>

                    <div
                        class="flex items-center gap-2 rounded-xl bg-secondary/50 p-3 text-xs text-muted-foreground"
                    >
                        <ShieldCheck
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span>
                            Online payments are marked paid only after
                            cryptographic webhook signature verification.
                        </span>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

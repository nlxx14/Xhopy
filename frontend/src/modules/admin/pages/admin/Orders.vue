<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2, CreditCard } from '@lucide/vue';
import { ref } from 'vue';
import type { AdminOrderRow } from '@modules/admin/types';
import Heading from '@shared/components/Heading.vue';
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
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    index as adminOrdersIndex,
    status as updateOrderStatusRoute,
} from '@/routes/admin/orders';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Back-Office', href: adminDashboard() },
            { title: 'Order Fulfillment', href: adminOrdersIndex() },
        ],
    },
});

defineProps<{
    orders: AdminOrderRow[];
}>();

const deliveryPaymentDialogOpen = ref(false);
const pendingDeliveryOrder = ref<AdminOrderRow | null>(null);
const isSubmitting = ref(false);

function handleTransition(order: AdminOrderRow, nextStatus: string): void {
    if (nextStatus === 'delivered') {
        pendingDeliveryOrder.value = order;
        deliveryPaymentDialogOpen.value = true;
        return;
    }

    router.post(
        updateOrderStatusRoute.url(order.id),
        { status: nextStatus },
        { preserveScroll: true },
    );
}

function openConfirmPaidForDeliveredOrder(order: AdminOrderRow): void {
    pendingDeliveryOrder.value = order;
    deliveryPaymentDialogOpen.value = true;
}

function submitDeliveryWithPaymentChoice(markPaid: boolean): void {
    const order = pendingDeliveryOrder.value;

    if (!order || isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;

    router.post(
        updateOrderStatusRoute.url(order.id),
        {
            status: 'delivered',
            mark_paid: markPaid,
            note: markPaid
                ? 'Marked delivered and payment confirmed as paid.'
                : 'Marked delivered (payment kept unpaid).',
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
                deliveryPaymentDialogOpen.value = false;
                pendingDeliveryOrder.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Order Fulfillment — XHopy Admin" />

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-6">
        <Heading
            title="Order Fulfillment"
            description="Review customer orders and execute server-validated state transitions"
        />

        <div
            class="overflow-x-auto rounded-2xl border border-border bg-card shadow-2xs"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-border bg-secondary/40 text-xs font-semibold text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3">Order</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Destination</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">
                            Allowed Transitions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/70">
                    <tr
                        v-for="order in orders"
                        :key="order.id"
                        class="hover:bg-secondary/25"
                    >
                        <td class="px-4 py-3">
                            <p class="font-bold text-foreground">
                                {{ order.orderNumber }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ order.placedAtFormatted }} ·
                                {{ order.itemCount }} item(s)
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-foreground">
                                {{ order.customerName }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ order.customerEmail }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">
                            {{ order.shippingCity }},
                            {{ order.shippingProvince }}
                        </td>
                        <td class="px-4 py-3 font-bold text-foreground">
                            {{ order.totalFormatted }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-1.5">
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
                                <Badge variant="secondary" class="text-[11px]">
                                    {{ order.paymentStatusLabel }}
                                </Badge>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div
                                v-if="order.allowedTransitions.length > 0"
                                class="inline-flex flex-wrap justify-end gap-1.5"
                            >
                                <Button
                                    v-for="next in order.allowedTransitions"
                                    :key="next.value"
                                    type="button"
                                    :variant="
                                        next.value === 'cancelled'
                                            ? 'ghost'
                                            : 'outline'
                                    "
                                    size="sm"
                                    class="h-8 rounded-lg text-xs"
                                    @click="handleTransition(order, next.value)"
                                >
                                    → {{ next.label }}
                                </Button>
                            </div>
                            <Button
                                v-else-if="
                                    order.status === 'delivered' &&
                                    order.paymentStatus !== 'paid'
                                "
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 gap-1.5 rounded-lg border-amber-500/50 text-xs font-semibold text-amber-500 hover:text-amber-400"
                                @click="openConfirmPaidForDeliveredOrder(order)"
                            >
                                <CreditCard
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                <span>Is Order Paid?</span>
                            </Button>
                            <span
                                v-else
                                class="text-xs text-muted-foreground italic"
                            >
                                Terminal state
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Post-Delivery Payment Confirmation Dialog -->
        <Dialog v-model:open="deliveryPaymentDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <CheckCircle2
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span>Confirm Delivery &amp; Payment Status</span>
                    </DialogTitle>
                    <DialogDescription v-if="pendingDeliveryOrder">
                        You are marking order
                        <strong class="text-foreground">{{
                            pendingDeliveryOrder.orderNumber
                        }}</strong>
                        as <strong class="text-foreground">Delivered</strong>.
                        Has the customer paid for this order?
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="pendingDeliveryOrder"
                    class="space-y-2 rounded-xl border border-border bg-secondary/35 p-4 text-xs"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Customer</span>
                        <span class="font-semibold text-foreground">{{
                            pendingDeliveryOrder.customerName
                        }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground"
                            >Payment Method</span
                        >
                        <span class="font-semibold text-foreground">
                            {{
                                pendingDeliveryOrder.paymentMethod ===
                                'cash_on_delivery'
                                    ? 'Cash on Delivery (COD)'
                                    : 'PayMongo Online Checkout'
                            }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground"
                            >Current Payment Status</span
                        >
                        <Badge variant="secondary" class="text-[11px]">
                            {{ pendingDeliveryOrder.paymentStatusLabel }}
                        </Badge>
                    </div>
                    <div
                        class="flex items-center justify-between border-t border-border/70 pt-2 text-sm"
                    >
                        <span class="font-medium text-muted-foreground"
                            >Order Total</span
                        >
                        <span class="font-extrabold text-foreground">{{
                            pendingDeliveryOrder.totalFormatted
                        }}</span>
                    </div>
                </div>

                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-10 rounded-xl text-xs"
                        :disabled="isSubmitting"
                        @click="submitDeliveryWithPaymentChoice(false)"
                    >
                        Delivered (Not Paid Yet)
                    </Button>
                    <Button
                        type="button"
                        class="h-10 gap-1.5 rounded-xl text-xs font-semibold"
                        :disabled="isSubmitting"
                        @click="submitDeliveryWithPaymentChoice(true)"
                    >
                        <CheckCircle2 class="size-4" aria-hidden="true" />
                        <span>Yes, Paid &amp; Delivered</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

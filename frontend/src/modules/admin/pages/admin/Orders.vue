<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { AdminOrderRow } from '@modules/admin/types';
import Heading from '@shared/components/Heading.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
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

function handleTransition(order: AdminOrderRow, nextStatus: string): void {
    router.post(
        updateOrderStatusRoute.url(order.id),
        { status: nextStatus },
        { preserveScroll: true },
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
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Boxes,
    ClipboardList,
    History,
    PackageCheck,
    Users,
} from '@lucide/vue';
import type {
    AdminAuditEntry,
    AdminMetrics,
    StockMovementEntry,
} from '@modules/admin/types';
import Heading from '@shared/components/Heading.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminCustomersIndex } from '@/routes/admin/customers';
import { index as adminOrdersIndex } from '@/routes/admin/orders';
import { index as adminProductsIndex } from '@/routes/admin/products';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin Back-Office',
                href: adminDashboard(),
            },
        ],
    },
});

defineProps<{
    metrics: AdminMetrics;
    recentMovements: StockMovementEntry[];
    recentAuditLogs: AdminAuditEntry[];
}>();
</script>

<template>
    <Head title="Admin Back-Office — XHopy" />

    <div class="mx-auto max-w-6xl space-y-8 px-4 py-6">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <Heading
                title="Back-Office Operations"
                description="Server-authoritative catalog, stock movement ledger, order fulfillment, and audit trail"
            />

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    as-child
                    variant="outline"
                    class="h-10 gap-1.5 rounded-xl text-xs font-semibold"
                >
                    <Link :href="adminProductsIndex.url()">
                        <Boxes class="size-3.5" aria-hidden="true" />
                        <span>Catalog &amp; Inventory</span>
                    </Link>
                </Button>
                <Button
                    as-child
                    variant="outline"
                    class="h-10 gap-1.5 rounded-xl text-xs font-semibold"
                >
                    <Link :href="adminOrdersIndex.url()">
                        <ClipboardList class="size-3.5" aria-hidden="true" />
                        <span>Orders</span>
                    </Link>
                </Button>
                <Button
                    as-child
                    variant="outline"
                    class="h-10 gap-1.5 rounded-xl text-xs font-semibold"
                >
                    <Link :href="adminCustomersIndex.url()">
                        <Users class="size-3.5" aria-hidden="true" />
                        <span>Customers &amp; Audit</span>
                    </Link>
                </Button>
            </div>
        </div>

        <!-- KPI Metrics Grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div
                class="rounded-2xl border border-border bg-card p-5 shadow-2xs"
            >
                <p class="text-xs font-medium text-muted-foreground">
                    Verified Paid Revenue
                </p>
                <p class="mt-2 text-2xl font-extrabold text-foreground">
                    {{ metrics.paidRevenueFormatted }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-border bg-card p-5 shadow-2xs"
            >
                <p class="text-xs font-medium text-muted-foreground">
                    Total Orders
                </p>
                <p class="mt-2 text-2xl font-extrabold text-foreground">
                    {{ metrics.totalOrders }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-border bg-card p-5 shadow-2xs"
            >
                <p class="text-xs font-medium text-muted-foreground">
                    Active / Pending Orders
                </p>
                <p class="mt-2 text-2xl font-extrabold text-primary">
                    {{ metrics.pendingOrders }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-border bg-card p-5 shadow-2xs"
            >
                <p class="text-xs font-medium text-muted-foreground">
                    Active Products
                </p>
                <p class="mt-2 text-2xl font-extrabold text-foreground">
                    {{ metrics.activeProducts }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-border bg-card p-5 shadow-2xs"
            >
                <p class="text-xs font-medium text-muted-foreground">
                    Low / Out of Stock (≤ 5)
                </p>
                <p
                    class="mt-2 flex items-center gap-2 text-2xl font-extrabold text-foreground"
                >
                    <span>{{ metrics.lowStockProducts }}</span>
                    <AlertTriangle
                        v-if="metrics.lowStockProducts > 0"
                        class="size-5 text-primary"
                        aria-hidden="true"
                    />
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- Stock Movement Ledger -->
            <section
                aria-labelledby="stock-ledger-heading"
                class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-2xs lg:col-span-6"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <PackageCheck
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        <h2
                            id="stock-ledger-heading"
                            class="text-base font-bold text-foreground"
                        >
                            Recent Inventory Movements
                        </h2>
                    </div>

                    <Link
                        :href="adminProductsIndex.url()"
                        class="text-xs font-semibold text-primary hover:underline"
                    >
                        Manage stock
                    </Link>
                </div>

                <ul
                    v-if="recentMovements.length > 0"
                    class="divide-y divide-border/70"
                >
                    <li
                        v-for="item in recentMovements"
                        :key="item.id"
                        class="flex items-start justify-between gap-3 py-3 text-sm"
                    >
                        <div class="space-y-0.5">
                            <p class="font-semibold text-foreground">
                                {{ item.productName }}
                                <span
                                    class="ml-1 text-xs font-normal text-muted-foreground"
                                >
                                    ({{ item.sku }})
                                </span>
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ item.typeLabel }} · by {{ item.actorName }} ·
                                {{ item.occurredAtDiff }}
                            </p>
                            <p
                                v-if="item.reason"
                                class="text-xs text-muted-foreground italic"
                            >
                                {{ item.reason }}
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <Badge
                                :variant="
                                    item.quantityDelta > 0
                                        ? 'default'
                                        : 'secondary'
                                "
                                class="text-xs font-bold tabular-nums"
                            >
                                {{
                                    item.quantityDelta > 0
                                        ? `+${item.quantityDelta}`
                                        : item.quantityDelta
                                }}
                            </Badge>
                            <p class="mt-1 text-[11px] text-muted-foreground">
                                {{ item.previousQuantity }} →
                                {{ item.resultingQuantity }}
                            </p>
                        </div>
                    </li>
                </ul>

                <p v-else class="text-xs text-muted-foreground">
                    No stock movements recorded yet.
                </p>
            </section>

            <!-- Recent Admin Audit Trail -->
            <section
                aria-labelledby="admin-audit-heading"
                class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-2xs lg:col-span-6"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <History
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        <h2
                            id="admin-audit-heading"
                            class="text-base font-bold text-foreground"
                        >
                            Recent Admin Audit Trail
                        </h2>
                    </div>

                    <Link
                        :href="adminCustomersIndex.url()"
                        class="text-xs font-semibold text-primary hover:underline"
                    >
                        Full audit log
                    </Link>
                </div>

                <ul
                    v-if="recentAuditLogs.length > 0"
                    class="divide-y divide-border/70"
                >
                    <li
                        v-for="log in recentAuditLogs"
                        :key="log.id"
                        class="flex items-start justify-between gap-3 py-3 text-sm"
                    >
                        <div class="space-y-0.5">
                            <p class="font-medium text-foreground">
                                {{ log.summary }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ log.actorName }} ({{ log.actorRole }}) ·
                                <code class="text-[11px]">{{
                                    log.action
                                }}</code>
                            </p>
                        </div>
                        <span class="shrink-0 text-xs text-muted-foreground">
                            {{ log.occurredAtDiff }}
                        </span>
                    </li>
                </ul>

                <p v-else class="text-xs text-muted-foreground">
                    No administrative actions recorded yet.
                </p>
            </section>
        </div>
    </div>
</template>

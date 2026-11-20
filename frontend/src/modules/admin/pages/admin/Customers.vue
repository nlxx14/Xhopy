<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { History, Shield } from '@lucide/vue';
import type { AdminAuditEntry, AdminCustomerRow } from '@modules/admin/types';
import Heading from '@shared/components/Heading.vue';
import { Badge } from '@shared/components/ui/badge';
import {
    CustomDropdown,
    type CustomDropdownOption,
} from '@shared/components/ui/custom-dropdown';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    index as adminCustomersIndex,
    role as updateCustomerRoleRoute,
} from '@/routes/admin/customers';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Back-Office', href: adminDashboard() },
            { title: 'Customers & Audit Trail', href: adminCustomersIndex() },
        ],
    },
});

defineProps<{
    customers: AdminCustomerRow[];
    roleOptions: CustomDropdownOption[];
    canAssignRoles: boolean;
    currentUserId: number;
    auditLogs: AdminAuditEntry[];
}>();

function handleRoleChange(customer: AdminCustomerRow, nextRole: string): void {
    if (nextRole === customer.role) {
        return;
    }

    router.patch(
        updateCustomerRoleRoute.url(customer.id),
        { role: nextRole },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Customers & Audit Trail — XHopy Admin" />

    <div class="mx-auto max-w-6xl space-y-8 px-4 py-6">
        <Heading
            title="Customers & Role Permissions"
            description="Least-privilege role management and immutable back-office audit logs"
        />

        <!-- Customers Table -->
        <div
            class="overflow-x-auto rounded-2xl border border-border bg-card shadow-2xs"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-border bg-secondary/40 text-xs font-semibold text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Verification</th>
                        <th class="px-4 py-3">Addresses</th>
                        <th class="px-4 py-3">Joined</th>
                        <th class="px-4 py-3 text-right">Role</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/70">
                    <tr
                        v-for="customer in customers"
                        :key="customer.id"
                        class="hover:bg-secondary/25"
                    >
                        <td class="px-4 py-3">
                            <p class="font-semibold text-foreground">
                                {{ customer.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ customer.email }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    customer.emailVerified
                                        ? 'secondary'
                                        : 'outline'
                                "
                                class="text-xs"
                            >
                                {{
                                    customer.emailVerified
                                        ? 'Verified'
                                        : 'Unverified'
                                }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">
                            {{ customer.addressCount }} saved
                        </td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">
                            {{ customer.joinedAtFormatted }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div
                                v-if="
                                    canAssignRoles &&
                                    customer.id !== currentUserId
                                "
                                class="inline-flex justify-end"
                            >
                                <CustomDropdown
                                    :model-value="customer.role"
                                    :options="roleOptions"
                                    :aria-label="`Role for ${customer.email}`"
                                    class="h-9 min-w-[9.5rem]"
                                    @update:model-value="
                                        handleRoleChange(customer, $event)
                                    "
                                />
                            </div>
                            <Badge
                                v-else
                                variant="default"
                                class="gap-1 text-xs"
                            >
                                <Shield class="size-3" aria-hidden="true" />
                                <span>{{ customer.roleLabel }}</span>
                            </Badge>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Immutable Admin Audit Trail -->
        <section
            aria-labelledby="full-audit-trail-heading"
            class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-2xs"
        >
            <div class="flex items-center gap-2">
                <History class="size-4 text-primary" aria-hidden="true" />
                <h2
                    id="full-audit-trail-heading"
                    class="text-base font-bold text-foreground"
                >
                    Back-Office Audit Trail
                </h2>
            </div>

            <ul v-if="auditLogs.length > 0" class="divide-y divide-border/70">
                <li
                    v-for="log in auditLogs"
                    :key="log.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"
                >
                    <div class="space-y-0.5">
                        <p class="font-medium text-foreground">
                            {{ log.summary }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Actor: {{ log.actorName }} ({{ log.actorRole }}) ·
                            Action:
                            <code class="text-[11px]">{{ log.action }}</code>
                            <span v-if="log.ipAddress">
                                · IP: {{ log.ipAddress }}
                            </span>
                        </p>
                    </div>
                    <span class="text-xs text-muted-foreground">
                        {{ log.occurredAtDiff }}
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>

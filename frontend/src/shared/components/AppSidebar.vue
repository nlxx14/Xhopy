<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Boxes,
    ClipboardList,
    LayoutGrid,
    Package,
    ShieldCheck,
    ShoppingBag,
    Store,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@shared/components/AppLogo.vue';
import NavMain from '@shared/components/NavMain.vue';
import NavUser from '@shared/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@shared/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminCustomersIndex } from '@/routes/admin/customers';
import { index as adminOrdersIndex } from '@/routes/admin/orders';
import { index as adminProductsIndex } from '@/routes/admin/products';
import { index as cartIndex } from '@/routes/cart';
import { index as ordersIndex } from '@/routes/orders';
import type { NavItem } from '@shared/types';

const page = usePage();

const isStaffOrAdmin = computed(() => {
    const role = page.props.auth?.user?.role;

    return role === 'staff' || role === 'admin';
});

const mainNavItems = computed<NavItem[]>(() => {
    if (isStaffOrAdmin.value) {
        return [
            {
                title: 'Admin Overview',
                href: adminDashboard(),
                icon: ShieldCheck,
            },
            {
                title: 'Catalog & Inventory',
                href: adminProductsIndex(),
                icon: Boxes,
            },
            {
                title: 'Order Fulfillment',
                href: adminOrdersIndex(),
                icon: ClipboardList,
            },
            {
                title: 'Customers & Audit',
                href: adminCustomersIndex(),
                icon: Users,
            },
            {
                title: 'Storefront',
                href: home(),
                icon: Store,
            },
        ];
    }

    return [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'My Orders',
            href: ordersIndex(),
            icon: Package,
        },
        {
            title: 'Shopping Cart',
            href: cartIndex(),
            icon: ShoppingBag,
        },
        {
            title: 'Storefront',
            href: home(),
            icon: Store,
        },
    ];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link
                            :href="
                                isStaffOrAdmin ? adminDashboard() : dashboard()
                            "
                        >
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

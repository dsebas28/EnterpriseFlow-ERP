<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { Link } from '@inertiajs/vue3';
import {
    Banknote,
    BarChart3,
    Boxes,
    ClipboardList,
    Contact,
    FileText,
    FolderTree,
    History,
    KeyRound,
    LayoutGrid,
    Package,
    Receipt,
    ReceiptText,
    ScrollText,
    Truck,
    Users,
    Wallet,
    Warehouse,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { can, canAny } = usePermissions();

const mainNavItems = computed(() =>
    [
        { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid, visible: true },
        { title: 'Reports', url: '/reports', icon: BarChart3, visible: can('reports.view') },
    ].filter((item) => item.visible),
);

const catalogNavItems = computed(() =>
    [
        { title: 'Products', url: '/catalog/products', icon: Package, visible: can('products.view') },
        { title: 'Categories', url: '/catalog/categories', icon: FolderTree, visible: canAny('products.view', 'categories.manage') },
    ].filter((item) => item.visible),
);

const inventoryNavItems = computed(() =>
    [
        { title: 'Stock', url: '/inventory/stock', icon: Boxes, visible: can('inventory.view') },
        { title: 'Movements', url: '/inventory/movements', icon: History, visible: can('inventory.view') },
        { title: 'Warehouses', url: '/inventory/warehouses', icon: Warehouse, visible: canAny('warehouses.view', 'warehouses.manage') },
    ].filter((item) => item.visible),
);

const salesNavItems = computed(() =>
    [
        { title: 'Sales', url: '/sales/orders', icon: Receipt, visible: can('sales.view') },
        { title: 'Customers', url: '/sales/customers', icon: Contact, visible: can('customers.view') },
    ].filter((item) => item.visible),
);

const financeNavItems = computed(() =>
    [
        { title: 'Invoices', url: '/finance/invoices', icon: FileText, visible: can('invoices.view') },
        { title: 'Supplier bills', url: '/finance/bills', icon: ReceiptText, visible: can('invoices.view') },
        { title: 'Payments', url: '/finance/payments', icon: Banknote, visible: can('payments.view') },
        { title: 'Expenses', url: '/finance/expenses', icon: Wallet, visible: can('expenses.view') },
    ].filter((item) => item.visible),
);

const purchasingNavItems = computed(() =>
    [
        { title: 'Purchase orders', url: '/purchasing/orders', icon: ClipboardList, visible: can('purchases.view') },
        { title: 'Suppliers', url: '/purchasing/suppliers', icon: Truck, visible: can('suppliers.view') },
    ].filter((item) => item.visible),
);

const teamNavItems = computed(() =>
    [
        { title: 'Members', url: '/team/members', icon: Users, visible: can('users.manage') },
        { title: 'Roles & permissions', url: '/team/roles', icon: KeyRound, visible: canAny('roles.manage', 'users.manage') },
        { title: 'Audit trail', url: '/audit', icon: ScrollText, visible: can('audit.view') },
    ].filter((item) => item.visible),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain label="Platform" :items="mainNavItems" />
            <NavMain v-if="salesNavItems.length" label="Sales" :items="salesNavItems" />
            <NavMain v-if="catalogNavItems.length" label="Catalog" :items="catalogNavItems" />
            <NavMain v-if="inventoryNavItems.length" label="Inventory" :items="inventoryNavItems" />
            <NavMain v-if="purchasingNavItems.length" label="Purchasing" :items="purchasingNavItems" />
            <NavMain v-if="financeNavItems.length" label="Finance" :items="financeNavItems" />
            <NavMain v-if="teamNavItems.length" label="Team" :items="teamNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

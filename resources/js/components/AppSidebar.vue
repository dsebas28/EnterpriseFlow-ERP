<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { Link } from '@inertiajs/vue3';
import { Boxes, ClipboardList, FolderTree, History, KeyRound, LayoutGrid, Package, Truck, Users, Warehouse } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { can, canAny } = usePermissions();

const mainNavItems = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
];

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
            <NavMain v-if="catalogNavItems.length" label="Catalog" :items="catalogNavItems" />
            <NavMain v-if="inventoryNavItems.length" label="Inventory" :items="inventoryNavItems" />
            <NavMain v-if="purchasingNavItems.length" label="Purchasing" :items="purchasingNavItems" />
            <NavMain v-if="teamNavItems.length" label="Team" :items="teamNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

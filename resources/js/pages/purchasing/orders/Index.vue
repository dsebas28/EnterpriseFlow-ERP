<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney, type MoneyValue } from '@/composables/useMoney';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Option, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ClipboardList, Plus, Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface OrderRow {
    id: string;
    number: string;
    status: string;
    status_label: string;
    supplier: { id: string; name: string };
    warehouse: { id: string; name: string };
    order_date: string;
    expected_date: string | null;
    total: MoneyValue;
}

const props = defineProps<{
    orders: Paginated<OrderRow>;
    filters: { search?: string; status?: string; from?: string; to?: string };
    statuses: Option[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchasing', href: '/purchasing/orders' },
    { title: 'Purchase orders', href: '/purchasing/orders' },
];

const { can } = usePermissions();

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});
const apply = () => {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
    router.get(route('purchasing.orders.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};
watch(() => filters.search, useDebounceFn(apply, 300));
watch(() => [filters.status, filters.from, filters.to], apply);

const formatDate = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { dateStyle: 'medium' });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Purchase orders" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <HeadingSmall title="Purchase orders" description="From request to approval to goods received." />
                <Button v-if="can('purchases.create')" as-child>
                    <Link :href="route('purchasing.orders.create')"><Plus class="mr-2 h-4 w-4" /> New order</Link>
                </Button>
            </div>

            <PageAlerts />

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Order number or supplier" class="pl-9" aria-label="Search orders" />
                </div>
                <div class="flex flex-wrap gap-1" role="group" aria-label="Status">
                    <Button size="sm" :variant="filters.status === '' ? 'default' : 'outline'" @click="filters.status = ''">All</Button>
                    <Button
                        v-for="status in statuses"
                        :key="status.value"
                        size="sm"
                        :variant="filters.status === status.value ? 'default' : 'outline'"
                        @click="filters.status = status.value"
                    >
                        {{ status.label }}
                    </Button>
                </div>
                <Input v-model="filters.from" type="date" class="w-auto" aria-label="From date" />
                <Input v-model="filters.to" type="date" class="w-auto" aria-label="To date" />
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Order</th>
                            <th class="px-4 py-3 font-medium">Supplier</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Date</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="order in orders.data"
                            :key="order.id"
                            class="cursor-pointer hover:bg-muted/40"
                            @click="router.visit(route('purchasing.orders.show', order.id))"
                        >
                            <td class="px-4 py-3">
                                <Link :href="route('purchasing.orders.show', order.id)" class="font-mono font-medium hover:underline" @click.stop>
                                    {{ order.number }}
                                </Link>
                                <p class="text-xs text-muted-foreground">→ {{ order.warehouse.name }}</p>
                            </td>
                            <td class="px-4 py-3">{{ order.supplier.name }}</td>
                            <td class="hidden px-4 py-3 text-muted-foreground md:table-cell">
                                {{ formatDate(order.order_date) }}
                                <span v-if="order.expected_date" class="block text-xs">expected {{ formatDate(order.expected_date) }}</span>
                            </td>
                            <td class="px-4 py-3"><StatusBadge :status="order.status" :label="order.status_label" /></td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums">{{ formatMoney(order.total) }}</td>
                        </tr>
                        <tr v-if="orders.data.length === 0">
                            <td colspan="5" class="px-4 py-16 text-center">
                                <ClipboardList class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                                <p class="font-medium">No purchase orders</p>
                                <p class="mt-1 text-sm text-muted-foreground">Create an order to request stock from a supplier.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="orders.meta" />
        </div>
    </AppLayout>
</template>

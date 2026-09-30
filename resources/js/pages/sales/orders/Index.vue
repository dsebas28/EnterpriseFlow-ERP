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
import { Plus, Receipt, Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface SaleRow {
    id: string;
    number: string;
    status: string;
    status_label: string;
    customer: { id: string; name: string };
    warehouse: { id: string; name: string };
    sale_date: string;
    total: MoneyValue;
    balance_due: MoneyValue;
}

const props = defineProps<{
    sales: Paginated<SaleRow>;
    filters: { search?: string; status?: string; from?: string; to?: string };
    statuses: Option[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sales', href: '/sales/orders' },
    { title: 'Sales', href: '/sales/orders' },
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
    router.get(route('sales.orders.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};
watch(() => filters.search, useDebounceFn(apply, 300));
watch(() => [filters.status, filters.from, filters.to], apply);

const formatDate = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { dateStyle: 'medium' });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Sales" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <HeadingSmall title="Sales" description="Quotes, confirmed sales and what is still to be collected." />
                <Button v-if="can('sales.create')" as-child>
                    <Link :href="route('sales.orders.create')"><Plus class="mr-2 h-4 w-4" /> New sale</Link>
                </Button>
            </div>

            <PageAlerts />

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Sale number or customer" class="pl-9" aria-label="Search sales" />
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
                            <th class="px-4 py-3 font-medium">Sale</th>
                            <th class="px-4 py-3 font-medium">Customer</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Date</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                            <th class="hidden px-4 py-3 text-right font-medium sm:table-cell">Due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="sale in sales.data"
                            :key="sale.id"
                            class="cursor-pointer hover:bg-muted/40"
                            @click="router.visit(route('sales.orders.show', sale.id))"
                        >
                            <td class="px-4 py-3">
                                <Link :href="route('sales.orders.show', sale.id)" class="font-mono font-medium hover:underline" @click.stop>
                                    {{ sale.number }}
                                </Link>
                            </td>
                            <td class="px-4 py-3">{{ sale.customer.name }}</td>
                            <td class="hidden px-4 py-3 text-muted-foreground md:table-cell">{{ formatDate(sale.sale_date) }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="sale.status" :label="sale.status_label" /></td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums">{{ formatMoney(sale.total) }}</td>
                            <td class="hidden px-4 py-3 text-right tabular-nums sm:table-cell">
                                {{ sale.balance_due.amount > 0 ? formatMoney(sale.balance_due) : '—' }}
                            </td>
                        </tr>
                        <tr v-if="sales.data.length === 0">
                            <td colspan="6" class="px-4 py-16 text-center">
                                <Receipt class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                                <p class="font-medium">No sales found</p>
                                <p class="mt-1 text-sm text-muted-foreground">Create a sale to reserve stock for a customer.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="sales.meta" />
        </div>
    </AppLayout>
</template>

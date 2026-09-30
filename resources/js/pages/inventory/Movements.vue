<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney, type MoneyValue } from '@/composables/useMoney';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Option, Paginated, Warehouse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ArrowDownRight, ArrowUpRight, History, Search, X } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface Movement {
    id: number;
    type: string;
    type_label: string;
    quantity: number;
    balance_after: number;
    unit_cost: MoneyValue | null;
    product: { id: string; sku: string; name: string };
    warehouse: { id: string; code: string; name: string };
    user: string | null;
    reference: { type: string; id: string } | null;
    transfer_id: string | null;
    notes: string | null;
    occurred_at: string;
}

const props = defineProps<{
    movements: Paginated<Movement>;
    filters: { product_id?: string; warehouse_id?: string; type?: string; from?: string; to?: string; search?: string };
    product: { id: string; sku: string; name: string } | null;
    warehouses: { data: Warehouse[] };
    types: Option[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventory', href: '/inventory/stock' },
    { title: 'Movements', href: '/inventory/movements' },
];

const filters = reactive({
    product_id: props.filters.product_id ?? '',
    warehouse_id: props.filters.warehouse_id ?? '',
    type: props.filters.type ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    search: props.filters.search ?? '',
});

const apply = () => {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
    router.get(route('inventory.movements.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};
watch(() => filters.search, useDebounceFn(apply, 300));
watch(() => [filters.product_id, filters.warehouse_id, filters.type, filters.from, filters.to], apply);

const formatDate = (iso: string) => new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Stock movements" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <HeadingSmall
                :title="product ? `Kardex · ${product.name}` : 'Stock movements'"
                description="The inventory ledger. Movements are never edited or deleted; corrections are new movements."
            />

            <div class="flex flex-wrap items-center gap-2">
                <span v-if="product" class="inline-flex h-9 items-center gap-2 rounded-md border bg-secondary px-3 text-sm">
                    <span class="font-mono text-xs">{{ product.sku }}</span>
                    <button type="button" aria-label="Clear product filter" @click="filters.product_id = ''"><X class="h-3.5 w-3.5" /></button>
                </span>
                <div v-else class="relative w-full sm:w-64">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Product name or SKU" class="pl-9" aria-label="Search movements" />
                </div>
                <select v-model="filters.warehouse_id" :class="selectClass" aria-label="Warehouse">
                    <option value="">All warehouses</option>
                    <option v-for="warehouse in warehouses.data" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
                </select>
                <select v-model="filters.type" :class="selectClass" aria-label="Type">
                    <option value="">All types</option>
                    <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>
                <Input v-model="filters.from" type="date" class="w-auto" aria-label="From date" />
                <Input v-model="filters.to" type="date" class="w-auto" aria-label="To date" />
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="route('inventory.stock.index')">Back to stock</Link>
                </Button>
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th v-if="!product" class="px-4 py-3 font-medium">Item</th>
                            <th class="px-4 py-3 font-medium">Warehouse</th>
                            <th class="px-4 py-3 text-right font-medium">Qty</th>
                            <th class="px-4 py-3 text-right font-medium">Balance</th>
                            <th class="hidden px-4 py-3 font-medium lg:table-cell">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="movement in movements.data" :key="movement.id">
                            <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">{{ formatDate(movement.occurred_at) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 rounded bg-secondary px-1.5 py-0.5 text-xs">{{
                                    movement.type_label
                                }}</span>
                            </td>
                            <td v-if="!product" class="px-4 py-3">
                                <Link :href="route('inventory.movements.index', { product_id: movement.product.id })" class="hover:underline">
                                    {{ movement.product.name }}
                                </Link>
                                <p class="font-mono text-xs text-muted-foreground">{{ movement.product.sku }}</p>
                            </td>
                            <td class="px-4 py-3">{{ movement.warehouse.name }}</td>
                            <td
                                class="px-4 py-3 text-right font-medium tabular-nums"
                                :class="movement.quantity > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                            >
                                <span class="inline-flex items-center gap-0.5">
                                    <ArrowUpRight v-if="movement.quantity > 0" class="h-3.5 w-3.5" />
                                    <ArrowDownRight v-else class="h-3.5 w-3.5" />
                                    {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ movement.balance_after }}</td>
                            <td class="hidden px-4 py-3 text-xs text-muted-foreground lg:table-cell">
                                <span v-if="movement.notes">{{ movement.notes }}</span>
                                <span v-if="movement.unit_cost"> · {{ formatMoney(movement.unit_cost) }}/u</span>
                                <span v-if="movement.user"> · {{ movement.user }}</span>
                            </td>
                        </tr>
                        <tr v-if="movements.data.length === 0">
                            <td colspan="7" class="px-4 py-16 text-center">
                                <History class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                                <p class="font-medium">No movements found</p>
                                <p class="mt-1 text-sm text-muted-foreground">Purchases, sales, adjustments and transfers will appear here.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="movements.meta" />
        </div>
    </AppLayout>
</template>

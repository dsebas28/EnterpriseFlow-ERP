<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney, type MoneyValue } from '@/composables/useMoney';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Option, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ReceiptText, Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface BillRow {
    id: string;
    number: string;
    supplier_reference: string;
    status: string;
    status_label: string;
    supplier: { id: string; name: string };
    purchase_order: { id: string; number: string };
    bill_date: string;
    due_date: string;
    total: MoneyValue;
    balance_due: MoneyValue;
}

const props = defineProps<{
    bills: Paginated<BillRow>;
    filters: { search: string; status: string };
    statuses: Option[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Finance', href: '/finance/invoices' },
    { title: 'Supplier bills', href: '/finance/bills' },
];

const filters = reactive({ ...props.filters });
const apply = () => {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
    router.get(route('finance.bills.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};
watch(() => filters.search, useDebounceFn(apply, 300));
watch(() => filters.status, apply);

const formatDate = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { dateStyle: 'medium' });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Supplier bills" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <HeadingSmall
                title="Supplier bills"
                description="What you owe suppliers, ordered by due date. Bills are registered from received purchase orders."
            />

            <PageAlerts />

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Bill, supplier reference or supplier" class="pl-9" aria-label="Search bills" />
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
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Bill</th>
                            <th class="px-4 py-3 font-medium">Supplier</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Due</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                            <th class="hidden px-4 py-3 text-right font-medium sm:table-cell">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="bill in bills.data"
                            :key="bill.id"
                            class="cursor-pointer hover:bg-muted/40"
                            @click="router.visit(route('finance.bills.show', bill.id))"
                        >
                            <td class="px-4 py-3">
                                <Link :href="route('finance.bills.show', bill.id)" class="font-mono font-medium hover:underline" @click.stop>{{
                                    bill.number
                                }}</Link>
                                <p class="font-mono text-xs text-muted-foreground">
                                    Ref. {{ bill.supplier_reference }} · {{ bill.purchase_order.number }}
                                </p>
                            </td>
                            <td class="px-4 py-3">{{ bill.supplier.name }}</td>
                            <td
                                class="hidden px-4 py-3 md:table-cell"
                                :class="bill.status === 'overdue' ? 'font-medium text-red-600' : 'text-muted-foreground'"
                            >
                                {{ formatDate(bill.due_date) }}
                            </td>
                            <td class="px-4 py-3"><StatusBadge :status="bill.status" :label="bill.status_label" /></td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums">{{ formatMoney(bill.total) }}</td>
                            <td class="hidden px-4 py-3 text-right tabular-nums sm:table-cell">
                                {{ bill.balance_due.amount > 0 ? formatMoney(bill.balance_due) : '—' }}
                            </td>
                        </tr>
                        <tr v-if="bills.data.length === 0">
                            <td colspan="6" class="px-4 py-16 text-center">
                                <ReceiptText class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                                <p class="font-medium">No supplier bills</p>
                                <p class="mt-1 text-sm text-muted-foreground">Open a received purchase order and register the supplier's invoice.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="bills.meta" />
        </div>
    </AppLayout>
</template>

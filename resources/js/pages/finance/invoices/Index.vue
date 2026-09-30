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
import { FileText, Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface InvoiceRow {
    id: string;
    number: string | null;
    status: string;
    status_label: string;
    customer: { id: string; name: string };
    sale: { id: string; number: string };
    issue_date: string | null;
    due_date: string;
    total: MoneyValue;
    balance_due: MoneyValue;
}

const props = defineProps<{
    invoices: Paginated<InvoiceRow>;
    filters: { search?: string; status?: string; from?: string; to?: string };
    statuses: Option[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Finance', href: '/finance/invoices' },
    { title: 'Invoices', href: '/finance/invoices' },
];

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});
const apply = () => {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
    router.get(route('finance.invoices.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};
watch(() => filters.search, useDebounceFn(apply, 300));
watch(() => [filters.status, filters.from, filters.to], apply);

const formatDate = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { dateStyle: 'medium' });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Invoices" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <HeadingSmall
                title="Invoices"
                description="Customer invoices and what remains to be collected. Invoices are created from confirmed sales."
            />

            <PageAlerts />

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Invoice number or customer" class="pl-9" aria-label="Search invoices" />
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
                <Input v-model="filters.from" type="date" class="w-auto" aria-label="Due from" />
                <Input v-model="filters.to" type="date" class="w-auto" aria-label="Due to" />
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Invoice</th>
                            <th class="px-4 py-3 font-medium">Customer</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Due</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                            <th class="hidden px-4 py-3 text-right font-medium sm:table-cell">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="invoice in invoices.data"
                            :key="invoice.id"
                            class="cursor-pointer hover:bg-muted/40"
                            @click="router.visit(route('finance.invoices.show', invoice.id))"
                        >
                            <td class="px-4 py-3">
                                <Link :href="route('finance.invoices.show', invoice.id)" class="font-mono font-medium hover:underline" @click.stop>
                                    {{ invoice.number ?? 'Draft' }}
                                </Link>
                                <p class="font-mono text-xs text-muted-foreground">{{ invoice.sale.number }}</p>
                            </td>
                            <td class="px-4 py-3">{{ invoice.customer.name }}</td>
                            <td
                                class="hidden px-4 py-3 md:table-cell"
                                :class="invoice.status === 'overdue' ? 'font-medium text-red-600' : 'text-muted-foreground'"
                            >
                                {{ formatDate(invoice.due_date) }}
                            </td>
                            <td class="px-4 py-3"><StatusBadge :status="invoice.status" :label="invoice.status_label" /></td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums">{{ formatMoney(invoice.total) }}</td>
                            <td class="hidden px-4 py-3 text-right tabular-nums sm:table-cell">
                                {{ invoice.balance_due.amount > 0 ? formatMoney(invoice.balance_due) : '—' }}
                            </td>
                        </tr>
                        <tr v-if="invoices.data.length === 0">
                            <td colspan="6" class="px-4 py-16 text-center">
                                <FileText class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                                <p class="font-medium">No invoices found</p>
                                <p class="mt-1 text-sm text-muted-foreground">Open a confirmed sale and choose “Create invoice”.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="invoices.meta" />
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney, type MoneyValue } from '@/composables/useMoney';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Option, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ArrowDownLeft, ArrowUpRight, Banknote, Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface PaymentRow {
    id: string;
    number: string;
    direction: 'incoming' | 'outgoing';
    method_label: string;
    amount: MoneyValue;
    paid_at: string;
    reference: string | null;
    status: 'posted' | 'voided';
    document: { type: 'invoice' | 'supplier_bill'; id: string; number: string | null; party: string };
    created_by: string | null;
}

const props = defineProps<{
    payments: Paginated<PaymentRow>;
    totals: { incoming: MoneyValue; outgoing: MoneyValue };
    filters: { direction?: string; method?: string; from?: string; to?: string; search?: string };
    methods: Option[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Finance', href: '/finance/invoices' },
    { title: 'Payments', href: '/finance/payments' },
];

const filters = reactive({
    direction: props.filters.direction ?? '',
    method: props.filters.method ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    search: props.filters.search ?? '',
});
const apply = () => {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
    router.get(route('finance.payments.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};
watch(() => filters.search, useDebounceFn(apply, 300));
watch(() => [filters.direction, filters.method, filters.from, filters.to], apply);

const documentUrl = (payment: PaymentRow) =>
    payment.document.type === 'invoice' ? route('finance.invoices.show', payment.document.id) : route('finance.bills.show', payment.document.id);

const formatDate = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { dateStyle: 'medium' });
const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Payments" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <HeadingSmall title="Payments" description="Every payment received from customers and paid to suppliers. Voided payments stay visible." />

            <PageAlerts />

            <dl class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg border p-4">
                    <dt class="flex items-center gap-1.5 text-xs uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                        <ArrowDownLeft class="h-3.5 w-3.5" /> Received
                    </dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ formatMoney(totals.incoming) }}</dd>
                </div>
                <div class="rounded-lg border p-4">
                    <dt class="flex items-center gap-1.5 text-xs uppercase tracking-wide text-red-700 dark:text-red-300">
                        <ArrowUpRight class="h-3.5 w-3.5" /> Paid out
                    </dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ formatMoney(totals.outgoing) }}</dd>
                </div>
            </dl>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full sm:w-64">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Payment no. or reference" class="pl-9" aria-label="Search payments" />
                </div>
                <div class="flex gap-1" role="group" aria-label="Direction">
                    <Button size="sm" :variant="filters.direction === '' ? 'default' : 'outline'" @click="filters.direction = ''">All</Button>
                    <Button size="sm" :variant="filters.direction === 'incoming' ? 'default' : 'outline'" @click="filters.direction = 'incoming'">
                        Received
                    </Button>
                    <Button size="sm" :variant="filters.direction === 'outgoing' ? 'default' : 'outline'" @click="filters.direction = 'outgoing'">
                        Paid out
                    </Button>
                </div>
                <select v-model="filters.method" :class="selectClass" aria-label="Method">
                    <option value="">Any method</option>
                    <option v-for="method in methods" :key="method.value" :value="method.value">{{ method.label }}</option>
                </select>
                <Input v-model="filters.from" type="date" class="w-auto" aria-label="From date" />
                <Input v-model="filters.to" type="date" class="w-auto" aria-label="To date" />
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Payment</th>
                            <th class="px-4 py-3 font-medium">Document</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Method</th>
                            <th class="px-4 py-3 text-right font-medium">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="payment in payments.data" :key="payment.id" :class="{ 'opacity-60': payment.status === 'voided' }">
                            <td class="px-4 py-3">
                                <p class="font-mono font-medium">{{ payment.number }}</p>
                                <p class="text-xs text-muted-foreground">{{ formatDate(payment.paid_at) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <Link :href="documentUrl(payment)" class="font-mono hover:underline">{{ payment.document.number ?? 'Draft' }}</Link>
                                <p class="text-xs text-muted-foreground">{{ payment.document.party }}</p>
                            </td>
                            <td class="hidden px-4 py-3 text-muted-foreground md:table-cell">
                                {{ payment.method_label }}<template v-if="payment.reference"> · {{ payment.reference }}</template>
                            </td>
                            <td
                                class="px-4 py-3 text-right font-medium tabular-nums"
                                :class="
                                    payment.direction === 'incoming' ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300'
                                "
                            >
                                <span :class="{ 'line-through': payment.status === 'voided' }">
                                    {{ payment.direction === 'incoming' ? '+' : '−' }}{{ formatMoney(payment.amount) }}
                                </span>
                                <span v-if="payment.status === 'voided'" class="block text-xs font-normal">voided</span>
                            </td>
                        </tr>
                        <tr v-if="payments.data.length === 0">
                            <td colspan="4" class="px-4 py-16 text-center">
                                <Banknote class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                                <p class="font-medium">No payments found</p>
                                <p class="mt-1 text-sm text-muted-foreground">Record payments from an invoice or a supplier bill.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="payments.meta" />
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import ExportsList, { type ExportRow } from '@/components/ExportsList.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, FileDown, FileSpreadsheet, FileText, SearchX } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

interface ColumnDef {
    key: string;
    label: string;
    type: 'text' | 'number' | 'money' | 'percent';
}
type Cell = string | number | null;
type Named = { id: string | number; name: string };

const props = defineProps<{
    report: { key: string; title: string; description: string; filters: string[]; columns: ColumnDef[] };
    rows: Record<string, Cell>[];
    totalRows: number;
    totals: Record<string, Cell>;
    filters: Record<string, string | number | null>;
    currency: { code: string; decimals: number };
    options: { warehouses?: Named[]; categories?: Named[]; expenseCategories?: Named[]; customers?: Named[]; suppliers?: Named[]; users?: Named[] };
    exports: ExportRow[];
    canExport: boolean;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Reports', href: '/reports' },
    { title: props.report.title, href: '#' },
]);

const has = (filter: string) => props.report.filters.includes(filter);

const form = reactive<Record<string, string>>(
    Object.fromEntries(Object.entries(props.filters).map(([key, value]) => [key, value === null ? '' : String(value)])),
);

const query = () => Object.fromEntries(Object.entries(form).filter(([key, value]) => value !== '' && has(key === 'to' ? 'from' : key)));
const apply = () => router.get(route('reports.show', props.report.key), query(), { preserveScroll: true, preserveState: true });
const exportAs = (format: string) => router.post(route('reports.export', props.report.key), { ...query(), format }, { preserveScroll: true });

const money = new Intl.NumberFormat(undefined, { style: 'currency', currency: props.currency.code });
const number = new Intl.NumberFormat();
const display = (column: ColumnDef, value: Cell | undefined) => {
    if (value === null || value === undefined || value === '') return '—';
    switch (column.type) {
        case 'money':
            return money.format(Number(value) / 10 ** props.currency.decimals);
        case 'number':
            return number.format(Number(value));
        case 'percent':
            return `${Number(value).toFixed(1)}%`;
        default:
            return String(value);
    }
};
const negative = (column: ColumnDef, value: Cell | undefined) => column.type === 'money' && Number(value) < 0;

const selects = computed(() =>
    [
        { key: 'warehouse_id', label: 'Warehouse', items: props.options.warehouses },
        { key: 'category_id', label: 'Category', items: props.options.categories },
        { key: 'expense_category_id', label: 'Category', items: props.options.expenseCategories },
        { key: 'customer_id', label: 'Customer', items: props.options.customers },
        { key: 'supplier_id', label: 'Supplier', items: props.options.suppliers },
        { key: 'user_id', label: 'User', items: props.options.users },
    ].filter((select) => has(select.key) && select.items),
);

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="report.title" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" as-child>
                        <Link :href="route('reports.index')" aria-label="Back to reports"><ArrowLeft class="h-4 w-4" /></Link>
                    </Button>
                    <HeadingSmall :title="report.title" :description="report.description" />
                </div>
                <div v-if="canExport" class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" @click="exportAs('csv')"><FileDown class="mr-1.5 h-4 w-4" /> CSV</Button>
                    <Button variant="outline" size="sm" @click="exportAs('xlsx')"><FileSpreadsheet class="mr-1.5 h-4 w-4" /> Excel</Button>
                    <Button variant="outline" size="sm" @click="exportAs('pdf')"><FileText class="mr-1.5 h-4 w-4" /> PDF</Button>
                </div>
            </div>

            <PageAlerts />

            <form class="grid gap-3 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6" @submit.prevent="apply">
                <template v-if="has('from')">
                    <div class="grid gap-1.5">
                        <Label for="f-from">From</Label>
                        <Input id="f-from" v-model="form.from" type="date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="f-to">To</Label>
                        <Input id="f-to" v-model="form.to" type="date" />
                    </div>
                </template>
                <div v-if="has('group_by')" class="grid gap-1.5">
                    <Label for="f-group">Group by</Label>
                    <select id="f-group" v-model="form.group_by" :class="selectClass">
                        <option value="day">Day</option>
                        <option value="week">Week</option>
                        <option value="month">Month</option>
                    </select>
                </div>
                <div v-for="select in selects" :key="select.key" class="grid gap-1.5">
                    <Label :for="`f-${select.key}`">{{ select.label }}</Label>
                    <select :id="`f-${select.key}`" v-model="form[select.key]" :class="selectClass">
                        <option value="">All</option>
                        <option v-for="item in select.items" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <Button type="submit" class="w-full">Apply</Button>
                </div>
            </form>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th
                                v-for="column in report.columns"
                                :key="column.key"
                                class="whitespace-nowrap px-4 py-3 font-medium"
                                :class="{ 'text-right': column.type !== 'text' }"
                            >
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(row, index) in rows" :key="index" class="hover:bg-muted/30">
                            <td
                                v-for="column in report.columns"
                                :key="column.key"
                                class="whitespace-nowrap px-4 py-2.5"
                                :class="{ 'text-right tabular-nums': column.type !== 'text', 'text-red-600': negative(column, row[column.key]) }"
                            >
                                {{ display(column, row[column.key]) }}
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td :colspan="report.columns.length" class="px-4 py-16 text-center">
                                <SearchX class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                                <p class="font-medium">No data for these filters</p>
                                <p class="mt-1 text-sm text-muted-foreground">Try a wider date range.</p>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows.length && Object.keys(totals).length" class="border-t-2 bg-muted/20 font-semibold">
                        <tr>
                            <td
                                v-for="column in report.columns"
                                :key="column.key"
                                class="whitespace-nowrap px-4 py-3"
                                :class="{ 'text-right tabular-nums': column.type !== 'text', 'text-red-600': negative(column, totals[column.key]) }"
                            >
                                {{ column.key in totals ? display(column, totals[column.key]) : '' }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p v-if="totalRows > rows.length" class="text-sm text-muted-foreground">
                Showing the first {{ rows.length }} of {{ totalRows }} rows. Totals include every row; export the report for the full list.
            </p>

            <section v-if="exports.length" class="space-y-2">
                <h3 class="text-sm font-medium">Your exports of this report</h3>
                <ExportsList :exports="exports" />
            </section>
        </div>
    </AppLayout>
</template>

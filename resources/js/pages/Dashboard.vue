<script setup lang="ts">
import DailySalesChart from '@/components/charts/DailySalesChart.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatMoney, type MoneyValue } from '@/composables/useMoney';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, PackageX } from 'lucide-vue-next';
import { computed } from 'vue';

interface DocRow {
    id: string;
    number: string;
    status: string;
    status_label: string;
    total: MoneyValue;
}

const props = defineProps<{
    generatedAt: string;
    sales?: {
        today: MoneyValue;
        today_count: number;
        month: MoneyValue;
        month_count: number;
        chart: { date: string; total: number }[];
        top_products: { product: string; quantity: number; revenue: MoneyValue }[];
        recent: (DocRow & { customer: string })[];
    };
    finance?: {
        expenses_month: MoneyValue;
        profit_month: MoneyValue;
        gross_profit_month: MoneyValue;
        receivable: MoneyValue;
        receivable_overdue: number;
        payable: MoneyValue;
        payable_overdue: number;
    };
    inventory?: {
        low_stock: { sku: string; product: string; on_hand: number; min_stock: number; shortfall: number }[];
        low_stock_count: number;
    };
    purchases?: { recent: (DocRow & { supplier: string })[] };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];
const page = usePage<SharedData>();

const currency = computed(() => page.props.tenant?.current?.currency ?? 'USD');
const decimals = computed(
    () => new Intl.NumberFormat(undefined, { style: 'currency', currency: currency.value }).resolvedOptions().maximumFractionDigits ?? 2,
);
const monthName = new Date().toLocaleDateString(undefined, { month: 'long' });
const nothingVisible = computed(() => !props.sales && !props.finance && !props.inventory && !props.purchases);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">{{ page.props.tenant?.current?.name }}</h1>
                    <p class="text-sm text-muted-foreground">Figures for {{ monthName }}, computed from recorded transactions.</p>
                </div>
                <p class="text-xs text-muted-foreground">Updated {{ new Date(generatedAt).toLocaleTimeString(undefined, { timeStyle: 'short' }) }}</p>
            </div>

            <!-- Headline figures -->
            <dl v-if="sales || finance" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <template v-if="sales">
                    <div class="rounded-lg border p-4">
                        <dt class="text-sm text-muted-foreground">Sales today</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ formatMoney(sales.today) }}</dd>
                        <dd class="text-xs text-muted-foreground">{{ sales.today_count }} {{ sales.today_count === 1 ? 'sale' : 'sales' }}</dd>
                    </div>
                    <div class="rounded-lg border p-4">
                        <dt class="text-sm text-muted-foreground">Sales this month</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ formatMoney(sales.month) }}</dd>
                        <dd class="text-xs text-muted-foreground">{{ sales.month_count }} {{ sales.month_count === 1 ? 'sale' : 'sales' }}</dd>
                    </div>
                </template>
                <template v-if="finance">
                    <div class="rounded-lg border p-4">
                        <dt class="text-sm text-muted-foreground">Expenses this month</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ formatMoney(finance.expenses_month) }}</dd>
                        <dd class="text-xs text-muted-foreground">approved only</dd>
                    </div>
                    <div class="rounded-lg border p-4">
                        <dt class="text-sm text-muted-foreground">Estimated net profit</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums" :class="finance.profit_month.amount < 0 ? 'text-red-600' : ''">
                            {{ formatMoney(finance.profit_month) }}
                        </dd>
                        <dd class="text-xs text-muted-foreground">gross {{ formatMoney(finance.gross_profit_month) }} − expenses</dd>
                    </div>
                </template>
            </dl>

            <div class="grid gap-6 xl:grid-cols-3">
                <!-- Sales chart -->
                <section v-if="sales" class="rounded-lg border p-4 xl:col-span-2">
                    <div class="mb-3 flex items-baseline justify-between">
                        <h2 class="font-medium">Daily sales · last 30 days</h2>
                        <Link :href="route('reports.show', 'sales-by-period')" class="text-xs text-muted-foreground hover:text-foreground"
                            >Full report</Link
                        >
                    </div>
                    <DailySalesChart :points="sales.chart" :currency="currency" :decimals="decimals" />
                </section>

                <!-- Receivables / payables -->
                <section v-if="finance" class="grid content-start gap-3">
                    <Link :href="route('reports.show', 'receivables-aging')" class="group rounded-lg border p-4 hover:bg-muted/30">
                        <p class="text-sm text-muted-foreground">Customers owe you</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums">{{ formatMoney(finance.receivable) }}</p>
                        <p v-if="finance.receivable_overdue" class="mt-1 flex items-center gap-1 text-xs font-medium text-red-600">
                            <AlertTriangle class="h-3.5 w-3.5" /> {{ finance.receivable_overdue }} overdue
                            {{ finance.receivable_overdue === 1 ? 'invoice' : 'invoices' }}
                        </p>
                        <p v-else class="mt-1 text-xs text-muted-foreground">Nothing overdue</p>
                    </Link>
                    <Link :href="route('reports.show', 'payables-aging')" class="group rounded-lg border p-4 hover:bg-muted/30">
                        <p class="text-sm text-muted-foreground">You owe suppliers</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums">{{ formatMoney(finance.payable) }}</p>
                        <p v-if="finance.payable_overdue" class="mt-1 flex items-center gap-1 text-xs font-medium text-red-600">
                            <AlertTriangle class="h-3.5 w-3.5" /> {{ finance.payable_overdue }} overdue
                            {{ finance.payable_overdue === 1 ? 'bill' : 'bills' }}
                        </p>
                        <p v-else class="mt-1 text-xs text-muted-foreground">Nothing overdue</p>
                    </Link>
                </section>

                <!-- Top products -->
                <section v-if="sales" class="rounded-lg border">
                    <h2 class="border-b px-4 py-3 font-medium">Top products this month</h2>
                    <ol class="divide-y text-sm">
                        <li v-for="(product, index) in sales.top_products" :key="product.product" class="flex items-center gap-3 px-4 py-2.5">
                            <span class="w-4 text-xs tabular-nums text-muted-foreground">{{ index + 1 }}</span>
                            <span class="min-w-0 flex-1 truncate">{{ product.product }}</span>
                            <span class="text-xs tabular-nums text-muted-foreground">{{ product.quantity }} u.</span>
                            <span class="w-24 text-right tabular-nums">{{ formatMoney(product.revenue) }}</span>
                        </li>
                        <li v-if="sales.top_products.length === 0" class="px-4 py-6 text-center text-muted-foreground">No sales this month yet.</li>
                    </ol>
                </section>

                <!-- Low stock -->
                <section v-if="inventory" class="rounded-lg border">
                    <div class="flex items-center justify-between border-b px-4 py-3">
                        <h2 class="font-medium">Low stock</h2>
                        <span
                            v-if="inventory.low_stock_count"
                            class="rounded bg-amber-500/15 px-1.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300"
                        >
                            {{ inventory.low_stock_count }} items
                        </span>
                    </div>
                    <ul class="divide-y text-sm">
                        <li v-for="item in inventory.low_stock" :key="item.sku" class="flex items-center gap-3 px-4 py-2.5">
                            <PackageX class="h-4 w-4 shrink-0" :class="item.on_hand <= 0 ? 'text-red-600' : 'text-amber-600'" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate">{{ item.product }}</span>
                                <span class="font-mono text-xs text-muted-foreground">{{ item.sku }}</span>
                            </span>
                            <span class="text-right text-xs tabular-nums">
                                <span class="font-medium">{{ item.on_hand }}</span>
                                <span class="text-muted-foreground"> / {{ item.min_stock }}</span>
                            </span>
                        </li>
                        <li v-if="inventory.low_stock.length === 0" class="px-4 py-6 text-center text-muted-foreground">
                            All products above their minimum.
                        </li>
                    </ul>
                    <Link
                        v-if="inventory.low_stock_count > inventory.low_stock.length"
                        :href="route('inventory.stock.index', { stock: 'low' })"
                        class="flex items-center justify-center gap-1 border-t px-4 py-2 text-xs text-muted-foreground hover:text-foreground"
                    >
                        See all <ArrowRight class="h-3 w-3" />
                    </Link>
                </section>

                <!-- Recent sales -->
                <section v-if="sales" class="rounded-lg border">
                    <h2 class="border-b px-4 py-3 font-medium">Latest sales</h2>
                    <ul class="divide-y text-sm">
                        <li v-for="sale in sales.recent" :key="sale.id" class="flex items-center gap-3 px-4 py-2.5">
                            <Link :href="route('sales.orders.show', sale.id)" class="min-w-0 flex-1 hover:underline">
                                <span class="block truncate">{{ sale.customer }}</span>
                                <span class="font-mono text-xs text-muted-foreground">{{ sale.number }}</span>
                            </Link>
                            <StatusBadge :status="sale.status" :label="sale.status_label" />
                            <span class="w-24 text-right tabular-nums">{{ formatMoney(sale.total) }}</span>
                        </li>
                        <li v-if="sales.recent.length === 0" class="px-4 py-6 text-center text-muted-foreground">No sales yet.</li>
                    </ul>
                </section>

                <!-- Recent purchases -->
                <section v-if="purchases" class="rounded-lg border">
                    <h2 class="border-b px-4 py-3 font-medium">Latest purchase orders</h2>
                    <ul class="divide-y text-sm">
                        <li v-for="order in purchases.recent" :key="order.id" class="flex items-center gap-3 px-4 py-2.5">
                            <Link :href="route('purchasing.orders.show', order.id)" class="min-w-0 flex-1 hover:underline">
                                <span class="block truncate">{{ order.supplier }}</span>
                                <span class="font-mono text-xs text-muted-foreground">{{ order.number }}</span>
                            </Link>
                            <StatusBadge :status="order.status" :label="order.status_label" />
                            <span class="w-24 text-right tabular-nums">{{ formatMoney(order.total) }}</span>
                        </li>
                        <li v-if="purchases.recent.length === 0" class="px-4 py-6 text-center text-muted-foreground">No purchase orders yet.</li>
                    </ul>
                </section>
            </div>

            <p v-if="nothingVisible" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                Your role does not include any dashboard figures. Use the menu to reach the modules you work with.
            </p>
        </div>
    </AppLayout>
</template>

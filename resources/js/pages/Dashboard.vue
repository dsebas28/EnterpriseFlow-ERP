<script setup lang="ts">
import DailySalesChart from '@/components/charts/DailySalesChart.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatMoney, type MoneyValue } from '@/composables/useMoney';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    ArrowUpRight,
    HandCoins,
    PackageX,
    PiggyBank,
    Plus,
    ShoppingCart,
    TrendingUp,
    Truck,
    Wallet,
} from 'lucide-vue-next';
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

const { can } = usePermissions();
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);
const greeting = computed(() => {
    const hour = new Date().getHours();
    return hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
});

const card = 'rounded-xl border bg-card shadow-[0_1px_2px_rgb(30_27_75/0.04)]';

// Last 30 days as a sparkline for the featured tile (the full chart is below).
const sparkline = computed(() => {
    const points = props.sales?.chart ?? [];
    if (points.length < 2) return null;

    const width = 132;
    const height = 40;
    const max = Math.max(...points.map((p) => p.total), 1);
    const coords = points.map((p, i) => `${((i / (points.length - 1)) * width).toFixed(1)},${(height - (p.total / max) * height).toFixed(1)}`);
    const line = `M${coords.join(' L')}`;

    return { width, height, line, area: `${line} L${width},${height} L0,${height} Z` };
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6 lg:p-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-display text-[1.9rem] font-semibold leading-tight tracking-tight">{{ greeting }}, {{ firstName }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ page.props.tenant?.current?.name }} in {{ monthName }}, computed from recorded transactions. Updated
                        {{ new Date(generatedAt).toLocaleTimeString(undefined, { timeStyle: 'short' }) }}.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="can('purchases.create')" variant="outline" as-child>
                        <Link :href="route('purchasing.orders.create')"><Plus class="size-4" /> Purchase order</Link>
                    </Button>
                    <Button v-if="can('sales.create')" as-child>
                        <Link :href="route('sales.orders.create')"><Plus class="size-4" /> New sale</Link>
                    </Button>
                </div>
            </div>

            <!-- Headline figures: sales this month leads, on the brand indigo. -->
            <dl v-if="sales || finance" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <template v-if="sales">
                    <div
                        class="relative overflow-hidden rounded-xl bg-[#312e81] p-5 text-white shadow-[0_12px_32px_-12px_rgb(49_46_129/0.7)] dark:bg-[#3730a3]"
                    >
                        <dt class="relative flex items-center gap-2 text-sm text-indigo-100">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-white/15"><TrendingUp class="size-4" /></span>
                            <span class="whitespace-nowrap">Sales this month</span>
                            <svg
                                v-if="sparkline"
                                :viewBox="`0 0 ${sparkline.width} ${sparkline.height}`"
                                preserveAspectRatio="none"
                                class="ml-auto h-8 w-16 min-w-0 shrink overflow-visible sm:w-20"
                                aria-hidden="true"
                            >
                                <path :d="sparkline.area" fill="#ffffff" fill-opacity="0.1" />
                                <path
                                    :d="sparkline.line"
                                    fill="none"
                                    stroke="#f59e0b"
                                    stroke-width="1.75"
                                    stroke-linejoin="round"
                                    vector-effect="non-scaling-stroke"
                                />
                            </svg>
                        </dt>
                        <dd class="relative mt-4 font-display text-3xl font-semibold tabular-nums tracking-tight">{{ formatMoney(sales.month) }}</dd>
                        <dd class="relative mt-1 text-sm text-indigo-200">
                            {{ sales.month_count }} {{ sales.month_count === 1 ? 'sale' : 'sales' }} confirmed
                        </dd>
                    </div>
                    <div class="p-5" :class="card">
                        <dt class="flex items-center gap-2 text-sm text-muted-foreground">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                ><ShoppingCart class="size-4"
                            /></span>
                            Sales today
                        </dt>
                        <dd class="mt-4 font-display text-3xl font-semibold tabular-nums tracking-tight">{{ formatMoney(sales.today) }}</dd>
                        <dd class="mt-1 text-sm text-muted-foreground">{{ sales.today_count }} {{ sales.today_count === 1 ? 'sale' : 'sales' }}</dd>
                    </div>
                </template>
                <template v-if="finance">
                    <div class="p-5" :class="card">
                        <dt class="flex items-center gap-2 text-sm text-muted-foreground">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-amber-500/15 text-amber-700 dark:text-amber-300"
                                ><Wallet class="size-4"
                            /></span>
                            Expenses this month
                        </dt>
                        <dd class="mt-4 font-display text-3xl font-semibold tabular-nums tracking-tight">
                            {{ formatMoney(finance.expenses_month) }}
                        </dd>
                        <dd class="mt-1 text-sm text-muted-foreground">Approved expenses only</dd>
                    </div>
                    <div class="p-5" :class="card">
                        <dt class="flex items-center gap-2 text-sm text-muted-foreground">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-700 dark:text-emerald-300"
                                ><PiggyBank class="size-4"
                            /></span>
                            Estimated net profit
                        </dt>
                        <dd
                            class="mt-4 font-display text-3xl font-semibold tabular-nums tracking-tight"
                            :class="finance.profit_month.amount < 0 ? 'text-red-600' : ''"
                        >
                            {{ formatMoney(finance.profit_month) }}
                        </dd>
                        <dd class="mt-1 text-sm text-muted-foreground">Gross {{ formatMoney(finance.gross_profit_month) }} minus expenses</dd>
                    </div>
                </template>
            </dl>

            <div class="grid gap-6 xl:grid-cols-3">
                <!-- Sales chart -->
                <section v-if="sales" class="p-5 xl:col-span-2" :class="card">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                            <h2 class="font-medium">Daily sales</h2>
                            <p class="text-sm text-muted-foreground">Last 30 days</p>
                        </div>
                        <Link
                            :href="route('reports.show', 'sales-by-period')"
                            class="flex items-center gap-1 rounded-md text-sm font-medium text-primary hover:underline"
                        >
                            Full report <ArrowUpRight class="size-4" />
                        </Link>
                    </div>
                    <DailySalesChart :points="sales.chart" :currency="currency" :decimals="decimals" />
                </section>

                <!-- Receivables / payables -->
                <section v-if="finance" class="grid content-start gap-4">
                    <Link
                        :href="route('reports.show', 'receivables-aging')"
                        class="group p-5 transition-colors hover:border-primary/40"
                        :class="card"
                    >
                        <p class="flex items-center gap-2 text-sm text-muted-foreground">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                ><HandCoins class="size-4"
                            /></span>
                            Customers owe you
                        </p>
                        <p class="mt-3 font-display text-2xl font-semibold tabular-nums tracking-tight">{{ formatMoney(finance.receivable) }}</p>
                        <p v-if="finance.receivable_overdue" class="mt-1 flex items-center gap-1 text-sm font-medium text-red-600 dark:text-red-400">
                            <AlertTriangle class="h-3.5 w-3.5" /> {{ finance.receivable_overdue }} overdue
                            {{ finance.receivable_overdue === 1 ? 'invoice' : 'invoices' }}
                        </p>
                        <p v-else class="mt-1 text-sm text-muted-foreground">Nothing overdue</p>
                    </Link>
                    <Link :href="route('reports.show', 'payables-aging')" class="group p-5 transition-colors hover:border-primary/40" :class="card">
                        <p class="flex items-center gap-2 text-sm text-muted-foreground">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-amber-500/15 text-amber-700 dark:text-amber-300"
                                ><Truck class="size-4"
                            /></span>
                            You owe suppliers
                        </p>
                        <p class="mt-3 font-display text-2xl font-semibold tabular-nums tracking-tight">{{ formatMoney(finance.payable) }}</p>
                        <p v-if="finance.payable_overdue" class="mt-1 flex items-center gap-1 text-sm font-medium text-red-600 dark:text-red-400">
                            <AlertTriangle class="h-3.5 w-3.5" /> {{ finance.payable_overdue }} overdue
                            {{ finance.payable_overdue === 1 ? 'bill' : 'bills' }}
                        </p>
                        <p v-else class="mt-1 text-sm text-muted-foreground">Nothing overdue</p>
                    </Link>
                </section>

                <!-- Top products -->
                <section v-if="sales" :class="card">
                    <h2 class="border-b px-5 py-4 font-medium">Top products this month</h2>
                    <ol class="divide-y text-sm">
                        <li v-for="(product, index) in sales.top_products" :key="product.product" class="flex items-center gap-3 px-5 py-3">
                            <span class="w-4 text-xs tabular-nums text-muted-foreground">{{ index + 1 }}</span>
                            <span class="min-w-0 flex-1 truncate">{{ product.product }}</span>
                            <span class="text-xs tabular-nums text-muted-foreground">{{ product.quantity }} u.</span>
                            <span class="w-24 text-right tabular-nums">{{ formatMoney(product.revenue) }}</span>
                        </li>
                        <li v-if="sales.top_products.length === 0" class="px-4 py-6 text-center text-muted-foreground">No sales this month yet.</li>
                    </ol>
                </section>

                <!-- Low stock -->
                <section v-if="inventory" :class="card">
                    <div class="flex items-center justify-between border-b px-5 py-4">
                        <h2 class="font-medium">Low stock</h2>
                        <span
                            v-if="inventory.low_stock_count"
                            class="rounded bg-amber-500/15 px-1.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300"
                        >
                            {{ inventory.low_stock_count }} items
                        </span>
                    </div>
                    <ul class="divide-y text-sm">
                        <li v-for="item in inventory.low_stock" :key="item.sku" class="flex items-center gap-3 px-5 py-3">
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
                <section v-if="sales" :class="card">
                    <h2 class="border-b px-5 py-4 font-medium">Latest sales</h2>
                    <ul class="divide-y text-sm">
                        <li v-for="sale in sales.recent" :key="sale.id" class="flex items-center gap-3 px-5 py-3">
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
                <section v-if="purchases" :class="card">
                    <h2 class="border-b px-5 py-4 font-medium">Latest purchase orders</h2>
                    <ul class="divide-y text-sm">
                        <li v-for="order in purchases.recent" :key="order.id" class="flex items-center gap-3 px-5 py-3">
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

            <p v-if="nothingVisible" class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                Your role does not include any dashboard figures. Use the menu to reach the modules you work with.
            </p>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney, type MoneyValue } from '@/composables/useMoney';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CategoryNode, Paginated, Warehouse } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ArrowLeftRight, Boxes, ClipboardCheck, History, PackageMinus, PackagePlus, Search } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface StockItem {
    id: string;
    sku: string;
    name: string;
    category: string | null;
    min_stock: number;
    on_hand: number;
    status: 'ok' | 'low' | 'out';
    cost: MoneyValue;
    value: MoneyValue;
    levels: Record<string, number>;
}

type Operation = 'adjust' | 'manual' | 'transfer';

const props = defineProps<{
    items: Paginated<StockItem>;
    summary: { items: number; units: number; value: MoneyValue; low: number; out: number };
    warehouses: { data: Warehouse[] };
    categories: CategoryNode[];
    filters: { search?: string; warehouse_id?: string; category_id?: string; stock?: string; sort?: string };
    can: { adjust: boolean; transfer: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventory', href: '/inventory/stock' },
    { title: 'Stock', href: '/inventory/stock' },
];

const warehouseName = computed(() => Object.fromEntries(props.warehouses.data.map((w) => [w.id, w.name])));
const activeWarehouses = computed(() => props.warehouses.data.filter((w) => w.is_active));
const defaultWarehouseId = computed(() => props.warehouses.data.find((w) => w.is_default)?.id ?? activeWarehouses.value[0]?.id ?? '');

// Filters
const filters = reactive({
    search: props.filters.search ?? '',
    warehouse_id: props.filters.warehouse_id ?? '',
    category_id: props.filters.category_id ?? '',
    stock: props.filters.stock ?? '',
    sort: props.filters.sort ?? '',
});
const apply = () => {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
    router.get(route('inventory.stock.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};
watch(() => filters.search, useDebounceFn(apply, 300));
watch(() => [filters.warehouse_id, filters.category_id, filters.stock, filters.sort], apply);

// Operations dialog
const operation = ref<Operation | null>(null);
const target = ref<StockItem | null>(null);

const adjustForm = useForm({ product_id: '', warehouse_id: '', counted_quantity: 0, reason: '' });
const manualForm = useForm({ product_id: '', warehouse_id: '', type: 'manual_in', quantity: 1, unit_cost: '', notes: '' });
const transferForm = useForm({ product_id: '', from_warehouse_id: '', to_warehouse_id: '', quantity: 1, notes: '' });

const open = (item: StockItem, op: Operation) => {
    target.value = item;
    operation.value = op;
    const warehouseId = filters.warehouse_id || defaultWarehouseId.value;

    adjustForm.reset();
    adjustForm.clearErrors();
    adjustForm.product_id = item.id;
    adjustForm.warehouse_id = warehouseId;
    adjustForm.counted_quantity = item.levels[warehouseId] ?? 0;

    manualForm.reset();
    manualForm.clearErrors();
    manualForm.product_id = item.id;
    manualForm.warehouse_id = warehouseId;

    transferForm.reset();
    transferForm.clearErrors();
    transferForm.product_id = item.id;
    transferForm.from_warehouse_id = warehouseId;
    transferForm.to_warehouse_id = activeWarehouses.value.find((w) => w.id !== warehouseId)?.id ?? '';
};

const close = () => (operation.value = null);
const options = { preserveScroll: true, onSuccess: close };
const submit = () => {
    if (operation.value === 'adjust') adjustForm.post(route('inventory.adjustments.store'), options);
    if (operation.value === 'manual') manualForm.post(route('inventory.manual-movements.store'), options);
    if (operation.value === 'transfer') transferForm.post(route('inventory.transfers.store'), options);
};

const currentInAdjustWarehouse = computed(() => (target.value ? (target.value.levels[adjustForm.warehouse_id] ?? 0) : 0));
const adjustDifference = computed(() => Number(adjustForm.counted_quantity) - currentInAdjustWarehouse.value);
const processing = computed(() => adjustForm.processing || manualForm.processing || transferForm.processing);

const titles: Record<Operation, string> = {
    adjust: 'Adjust to physical count',
    manual: 'Manual entry / exit',
    transfer: 'Transfer between warehouses',
};

const statusStyle: Record<StockItem['status'], string> = {
    ok: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    low: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    out: 'bg-red-500/10 text-red-700 dark:text-red-300',
};
const statusLabel: Record<StockItem['status'], string> = { ok: 'In stock', low: 'Low', out: 'Out of stock' };

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Stock" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <HeadingSmall title="Stock" description="Quantities on hand, derived from every recorded stock movement." />
                <Button variant="outline" as-child>
                    <Link :href="route('inventory.movements.index')"><History class="mr-2 h-4 w-4" /> Movement history</Link>
                </Button>
            </div>

            <PageAlerts />

            <dl class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <div class="rounded-lg border p-4">
                    <dt class="text-xs uppercase tracking-wide text-muted-foreground">Items</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ summary.items }}</dd>
                </div>
                <div class="rounded-lg border p-4">
                    <dt class="text-xs uppercase tracking-wide text-muted-foreground">Units on hand</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ summary.units.toLocaleString() }}</dd>
                </div>
                <div class="col-span-2 rounded-lg border p-4 lg:col-span-1">
                    <dt class="text-xs uppercase tracking-wide text-muted-foreground">Value at cost</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ formatMoney(summary.value) }}</dd>
                </div>
                <button
                    type="button"
                    class="rounded-lg border p-4 text-left hover:bg-muted/40"
                    @click="filters.stock = filters.stock === 'low' ? '' : 'low'"
                >
                    <dt class="text-xs uppercase tracking-wide text-amber-700 dark:text-amber-300">Low stock</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ summary.low }}</dd>
                </button>
                <button
                    type="button"
                    class="rounded-lg border p-4 text-left hover:bg-muted/40"
                    @click="filters.stock = filters.stock === 'out' ? '' : 'out'"
                >
                    <dt class="text-xs uppercase tracking-wide text-red-700 dark:text-red-300">Out of stock</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ summary.out }}</dd>
                </button>
            </dl>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Search name, SKU or barcode" class="pl-9" aria-label="Search stock" />
                </div>
                <select v-model="filters.warehouse_id" :class="selectClass" aria-label="Warehouse">
                    <option value="">All warehouses</option>
                    <option v-for="warehouse in warehouses.data" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
                </select>
                <select v-model="filters.category_id" :class="selectClass" aria-label="Category">
                    <option value="">All categories</option>
                    <option v-for="category in categories" :key="category.id" :value="String(category.id)">
                        {{ '  '.repeat(category.depth) }}{{ category.name }}
                    </option>
                </select>
                <select v-model="filters.stock" :class="selectClass" aria-label="Stock status">
                    <option value="">Any stock level</option>
                    <option value="in">In stock</option>
                    <option value="low">Low stock</option>
                    <option value="out">Out of stock</option>
                </select>
                <select v-model="filters.sort" :class="selectClass" aria-label="Sort">
                    <option value="">Name</option>
                    <option value="sku">SKU</option>
                    <option value="on_hand">Quantity: low to high</option>
                    <option value="-on_hand">Quantity: high to low</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Item</th>
                            <th class="hidden px-4 py-3 font-medium lg:table-cell">By warehouse</th>
                            <th class="px-4 py-3 text-right font-medium">On hand</th>
                            <th class="hidden px-4 py-3 text-right font-medium sm:table-cell">Min.</th>
                            <th class="hidden px-4 py-3 text-right font-medium md:table-cell">Value</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="item in items.data" :key="item.id">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ item.name }}</p>
                                <p class="font-mono text-xs text-muted-foreground">
                                    {{ item.sku }}<template v-if="item.category"> · {{ item.category }}</template>
                                </p>
                            </td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    <template v-for="(quantity, warehouseId) in item.levels" :key="warehouseId">
                                        <span v-if="quantity !== 0" class="rounded bg-secondary px-1.5 py-0.5 text-xs tabular-nums">
                                            {{ warehouseName[warehouseId] ?? '—' }}: {{ quantity }}
                                        </span>
                                    </template>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="mr-2 inline-block rounded px-1.5 py-0.5 text-[11px] font-medium" :class="statusStyle[item.status]">
                                    {{ statusLabel[item.status] }}
                                </span>
                                <span class="font-semibold tabular-nums">{{ item.on_hand }}</span>
                            </td>
                            <td class="hidden px-4 py-3 text-right tabular-nums text-muted-foreground sm:table-cell">{{ item.min_stock }}</td>
                            <td class="hidden px-4 py-3 text-right tabular-nums md:table-cell">{{ formatMoney(item.value) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button v-if="can.adjust" variant="ghost" size="icon" title="Adjust to count" @click="open(item, 'adjust')">
                                        <ClipboardCheck class="h-4 w-4" />
                                    </Button>
                                    <Button v-if="can.adjust" variant="ghost" size="icon" title="Manual entry / exit" @click="open(item, 'manual')">
                                        <PackagePlus class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        v-if="can.transfer && activeWarehouses.length > 1"
                                        variant="ghost"
                                        size="icon"
                                        title="Transfer"
                                        @click="open(item, 'transfer')"
                                    >
                                        <ArrowLeftRight class="h-4 w-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon" title="History" as-child>
                                        <Link :href="route('inventory.movements.index', { product_id: item.id })"><History class="h-4 w-4" /></Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="items.data.length === 0">
                            <td colspan="6" class="px-4 py-16 text-center">
                                <Boxes class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                                <p class="font-medium">No items match these filters</p>
                                <p class="mt-1 text-sm text-muted-foreground">Stock appears here once products exist in the catalogue.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="items.meta" />
        </div>

        <Dialog :open="operation !== null" @update:open="(value) => !value && close()">
            <DialogContent v-if="operation && target">
                <form class="space-y-5" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{ titles[operation] }}</DialogTitle>
                        <DialogDescription>{{ target.name }} · {{ target.sku }}</DialogDescription>
                    </DialogHeader>

                    <!-- Adjust -->
                    <template v-if="operation === 'adjust'">
                        <div class="grid gap-2">
                            <Label for="adj-warehouse">Warehouse</Label>
                            <select id="adj-warehouse" v-model="adjustForm.warehouse_id" :class="selectClass">
                                <option v-for="w in activeWarehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                            <InputError :message="adjustForm.errors.warehouse_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="adj-count">Counted quantity</Label>
                            <Input id="adj-count" v-model.number="adjustForm.counted_quantity" type="number" min="0" step="1" required />
                            <p class="text-xs text-muted-foreground">
                                System: {{ currentInAdjustWarehouse }} ·
                                <span :class="adjustDifference < 0 ? 'text-red-600' : adjustDifference > 0 ? 'text-emerald-600' : ''">
                                    difference {{ adjustDifference > 0 ? '+' : '' }}{{ adjustDifference }}
                                </span>
                            </p>
                            <InputError :message="adjustForm.errors.counted_quantity" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="adj-reason">Reason</Label>
                            <Input id="adj-reason" v-model="adjustForm.reason" required placeholder="Monthly cycle count" />
                            <InputError :message="adjustForm.errors.reason" />
                        </div>
                    </template>

                    <!-- Manual -->
                    <template v-if="operation === 'manual'">
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                v-for="option in [
                                    { value: 'manual_in', label: 'Entry', icon: PackagePlus },
                                    { value: 'manual_out', label: 'Exit', icon: PackageMinus },
                                ]"
                                :key="option.value"
                                class="flex cursor-pointer items-center gap-2 rounded-md border p-3 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                            >
                                <input v-model="manualForm.type" type="radio" :value="option.value" class="sr-only" />
                                <component :is="option.icon" class="h-4 w-4" /> {{ option.label }}
                            </label>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="man-warehouse">Warehouse</Label>
                                <select id="man-warehouse" v-model="manualForm.warehouse_id" :class="selectClass">
                                    <option v-for="w in activeWarehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                                </select>
                                <InputError :message="manualForm.errors.warehouse_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="man-qty">Quantity</Label>
                                <Input id="man-qty" v-model.number="manualForm.quantity" type="number" min="1" step="1" required />
                                <InputError :message="manualForm.errors.quantity" />
                            </div>
                        </div>
                        <div v-if="manualForm.type === 'manual_in'" class="grid gap-2">
                            <Label for="man-cost">Unit cost <span class="text-muted-foreground">(optional)</span></Label>
                            <Input id="man-cost" v-model="manualForm.unit_cost" type="number" min="0" step="any" :placeholder="target.cost.decimal" />
                            <InputError :message="manualForm.errors.unit_cost" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="man-notes">Notes</Label>
                            <Input
                                id="man-notes"
                                v-model="manualForm.notes"
                                required
                                :placeholder="manualForm.type === 'manual_in' ? 'Opening balance' : 'Damaged in handling'"
                            />
                            <InputError :message="manualForm.errors.notes" />
                        </div>
                    </template>

                    <!-- Transfer -->
                    <template v-if="operation === 'transfer'">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="tr-from">From</Label>
                                <select id="tr-from" v-model="transferForm.from_warehouse_id" :class="selectClass">
                                    <option v-for="w in activeWarehouses" :key="w.id" :value="w.id">
                                        {{ w.name }} ({{ target.levels[w.id] ?? 0 }})
                                    </option>
                                </select>
                                <InputError :message="transferForm.errors.from_warehouse_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tr-to">To</Label>
                                <select id="tr-to" v-model="transferForm.to_warehouse_id" :class="selectClass">
                                    <option
                                        v-for="w in activeWarehouses"
                                        :key="w.id"
                                        :value="w.id"
                                        :disabled="w.id === transferForm.from_warehouse_id"
                                    >
                                        {{ w.name }}
                                    </option>
                                </select>
                                <InputError :message="transferForm.errors.to_warehouse_id" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="tr-qty">Quantity</Label>
                            <Input id="tr-qty" v-model.number="transferForm.quantity" type="number" min="1" step="1" required />
                            <InputError :message="transferForm.errors.quantity" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tr-notes">Notes <span class="text-muted-foreground">(optional)</span></Label>
                            <Input id="tr-notes" v-model="transferForm.notes" />
                        </div>
                    </template>

                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="close">Cancel</Button>
                        <Button type="submit" :disabled="processing">Record</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

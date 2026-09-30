<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import ProductPicker, { type PickedProduct } from '@/components/ProductPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Warehouse } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, PackageOpen, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

type Line = {
    product_id: string;
    sku: string;
    name: string;
    quantity: number;
    unit_cost: string;
    tax_rate: string;
};

interface ExistingOrder {
    id: string;
    number: string;
    supplier: { id: string; name: string };
    warehouse: { id: string; name: string };
    order_date: string;
    expected_date: string | null;
    notes: string | null;
    items: { product_id: string; sku: string; description: string; quantity: number; unit_cost: { decimal: string }; tax_rate: string }[];
}

const props = defineProps<{
    order: { data: ExistingOrder } | null;
    suppliers: { id: string; name: string }[];
    warehouses: { data: Warehouse[] };
    currency: { code: string; decimals: number };
    today: string;
}>();

const existing = computed(() => props.order?.data ?? null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Purchasing', href: '/purchasing/orders' },
    { title: 'Purchase orders', href: '/purchasing/orders' },
    { title: existing.value ? `Edit ${existing.value.number}` : 'New order', href: '#' },
]);

const form = useForm<{
    supplier_id: string;
    warehouse_id: string;
    order_date: string;
    expected_date: string;
    notes: string;
    lines: Line[];
}>({
    supplier_id: existing.value?.supplier.id ?? '',
    warehouse_id: existing.value?.warehouse.id ?? props.warehouses.data[0]?.id ?? '',
    order_date: existing.value?.order_date ?? props.today,
    expected_date: existing.value?.expected_date ?? '',
    notes: existing.value?.notes ?? '',
    lines:
        existing.value?.items.map((item) => ({
            product_id: item.product_id,
            sku: item.sku,
            name: item.description,
            quantity: item.quantity,
            unit_cost: item.unit_cost.decimal,
            tax_rate: String(Number(item.tax_rate)),
        })) ?? [],
});

const addLine = (product: PickedProduct) => {
    form.lines.push({
        product_id: product.id,
        sku: product.sku,
        name: product.name,
        quantity: 1,
        unit_cost: product.cost,
        tax_rate: String(Number(product.tax_rate)),
    });
};

// Preview only, mirroring the server's integer arithmetic. The saved
// document always uses the totals computed by the backend.
const factor = computed(() => 10 ** props.currency.decimals);
const toMinor = (decimal: string) => Math.round(Number(decimal || 0) * factor.value);
const lineAmounts = (line: Line) => {
    const subtotal = toMinor(line.unit_cost) * Math.max(0, Number(line.quantity) || 0);
    const tax = Math.round((subtotal * Math.round(Number(line.tax_rate || 0) * 100)) / 10000);
    return { subtotal, tax, total: subtotal + tax };
};
const totals = computed(() =>
    form.lines.reduce(
        (acc, line) => {
            const amounts = lineAmounts(line);
            return { subtotal: acc.subtotal + amounts.subtotal, tax: acc.tax + amounts.tax, total: acc.total + amounts.total };
        },
        { subtotal: 0, tax: 0, total: 0 },
    ),
);
const money = (minor: number) => new Intl.NumberFormat(undefined, { style: 'currency', currency: props.currency.code }).format(minor / factor.value);

const lineError = (index: number, field: string) => (form.errors as Record<string, string>)[`lines.${index}.${field}`];

const submit = () => {
    const payload = (data: typeof form.data extends () => infer R ? R : never) => ({
        ...data,
        lines: data.lines.map(({ product_id, quantity, unit_cost, tax_rate }) => ({ product_id, quantity, unit_cost, tax_rate })),
    });

    if (existing.value) {
        form.transform(payload).put(route('purchasing.orders.update', existing.value.id));
    } else {
        form.transform(payload).post(route('purchasing.orders.store'));
    }
};

const step = computed(() => (props.currency.decimals === 0 ? '1' : (1 / factor.value).toFixed(props.currency.decimals)));
const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="existing ? `Edit ${existing.number}` : 'New purchase order'" />

        <form class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6" @submit.prevent="submit">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="existing ? route('purchasing.orders.show', existing.id) : route('purchasing.orders.index')" aria-label="Back">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <HeadingSmall
                    :title="existing ? `Edit ${existing.number}` : 'New purchase order'"
                    description="Saved as a draft. Submit it for approval when it is ready."
                />
            </div>

            <PageAlerts />

            <section class="grid gap-4 rounded-lg border p-5 md:grid-cols-4">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="supplier">Supplier</Label>
                    <select id="supplier" v-model="form.supplier_id" :class="selectClass" required>
                        <option value="" disabled>Select a supplier</option>
                        <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                    </select>
                    <InputError :message="form.errors.supplier_id" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="warehouse">Deliver to</Label>
                    <select id="warehouse" v-model="form.warehouse_id" :class="selectClass" required>
                        <option v-for="warehouse in warehouses.data" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
                    </select>
                    <InputError :message="form.errors.warehouse_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="order_date">Order date</Label>
                    <Input id="order_date" v-model="form.order_date" type="date" required />
                    <InputError :message="form.errors.order_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="expected_date">Expected delivery</Label>
                    <Input id="expected_date" v-model="form.expected_date" type="date" :min="form.order_date" />
                    <InputError :message="form.errors.expected_date" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="notes">Notes</Label>
                    <Input id="notes" v-model="form.notes" placeholder="Visible on the order" />
                </div>
            </section>

            <section class="space-y-4 rounded-lg border p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-medium">Lines</h3>
                    <ProductPicker class="w-full sm:w-96" :exclude-ids="form.lines.map((l) => l.product_id)" @pick="addLine" />
                </div>
                <InputError :message="form.errors.lines" />

                <div v-if="form.lines.length" class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="py-2 pr-3 font-medium">Product</th>
                                <th class="w-24 px-2 py-2 font-medium">Qty</th>
                                <th class="w-36 px-2 py-2 font-medium">Unit cost</th>
                                <th class="w-24 px-2 py-2 font-medium">Tax %</th>
                                <th class="w-32 px-2 py-2 text-right font-medium">Total</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(line, index) in form.lines" :key="line.product_id" class="align-top">
                                <td class="py-2 pr-3">
                                    <p class="font-medium">{{ line.name }}</p>
                                    <p class="font-mono text-xs text-muted-foreground">{{ line.sku }}</p>
                                    <InputError :message="lineError(index, 'product_id')" />
                                </td>
                                <td class="px-2 py-2">
                                    <Input v-model.number="line.quantity" type="number" min="1" step="1" aria-label="Quantity" />
                                    <InputError :message="lineError(index, 'quantity')" />
                                </td>
                                <td class="px-2 py-2">
                                    <Input v-model="line.unit_cost" type="number" min="0" :step="step" aria-label="Unit cost" />
                                    <InputError :message="lineError(index, 'unit_cost')" />
                                </td>
                                <td class="px-2 py-2">
                                    <Input v-model="line.tax_rate" type="number" min="0" max="100" step="0.01" aria-label="Tax rate" />
                                    <InputError :message="lineError(index, 'tax_rate')" />
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">{{ money(lineAmounts(line).total) }}</td>
                                <td class="py-2">
                                    <Button type="button" variant="ghost" size="icon" aria-label="Remove line" @click="form.lines.splice(index, 1)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="rounded-md border border-dashed p-10 text-center text-sm text-muted-foreground">
                    <PackageOpen class="mx-auto mb-2 h-8 w-8" />
                    Search a product above to add the first line.
                </div>

                <dl v-if="form.lines.length" class="ml-auto w-full max-w-xs space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Subtotal</dt>
                        <dd class="tabular-nums">{{ money(totals.subtotal) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Tax</dt>
                        <dd class="tabular-nums">{{ money(totals.tax) }}</dd>
                    </div>
                    <div class="flex justify-between border-t pt-1 font-semibold">
                        <dt>Total</dt>
                        <dd class="tabular-nums">{{ money(totals.total) }}</dd>
                    </div>
                    <p class="pt-1 text-xs text-muted-foreground">Final totals are calculated when the order is saved.</p>
                </dl>
            </section>

            <div class="flex justify-end gap-2">
                <Button variant="secondary" as-child>
                    <Link :href="existing ? route('purchasing.orders.show', existing.id) : route('purchasing.orders.index')">Cancel</Link>
                </Button>
                <Button type="submit" :disabled="form.processing || form.lines.length === 0">{{ existing ? 'Save changes' : 'Save draft' }}</Button>
            </div>
        </form>
    </AppLayout>
</template>

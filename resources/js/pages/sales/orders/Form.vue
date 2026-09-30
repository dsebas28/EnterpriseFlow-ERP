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
import { ArrowLeft, ShoppingCart, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

type Line = {
    product_id: string;
    sku: string;
    name: string;
    quantity: number;
    unit_price: string;
    discount_rate: string;
    tax_rate: string;
};

interface ExistingSale {
    id: string;
    number: string;
    customer: { id: string; name: string };
    warehouse: { id: string; name: string };
    sale_date: string;
    notes: string | null;
    items: {
        product_id: string;
        sku: string;
        description: string;
        quantity: number;
        unit_price: { decimal: string };
        discount_rate: string;
        tax_rate: string;
    }[];
}

const props = defineProps<{
    sale: { data: ExistingSale } | null;
    customers: { id: string; name: string }[];
    warehouses: { data: Warehouse[] };
    currency: { code: string; decimals: number };
    today: string;
    preselectedCustomerId: string | null;
}>();

const existing = computed(() => props.sale?.data ?? null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Sales', href: '/sales/orders' },
    { title: existing.value ? `Edit ${existing.value.number}` : 'New sale', href: '#' },
]);

const form = useForm<{ customer_id: string; warehouse_id: string; sale_date: string; notes: string; lines: Line[] }>({
    customer_id: existing.value?.customer.id ?? props.preselectedCustomerId ?? '',
    warehouse_id: existing.value?.warehouse.id ?? props.warehouses.data[0]?.id ?? '',
    sale_date: existing.value?.sale_date ?? props.today,
    notes: existing.value?.notes ?? '',
    lines:
        existing.value?.items.map((item) => ({
            product_id: item.product_id,
            sku: item.sku,
            name: item.description,
            quantity: item.quantity,
            unit_price: item.unit_price.decimal,
            discount_rate: String(Number(item.discount_rate)),
            tax_rate: String(Number(item.tax_rate)),
        })) ?? [],
});

const addLine = (product: PickedProduct) =>
    form.lines.push({
        product_id: product.id,
        sku: product.sku,
        name: product.name,
        quantity: 1,
        unit_price: product.price,
        discount_rate: '0',
        tax_rate: String(Number(product.tax_rate)),
    });

// Preview mirroring the server's integer arithmetic; the backend's totals are authoritative.
const factor = computed(() => 10 ** props.currency.decimals);
const pct = (value: string, amount: number) => Math.round((amount * Math.round(Number(value || 0) * 100)) / 10000);
const lineAmounts = (line: Line) => {
    const gross = Math.round(Number(line.unit_price || 0) * factor.value) * Math.max(0, Number(line.quantity) || 0);
    const discount = pct(line.discount_rate, gross);
    const subtotal = gross - discount;
    const tax = pct(line.tax_rate, subtotal);
    return { discount, subtotal, tax, total: subtotal + tax };
};
const totals = computed(() =>
    form.lines.reduce(
        (acc, line) => {
            const a = lineAmounts(line);
            return { discount: acc.discount + a.discount, subtotal: acc.subtotal + a.subtotal, tax: acc.tax + a.tax, total: acc.total + a.total };
        },
        { discount: 0, subtotal: 0, tax: 0, total: 0 },
    ),
);
const money = (minor: number) => new Intl.NumberFormat(undefined, { style: 'currency', currency: props.currency.code }).format(minor / factor.value);

const lineError = (index: number, field: string) => (form.errors as Record<string, string>)[`lines.${index}.${field}`];

const submit = () => {
    const payload = (data: typeof form.data extends () => infer R ? R : never) => ({
        ...data,
        lines: data.lines.map(({ product_id, quantity, unit_price, discount_rate, tax_rate }) => ({
            product_id,
            quantity,
            unit_price,
            discount_rate,
            tax_rate,
        })),
    });

    if (existing.value) {
        form.transform(payload).put(route('sales.orders.update', existing.value.id));
    } else {
        form.transform(payload).post(route('sales.orders.store'));
    }
};

const step = computed(() => (props.currency.decimals === 0 ? '1' : (1 / factor.value).toFixed(props.currency.decimals)));
const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="existing ? `Edit ${existing.number}` : 'New sale'" />

        <form class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6" @submit.prevent="submit">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="existing ? route('sales.orders.show', existing.id) : route('sales.orders.index')" aria-label="Back">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <HeadingSmall
                    :title="existing ? `Edit ${existing.number}` : 'New sale'"
                    description="Saved as a draft. Stock is only deducted when the sale is confirmed."
                />
            </div>

            <PageAlerts />

            <section class="grid gap-4 rounded-lg border p-5 md:grid-cols-4">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="customer">Customer</Label>
                    <select id="customer" v-model="form.customer_id" :class="selectClass" required>
                        <option value="" disabled>Select a customer</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}</option>
                    </select>
                    <InputError :message="form.errors.customer_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="warehouse">Ship from</Label>
                    <select id="warehouse" v-model="form.warehouse_id" :class="selectClass" required>
                        <option v-for="warehouse in warehouses.data" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
                    </select>
                    <InputError :message="form.errors.warehouse_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="sale_date">Date</Label>
                    <Input id="sale_date" v-model="form.sale_date" type="date" required />
                    <InputError :message="form.errors.sale_date" />
                </div>
                <div class="grid gap-2 md:col-span-4">
                    <Label for="notes">Notes</Label>
                    <Input id="notes" v-model="form.notes" placeholder="Delivery instructions, reference…" />
                </div>
            </section>

            <section class="space-y-4 rounded-lg border p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-medium">Items</h3>
                    <ProductPicker class="w-full sm:w-96" :exclude-ids="form.lines.map((l) => l.product_id)" @pick="addLine" />
                </div>
                <InputError :message="form.errors.lines" />

                <div v-if="form.lines.length" class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="py-2 pr-3 font-medium">Product</th>
                                <th class="w-24 px-2 py-2 font-medium">Qty</th>
                                <th class="w-36 px-2 py-2 font-medium">Unit price</th>
                                <th class="w-24 px-2 py-2 font-medium">Disc. %</th>
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
                                    <Input v-model="line.unit_price" type="number" min="0" :step="step" aria-label="Unit price" />
                                    <InputError :message="lineError(index, 'unit_price')" />
                                </td>
                                <td class="px-2 py-2">
                                    <Input v-model="line.discount_rate" type="number" min="0" max="100" step="0.01" aria-label="Discount" />
                                    <InputError :message="lineError(index, 'discount_rate')" />
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
                    <ShoppingCart class="mx-auto mb-2 h-8 w-8" />
                    Search a product above to add it to the sale.
                </div>

                <dl v-if="form.lines.length" class="ml-auto w-full max-w-xs space-y-1 text-sm">
                    <div v-if="totals.discount" class="flex justify-between text-muted-foreground">
                        <dt>Discounts</dt>
                        <dd class="tabular-nums">−{{ money(totals.discount) }}</dd>
                    </div>
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
                    <p class="pt-1 text-xs text-muted-foreground">Final totals are calculated when the sale is saved.</p>
                </dl>
            </section>

            <div class="flex justify-end gap-2">
                <Button variant="secondary" as-child>
                    <Link :href="existing ? route('sales.orders.show', existing.id) : route('sales.orders.index')">Cancel</Link>
                </Button>
                <Button type="submit" :disabled="form.processing || form.lines.length === 0">{{ existing ? 'Save changes' : 'Save draft' }}</Button>
            </div>
        </form>
    </AppLayout>
</template>

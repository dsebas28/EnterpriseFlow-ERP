<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney, type MoneyValue } from '@/composables/useMoney';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Warehouse } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Ban, CheckCircle2, PackageCheck, Pencil, ReceiptText, Send, Trash2, Undo2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Item {
    id: number;
    product_id: string;
    sku: string;
    description: string;
    quantity: number;
    received_quantity: number;
    remaining_quantity: number;
    billed_quantity: number;
    billable_quantity: number;
    unit_cost: MoneyValue;
    tax_rate: string;
    line_subtotal: MoneyValue;
    line_tax: MoneyValue;
    line_total: MoneyValue;
}

interface Order {
    id: string;
    number: string;
    status: string;
    status_label: string;
    supplier: { id: string; name: string };
    warehouse: { id: string; name: string };
    order_date: string;
    expected_date: string | null;
    subtotal: MoneyValue;
    tax_total: MoneyValue;
    total: MoneyValue;
    currency: string;
    notes: string | null;
    created_by: string | null;
    approved_by: string | null;
    approved_at: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    received_at: string | null;
    created_at: string;
    items: Item[];
    receipts: {
        id: string;
        number: string;
        warehouse: string;
        received_by: string | null;
        received_at: string;
        units: number;
        notes: string | null;
    }[];
    bills: { id: string; number: string; supplier_reference: string; status: string; status_label: string; total: MoneyValue; due_date: string }[];
}

const props = defineProps<{
    order: { data: Order };
    warehouses: { data: Warehouse[] };
    billDefaults: { bill_date: string; due_date: string };
    can: {
        bill: boolean;
        edit: boolean;
        submit: boolean;
        approve: boolean;
        returnToDraft: boolean;
        receive: boolean;
        cancel: boolean;
        delete: boolean;
    };
}>();

const { can: hasPermission } = usePermissions();

const order = computed(() => props.order.data);

// Register supplier bill (only received, not yet billed quantities)
const billOpen = ref(false);
const billForm = useForm<{
    supplier_reference: string;
    bill_date: string;
    due_date: string;
    notes: string;
    lines: { item_id: number; quantity: number }[];
}>({
    supplier_reference: '',
    bill_date: props.billDefaults.bill_date,
    due_date: props.billDefaults.due_date,
    notes: '',
    lines: [],
});
const openBill = () => {
    billForm.reset();
    billForm.clearErrors();
    billForm.lines = order.value.items
        .filter((item) => item.billable_quantity > 0)
        .map((item) => ({ item_id: item.id, quantity: item.billable_quantity }));
    billOpen.value = true;
};
const registerBill = () => billForm.post(route('purchasing.orders.bills.store', order.value.id), { onSuccess: () => (billOpen.value = false) });

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Purchasing', href: '/purchasing/orders' },
    { title: 'Purchase orders', href: '/purchasing/orders' },
    { title: order.value.number, href: '#' },
]);

const post = (name: string) => router.post(route(name, order.value.id), {}, { preserveScroll: true });

// Cancel
const cancelOpen = ref(false);
const cancelForm = useForm({ reason: '' });
const cancel = () =>
    cancelForm.post(route('purchasing.orders.cancel', order.value.id), { preserveScroll: true, onSuccess: () => (cancelOpen.value = false) });

// Delete
const deleteOpen = ref(false);
const destroy = () => router.delete(route('purchasing.orders.destroy', order.value.id));

// Receive
const receiveOpen = ref(false);
const receiveForm = useForm<{ warehouse_id: string; notes: string; lines: { item_id: number; quantity: number }[] }>({
    warehouse_id: '',
    notes: '',
    lines: [],
});
const openReceive = () => {
    receiveForm.reset();
    receiveForm.clearErrors();
    receiveForm.warehouse_id = order.value.warehouse.id;
    receiveForm.lines = order.value.items
        .filter((item) => item.remaining_quantity > 0)
        .map((item) => ({ item_id: item.id, quantity: item.remaining_quantity }));
    receiveOpen.value = true;
};
const itemById = computed(() => Object.fromEntries(order.value.items.map((item) => [item.id, item])));
const receive = () =>
    receiveForm.post(route('purchasing.orders.receive', order.value.id), { preserveScroll: true, onSuccess: () => (receiveOpen.value = false) });

const totalOrdered = computed(() => order.value.items.reduce((sum, item) => sum + item.quantity, 0));
const totalReceived = computed(() => order.value.items.reduce((sum, item) => sum + item.received_quantity, 0));

const formatDate = (value: string) =>
    new Date(value.length === 10 ? `${value}T00:00:00` : value).toLocaleDateString(undefined, { dateStyle: 'medium' });
const formatDateTime = (value: string) => new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });

const timeline = computed(() =>
    [
        { label: 'Created', at: order.value.created_at, by: order.value.created_by },
        order.value.approved_at ? { label: 'Approved', at: order.value.approved_at, by: order.value.approved_by } : null,
        ...order.value.receipts
            .slice()
            .reverse()
            .map((r) => ({ label: `Received ${r.units} units (${r.number})`, at: r.received_at, by: r.received_by })),
        order.value.cancelled_at ? { label: `Cancelled: ${order.value.cancel_reason}`, at: order.value.cancelled_at, by: null } : null,
    ].filter((event): event is { label: string; at: string; by: string | null } => event !== null),
);

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="order.number" />

        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" as-child>
                        <Link :href="route('purchasing.orders.index')" aria-label="Back to orders"><ArrowLeft class="h-4 w-4" /></Link>
                    </Button>
                    <div>
                        <div class="flex items-center gap-2">
                            <HeadingSmall :title="order.number" />
                            <StatusBadge :status="order.status" :label="order.status_label" />
                        </div>
                        <p class="text-sm text-muted-foreground">{{ order.supplier.name }} · deliver to {{ order.warehouse.name }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="can.edit" variant="outline" as-child>
                        <Link :href="route('purchasing.orders.edit', order.id)"><Pencil class="mr-2 h-4 w-4" /> Edit</Link>
                    </Button>
                    <Button v-if="can.submit" @click="post('purchasing.orders.submit')"><Send class="mr-2 h-4 w-4" /> Submit for approval</Button>
                    <Button v-if="can.returnToDraft" variant="outline" @click="post('purchasing.orders.return-to-draft')">
                        <Undo2 class="mr-2 h-4 w-4" /> Return to draft
                    </Button>
                    <Button v-if="can.approve" @click="post('purchasing.orders.approve')"><CheckCircle2 class="mr-2 h-4 w-4" /> Approve</Button>
                    <Button v-if="can.receive" @click="openReceive"><PackageCheck class="mr-2 h-4 w-4" /> Receive goods</Button>
                    <Button v-if="can.bill" variant="outline" @click="openBill"><ReceiptText class="mr-2 h-4 w-4" /> Register supplier bill</Button>
                    <Button v-if="can.cancel" variant="outline" class="text-red-600" @click="cancelOpen = true"
                        ><Ban class="mr-2 h-4 w-4" /> Cancel</Button
                    >
                    <Button v-if="can.delete" variant="ghost" size="icon" aria-label="Delete draft" @click="deleteOpen = true">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <PageAlerts />

            <div v-if="hasPermission('audit.view')" class="-mt-3 text-right">
                <Link
                    :href="route('audit.index', { type: 'purchase_order', id: order.id })"
                    class="text-xs text-muted-foreground hover:text-foreground"
                >
                    View change history
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="space-y-4 lg:col-span-2">
                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Product</th>
                                    <th class="px-4 py-3 text-right font-medium">Received</th>
                                    <th class="hidden px-4 py-3 text-right font-medium sm:table-cell">Unit cost</th>
                                    <th class="hidden px-4 py-3 text-right font-medium sm:table-cell">Tax</th>
                                    <th class="px-4 py-3 text-right font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="item in order.items" :key="item.id">
                                    <td class="px-4 py-3">
                                        <p class="font-medium">{{ item.description }}</p>
                                        <p class="font-mono text-xs text-muted-foreground">{{ item.sku }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <span :class="item.remaining_quantity === 0 ? 'text-emerald-600 dark:text-emerald-400' : ''">
                                            {{ item.received_quantity }} / {{ item.quantity }}
                                        </span>
                                    </td>
                                    <td class="hidden px-4 py-3 text-right tabular-nums sm:table-cell">{{ formatMoney(item.unit_cost) }}</td>
                                    <td class="hidden px-4 py-3 text-right tabular-nums text-muted-foreground sm:table-cell">
                                        {{ Number(item.tax_rate) }}%
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(item.line_total) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t text-sm">
                                <tr>
                                    <td colspan="4" class="px-4 py-2 text-right text-muted-foreground">Subtotal</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(order.subtotal) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="px-4 py-2 text-right text-muted-foreground">Tax</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(order.tax_total) }}</td>
                                </tr>
                                <tr class="font-semibold">
                                    <td colspan="4" class="px-4 py-2 text-right">Total</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(order.total) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div v-if="order.bills.length" class="rounded-lg border">
                        <h3 class="border-b px-4 py-3 text-sm font-medium">Supplier bills</h3>
                        <ul class="divide-y text-sm">
                            <li v-for="bill in order.bills" :key="bill.id" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                                <div>
                                    <Link :href="route('finance.bills.show', bill.id)" class="font-mono font-medium hover:underline">{{
                                        bill.number
                                    }}</Link>
                                    <p class="text-muted-foreground">Ref. {{ bill.supplier_reference }} · due {{ formatDate(bill.due_date) }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <StatusBadge :status="bill.status" :label="bill.status_label" />
                                    <span class="tabular-nums">{{ formatMoney(bill.total) }}</span>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div v-if="order.receipts.length" class="rounded-lg border">
                        <h3 class="border-b px-4 py-3 text-sm font-medium">Goods receipts</h3>
                        <ul class="divide-y text-sm">
                            <li
                                v-for="receipt in order.receipts"
                                :key="receipt.id"
                                class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
                            >
                                <div>
                                    <p class="font-mono font-medium">{{ receipt.number }}</p>
                                    <p class="text-muted-foreground">
                                        {{ receipt.units }} units into {{ receipt.warehouse
                                        }}<template v-if="receipt.notes"> · {{ receipt.notes }}</template>
                                    </p>
                                </div>
                                <span class="text-xs text-muted-foreground"
                                    >{{ formatDateTime(receipt.received_at) }} · {{ receipt.received_by }}</span
                                >
                            </li>
                        </ul>
                    </div>
                </section>

                <aside class="space-y-4">
                    <dl class="space-y-3 rounded-lg border p-4 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Order date</dt>
                            <dd>{{ formatDate(order.order_date) }}</dd>
                        </div>
                        <div v-if="order.expected_date" class="flex justify-between">
                            <dt class="text-muted-foreground">Expected</dt>
                            <dd>{{ formatDate(order.expected_date) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Currency</dt>
                            <dd>{{ order.currency }}</dd>
                        </div>
                        <div>
                            <dt class="mb-1 flex justify-between text-muted-foreground">
                                <span>Received</span><span class="tabular-nums">{{ totalReceived }} / {{ totalOrdered }}</span>
                            </dt>
                            <dd class="h-1.5 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full bg-emerald-500"
                                    :style="{ width: `${totalOrdered ? (totalReceived / totalOrdered) * 100 : 0}%` }"
                                />
                            </dd>
                        </div>
                        <div v-if="order.notes" class="border-t pt-3">
                            <dt class="text-muted-foreground">Notes</dt>
                            <dd class="mt-1">{{ order.notes }}</dd>
                        </div>
                    </dl>

                    <div class="rounded-lg border p-4">
                        <h3 class="mb-3 text-sm font-medium">History</h3>
                        <ol class="space-y-3 border-l pl-4 text-sm">
                            <li v-for="(event, index) in timeline" :key="index" class="relative">
                                <span class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full bg-primary" />
                                <p>{{ event.label }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ formatDateTime(event.at) }}<template v-if="event.by"> · {{ event.by }}</template>
                                </p>
                            </li>
                        </ol>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Register supplier bill -->
        <Dialog v-model:open="billOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form class="space-y-5" @submit.prevent="registerBill">
                    <DialogHeader>
                        <DialogTitle>Register supplier bill · {{ order.number }}</DialogTitle>
                        <DialogDescription
                            >Only goods already received and not yet billed can be billed, at the order's agreed cost.</DialogDescription
                        >
                    </DialogHeader>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="b-ref">Supplier invoice no.</Label>
                            <Input id="b-ref" v-model="billForm.supplier_reference" required class="font-mono" />
                            <InputError :message="billForm.errors.supplier_reference" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="b-date">Bill date</Label>
                            <Input id="b-date" v-model="billForm.bill_date" type="date" required />
                            <InputError :message="billForm.errors.bill_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="b-due">Due date</Label>
                            <Input id="b-due" v-model="billForm.due_date" type="date" required />
                            <InputError :message="billForm.errors.due_date" />
                        </div>
                    </div>
                    <table class="w-full text-left text-sm">
                        <thead class="border-b text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="py-2 font-medium">Product</th>
                                <th class="py-2 text-right font-medium">Billable</th>
                                <th class="w-28 py-2 pl-3 font-medium">Bill</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="line in billForm.lines" :key="line.item_id">
                                <td class="py-2">{{ itemById[line.item_id].description }}</td>
                                <td class="py-2 text-right tabular-nums">{{ itemById[line.item_id].billable_quantity }}</td>
                                <td class="py-2 pl-3">
                                    <Input
                                        v-model.number="line.quantity"
                                        type="number"
                                        min="0"
                                        :max="itemById[line.item_id].billable_quantity"
                                        step="1"
                                        aria-label="Quantity to bill"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="billOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="billForm.processing">Register bill</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Receive -->
        <Dialog v-model:open="receiveOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form class="space-y-5" @submit.prevent="receive">
                    <DialogHeader>
                        <DialogTitle>Receive goods · {{ order.number }}</DialogTitle>
                        <DialogDescription>Enter what physically arrived. Stock is added immediately; the rest stays pending.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-2">
                        <Label for="r-warehouse">Into warehouse</Label>
                        <select id="r-warehouse" v-model="receiveForm.warehouse_id" :class="selectClass">
                            <option v-for="warehouse in warehouses.data" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
                        </select>
                        <InputError :message="receiveForm.errors.warehouse_id" />
                    </div>
                    <table class="w-full text-left text-sm">
                        <thead class="border-b text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="py-2 font-medium">Product</th>
                                <th class="py-2 text-right font-medium">Pending</th>
                                <th class="w-28 py-2 pl-3 font-medium">Receive</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="line in receiveForm.lines" :key="line.item_id">
                                <td class="py-2">{{ itemById[line.item_id].description }}</td>
                                <td class="py-2 text-right tabular-nums">{{ itemById[line.item_id].remaining_quantity }}</td>
                                <td class="py-2 pl-3">
                                    <Input
                                        v-model.number="line.quantity"
                                        type="number"
                                        min="0"
                                        :max="itemById[line.item_id].remaining_quantity"
                                        step="1"
                                        aria-label="Quantity to receive"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="grid gap-2">
                        <Label for="r-notes">Notes</Label>
                        <Input id="r-notes" v-model="receiveForm.notes" placeholder="Delivery note, carrier…" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="receiveOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="receiveForm.processing">Record receipt</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Cancel -->
        <Dialog v-model:open="cancelOpen">
            <DialogContent>
                <form class="space-y-5" @submit.prevent="cancel">
                    <DialogHeader>
                        <DialogTitle>Cancel {{ order.number }}?</DialogTitle>
                        <DialogDescription>Cancelled orders cannot be reopened. The reason is kept in the order history.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-2">
                        <Label for="c-reason">Reason</Label>
                        <Input id="c-reason" v-model="cancelForm.reason" required />
                        <InputError :message="cancelForm.errors.reason" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="cancelOpen = false">Keep order</Button>
                        <Button type="submit" variant="destructive" :disabled="cancelForm.processing">Cancel order</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete draft -->
        <Dialog v-model:open="deleteOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete draft {{ order.number }}?</DialogTitle>
                    <DialogDescription>Drafts were never sent, so they can be removed permanently.</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="secondary" @click="deleteOpen = false">Cancel</Button>
                    <Button variant="destructive" @click="destroy">Delete draft</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

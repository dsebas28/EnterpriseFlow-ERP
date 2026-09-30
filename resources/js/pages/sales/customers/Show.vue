<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney, type MoneyValue } from '@/composables/useMoney';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Mail, MapPin, MessageSquare, Pencil, Phone, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Customer {
    id: string;
    kind: 'company' | 'person';
    name: string;
    tax_id: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    country: string | null;
    status: 'active' | 'inactive';
}

interface SaleRow {
    id: string;
    number: string;
    status: string;
    status_label: string;
    sale_date: string;
    total: MoneyValue;
    balance_due: MoneyValue;
}

const props = defineProps<{
    customer: { data: Customer };
    stats: { orders: number; sold: MoneyValue; outstanding: MoneyValue; last_sale: string | null };
    sales: Paginated<SaleRow>;
    notes: { id: number; body: string; author: string | null; created_at: string; can_delete: boolean }[];
}>();

const customer = computed(() => props.customer.data);
const { can } = usePermissions();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Sales', href: '/sales/orders' },
    { title: 'Customers', href: '/sales/customers' },
    { title: customer.value.name, href: '#' },
]);

const noteForm = useForm({ body: '' });
const addNote = () =>
    noteForm.post(route('sales.customers.notes.store', customer.value.id), { preserveScroll: true, onSuccess: () => noteForm.reset() });
const deleteNote = (id: number) => router.delete(route('sales.customers.notes.destroy', [customer.value.id, id]), { preserveScroll: true });

type CustomerForm = {
    kind: string;
    name: string;
    tax_id: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    country: string;
    status: string;
};
const editOpen = ref(false);
const form = useForm<CustomerForm>({ kind: '', name: '', tax_id: '', email: '', phone: '', address: '', city: '', country: '', status: '' });
const openEdit = () => {
    const c = customer.value;
    form.defaults({
        kind: c.kind,
        name: c.name,
        tax_id: c.tax_id ?? '',
        email: c.email ?? '',
        phone: c.phone ?? '',
        address: c.address ?? '',
        city: c.city ?? '',
        country: c.country ?? '',
        status: c.status,
    });
    form.reset();
    form.clearErrors();
    editOpen.value = true;
};
const save = () => form.put(route('sales.customers.update', customer.value.id), { preserveScroll: true, onSuccess: () => (editOpen.value = false) });

const deleteOpen = ref(false);
const destroy = () => router.delete(route('sales.customers.destroy', customer.value.id));

const formatDate = (value: string) =>
    new Date(value.length === 10 ? `${value}T00:00:00` : value).toLocaleDateString(undefined, { dateStyle: 'medium' });
const formatDateTime = (value: string) => new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="customer.name" />

        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" as-child>
                        <Link :href="route('sales.customers.index')" aria-label="Back to customers"><ArrowLeft class="h-4 w-4" /></Link>
                    </Button>
                    <div>
                        <HeadingSmall :title="customer.name" :description="customer.tax_id ? `Tax ID ${customer.tax_id}` : undefined" />
                        <span v-if="customer.status === 'inactive'" class="text-xs text-muted-foreground">Inactive</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button v-if="can('sales.create') && customer.status === 'active'" as-child>
                        <Link :href="route('sales.orders.create', { customer_id: customer.id })"><Plus class="mr-2 h-4 w-4" /> New sale</Link>
                    </Button>
                    <Button v-if="can('customers.update')" variant="outline" @click="openEdit"><Pencil class="mr-2 h-4 w-4" /> Edit</Button>
                    <Button v-if="can('customers.delete')" variant="ghost" size="icon" aria-label="Delete customer" @click="deleteOpen = true">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <PageAlerts />

            <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-lg border p-4">
                    <dt class="text-xs uppercase tracking-wide text-muted-foreground">Total sold</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums">{{ formatMoney(stats.sold) }}</dd>
                </div>
                <div class="rounded-lg border p-4">
                    <dt class="text-xs uppercase tracking-wide text-muted-foreground">Balance due</dt>
                    <dd
                        class="mt-1 text-xl font-semibold tabular-nums"
                        :class="stats.outstanding.amount > 0 ? 'text-amber-700 dark:text-amber-300' : ''"
                    >
                        {{ formatMoney(stats.outstanding) }}
                    </dd>
                </div>
                <div class="rounded-lg border p-4">
                    <dt class="text-xs uppercase tracking-wide text-muted-foreground">Orders</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums">{{ stats.orders }}</dd>
                </div>
                <div class="rounded-lg border p-4">
                    <dt class="text-xs uppercase tracking-wide text-muted-foreground">Last purchase</dt>
                    <dd class="mt-1 text-xl font-semibold">{{ stats.last_sale ? formatDate(stats.last_sale) : '—' }}</dd>
                </div>
            </dl>

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="space-y-4 lg:col-span-2">
                    <h3 class="font-medium">Sales history</h3>
                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Sale</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 text-right font-medium">Total</th>
                                    <th class="px-4 py-3 text-right font-medium">Due</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="sale in sales.data" :key="sale.id">
                                    <td class="px-4 py-3">
                                        <Link :href="route('sales.orders.show', sale.id)" class="font-mono font-medium hover:underline">{{
                                            sale.number
                                        }}</Link>
                                        <p class="text-xs text-muted-foreground">{{ formatDate(sale.sale_date) }}</p>
                                    </td>
                                    <td class="px-4 py-3"><StatusBadge :status="sale.status" :label="sale.status_label" /></td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(sale.total) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        {{ sale.balance_due.amount > 0 ? formatMoney(sale.balance_due) : '—' }}
                                    </td>
                                </tr>
                                <tr v-if="sales.data.length === 0">
                                    <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">No sales yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :meta="sales.meta" />
                </section>

                <aside class="space-y-4">
                    <div class="space-y-2 rounded-lg border p-4 text-sm">
                        <p v-if="customer.email" class="flex items-center gap-2">
                            <Mail class="h-4 w-4 text-muted-foreground" /> {{ customer.email }}
                        </p>
                        <p v-if="customer.phone" class="flex items-center gap-2">
                            <Phone class="h-4 w-4 text-muted-foreground" /> {{ customer.phone }}
                        </p>
                        <p v-if="customer.address || customer.city" class="flex items-start gap-2">
                            <MapPin class="mt-0.5 h-4 w-4 text-muted-foreground" />
                            {{ [customer.address, customer.city, customer.country].filter(Boolean).join(', ') }}
                        </p>
                    </div>

                    <div class="rounded-lg border p-4">
                        <h3 class="mb-3 flex items-center gap-2 text-sm font-medium"><MessageSquare class="h-4 w-4" /> Notes</h3>
                        <form class="mb-4 space-y-2" @submit.prevent="addNote">
                            <textarea
                                v-model="noteForm.body"
                                rows="2"
                                placeholder="Add an internal note…"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                            <InputError :message="noteForm.errors.body" />
                            <Button size="sm" type="submit" :disabled="noteForm.processing || !noteForm.body.trim()">Add note</Button>
                        </form>
                        <ul class="space-y-3">
                            <li v-for="note in notes" :key="note.id" class="group rounded-md bg-muted/40 p-3 text-sm">
                                <p class="whitespace-pre-line">{{ note.body }}</p>
                                <div class="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                                    <span>{{ note.author ?? 'Unknown' }} · {{ formatDateTime(note.created_at) }}</span>
                                    <button
                                        v-if="note.can_delete"
                                        type="button"
                                        class="opacity-0 transition hover:text-red-600 focus:opacity-100 group-hover:opacity-100"
                                        aria-label="Delete note"
                                        @click="deleteNote(note.id)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </li>
                            <li v-if="notes.length === 0" class="text-sm text-muted-foreground">No notes yet.</li>
                        </ul>
                    </div>
                </aside>
            </div>
        </div>

        <Dialog v-model:open="editOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <form class="space-y-5" @submit.prevent="save">
                    <DialogHeader><DialogTitle>Edit customer</DialogTitle></DialogHeader>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="e-name">Name</Label>
                            <Input id="e-name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="e-tax">Tax ID</Label>
                            <Input id="e-tax" v-model="form.tax_id" />
                            <InputError :message="form.errors.tax_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="e-email">Email</Label>
                            <Input id="e-email" v-model="form.email" type="email" />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="e-phone">Phone</Label>
                            <Input id="e-phone" v-model="form.phone" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="e-city">City</Label>
                            <Input id="e-city" v-model="form.city" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="e-address">Address</Label>
                            <Input id="e-address" v-model="form.address" />
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.status" type="checkbox" true-value="active" false-value="inactive" class="rounded border-input" />
                            Active
                        </label>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="editOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">Save</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {{ customer.name }}?</DialogTitle>
                    <DialogDescription
                        >Past sales keep their history. Customers with open sales or a balance due cannot be deleted.</DialogDescription
                    >
                </DialogHeader>
                <DialogFooter>
                    <Button variant="secondary" @click="deleteOpen = false">Cancel</Button>
                    <Button variant="destructive" @click="destroy">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

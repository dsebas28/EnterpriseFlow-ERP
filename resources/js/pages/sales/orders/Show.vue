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
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Ban, CheckCircle2, Clock, Pencil, Trash2, Undo2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Item {
    id: number;
    sku: string;
    description: string;
    quantity: number;
    unit_price: MoneyValue;
    discount_rate: string;
    tax_rate: string;
    line_total: MoneyValue;
}

interface Sale {
    id: string;
    number: string;
    status: string;
    status_label: string;
    customer: { id: string; name: string };
    warehouse: { id: string; name: string };
    sale_date: string;
    currency: string;
    discount_total: MoneyValue;
    subtotal: MoneyValue;
    tax_total: MoneyValue;
    total: MoneyValue;
    amount_paid: MoneyValue;
    balance_due: MoneyValue;
    notes: string | null;
    created_by: string | null;
    confirmed_by: string | null;
    confirmed_at: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    created_at: string;
    items: Item[];
}

const props = defineProps<{
    sale: { data: Sale };
    can: { edit: boolean; markPending: boolean; returnToDraft: boolean; confirm: boolean; cancel: boolean; delete: boolean };
}>();

const sale = computed(() => props.sale.data);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Sales', href: '/sales/orders' },
    { title: sale.value.number, href: '#' },
]);

const post = (name: string) => router.post(route(name, sale.value.id), {}, { preserveScroll: true });

const confirmOpen = ref(false);
const confirming = ref(false);
const confirmSale = () =>
    router.post(
        route('sales.orders.confirm', sale.value.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (confirming.value = true),
            onFinish: () => {
                confirming.value = false;
                confirmOpen.value = false;
            },
        },
    );

const cancelOpen = ref(false);
const cancelForm = useForm({ reason: '' });
const cancel = () =>
    cancelForm.post(route('sales.orders.cancel', sale.value.id), { preserveScroll: true, onSuccess: () => (cancelOpen.value = false) });

const deleteOpen = ref(false);
const destroy = () => router.delete(route('sales.orders.destroy', sale.value.id));

const stockWasDeducted = computed(() => ['confirmed', 'partially_paid', 'paid'].includes(sale.value.status));

const formatDate = (value: string) =>
    new Date(value.length === 10 ? `${value}T00:00:00` : value).toLocaleDateString(undefined, { dateStyle: 'medium' });
const formatDateTime = (value: string) => new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="sale.number" />

        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" as-child>
                        <Link :href="route('sales.orders.index')" aria-label="Back to sales"><ArrowLeft class="h-4 w-4" /></Link>
                    </Button>
                    <div>
                        <div class="flex items-center gap-2">
                            <HeadingSmall :title="sale.number" />
                            <StatusBadge :status="sale.status" :label="sale.status_label" />
                        </div>
                        <p class="text-sm text-muted-foreground">
                            <Link :href="route('sales.customers.show', sale.customer.id)" class="hover:underline">{{ sale.customer.name }}</Link>
                            · from {{ sale.warehouse.name }} · {{ formatDate(sale.sale_date) }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="can.edit" variant="outline" as-child>
                        <Link :href="route('sales.orders.edit', sale.id)"><Pencil class="mr-2 h-4 w-4" /> Edit</Link>
                    </Button>
                    <Button v-if="can.markPending" variant="outline" @click="post('sales.orders.pending')"
                        ><Clock class="mr-2 h-4 w-4" /> Mark pending</Button
                    >
                    <Button v-if="can.returnToDraft" variant="outline" @click="post('sales.orders.return-to-draft')">
                        <Undo2 class="mr-2 h-4 w-4" /> Back to draft
                    </Button>
                    <Button v-if="can.confirm" @click="confirmOpen = true"><CheckCircle2 class="mr-2 h-4 w-4" /> Confirm sale</Button>
                    <Button v-if="can.cancel" variant="outline" class="text-red-600" @click="cancelOpen = true"
                        ><Ban class="mr-2 h-4 w-4" /> Cancel</Button
                    >
                    <Button v-if="can.delete" variant="ghost" size="icon" aria-label="Delete draft" @click="deleteOpen = true">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <PageAlerts />

            <p
                v-if="sale.status === 'cancelled'"
                class="rounded-md border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-700 dark:text-red-300"
            >
                Cancelled {{ sale.cancelled_at ? formatDateTime(sale.cancelled_at) : '' }}: {{ sale.cancel_reason }}
            </p>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="overflow-x-auto rounded-lg border lg:col-span-2">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Product</th>
                                <th class="px-4 py-3 text-right font-medium">Qty</th>
                                <th class="hidden px-4 py-3 text-right font-medium sm:table-cell">Price</th>
                                <th class="hidden px-4 py-3 text-right font-medium md:table-cell">Disc.</th>
                                <th class="hidden px-4 py-3 text-right font-medium md:table-cell">Tax</th>
                                <th class="px-4 py-3 text-right font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="item in sale.items" :key="item.id">
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ item.description }}</p>
                                    <p class="font-mono text-xs text-muted-foreground">{{ item.sku }}</p>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ item.quantity }}</td>
                                <td class="hidden px-4 py-3 text-right tabular-nums sm:table-cell">{{ formatMoney(item.unit_price) }}</td>
                                <td class="hidden px-4 py-3 text-right tabular-nums text-muted-foreground md:table-cell">
                                    {{ Number(item.discount_rate) ? `${Number(item.discount_rate)}%` : '—' }}
                                </td>
                                <td class="hidden px-4 py-3 text-right tabular-nums text-muted-foreground md:table-cell">
                                    {{ Number(item.tax_rate) }}%
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(item.line_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <aside class="space-y-4">
                    <dl class="space-y-2 rounded-lg border p-4 text-sm">
                        <div v-if="sale.discount_total.amount" class="flex justify-between text-muted-foreground">
                            <dt>Discounts</dt>
                            <dd class="tabular-nums">−{{ formatMoney(sale.discount_total) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Subtotal</dt>
                            <dd class="tabular-nums">{{ formatMoney(sale.subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Tax</dt>
                            <dd class="tabular-nums">{{ formatMoney(sale.tax_total) }}</dd>
                        </div>
                        <div class="flex justify-between border-t pt-2 text-base font-semibold">
                            <dt>Total</dt>
                            <dd class="tabular-nums">{{ formatMoney(sale.total) }}</dd>
                        </div>
                        <template v-if="stockWasDeducted">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Paid</dt>
                                <dd class="tabular-nums">{{ formatMoney(sale.amount_paid) }}</dd>
                            </div>
                            <div class="flex justify-between font-medium">
                                <dt>Balance due</dt>
                                <dd class="tabular-nums" :class="sale.balance_due.amount > 0 ? 'text-amber-700 dark:text-amber-300' : ''">
                                    {{ formatMoney(sale.balance_due) }}
                                </dd>
                            </div>
                        </template>
                    </dl>
                    <dl class="space-y-2 rounded-lg border p-4 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Created by</dt>
                            <dd>{{ sale.created_by ?? '—' }}</dd>
                        </div>
                        <div v-if="sale.confirmed_at" class="flex justify-between">
                            <dt class="text-muted-foreground">Confirmed</dt>
                            <dd class="text-right">
                                {{ formatDateTime(sale.confirmed_at) }}<br /><span class="text-xs text-muted-foreground">{{
                                    sale.confirmed_by
                                }}</span>
                            </dd>
                        </div>
                        <div v-if="sale.notes" class="border-t pt-2">
                            <dt class="text-muted-foreground">Notes</dt>
                            <dd class="mt-1">{{ sale.notes }}</dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </div>

        <Dialog v-model:open="confirmOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirm {{ sale.number }}?</DialogTitle>
                    <DialogDescription>
                        {{ sale.items.reduce((sum, item) => sum + item.quantity, 0) }} units will leave {{ sale.warehouse.name }}. If any item lacks
                        stock, nothing is deducted.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="secondary" @click="confirmOpen = false">Not yet</Button>
                    <Button :disabled="confirming" @click="confirmSale">Confirm sale</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="cancelOpen">
            <DialogContent>
                <form class="space-y-5" @submit.prevent="cancel">
                    <DialogHeader>
                        <DialogTitle>Cancel {{ sale.number }}?</DialogTitle>
                        <DialogDescription>
                            {{ stockWasDeducted ? 'The stock will be returned to the warehouse through return movements. ' : '' }}Cancelled sales
                            cannot be reopened.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-2">
                        <Label for="reason">Reason</Label>
                        <Input id="reason" v-model="cancelForm.reason" required />
                        <InputError :message="cancelForm.errors.reason" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="cancelOpen = false">Keep sale</Button>
                        <Button type="submit" variant="destructive" :disabled="cancelForm.processing">Cancel sale</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete draft {{ sale.number }}?</DialogTitle>
                    <DialogDescription>Drafts never moved stock, so they can be removed permanently.</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="secondary" @click="deleteOpen = false">Cancel</Button>
                    <Button variant="destructive" @click="destroy">Delete draft</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

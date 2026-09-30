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
import { useIntervalFn } from '@vueuse/core';
import { ArrowLeft, Ban, Download, FileWarning, Loader2, RefreshCw, Send } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Invoice {
    id: string;
    number: string | null;
    status: string;
    status_label: string;
    sale: { id: string; number: string };
    customer: { id: string; name: string };
    issue_date: string | null;
    due_date: string;
    currency: string;
    discount_total: MoneyValue;
    subtotal: MoneyValue;
    tax_total: MoneyValue;
    total: MoneyValue;
    amount_paid: MoneyValue;
    balance_due: MoneyValue;
    notes: string | null;
    pdf_status: 'pending' | 'ready' | 'failed' | null;
    pdf_generated_at: string | null;
    issued_by: string | null;
    issued_at: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    items: {
        id: number;
        description: string;
        quantity: number;
        unit_price: MoneyValue;
        discount_rate: string;
        tax_rate: string;
        line_total: MoneyValue;
    }[];
}

const props = defineProps<{
    invoice: { data: Invoice };
    can: { edit: boolean; issue: boolean; cancel: boolean; regeneratePdf: boolean };
}>();

const invoice = computed(() => props.invoice.data);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Finance', href: '/finance/invoices' },
    { title: 'Invoices', href: '/finance/invoices' },
    { title: invoice.value.number ?? 'Draft invoice', href: '#' },
]);

// While the PDF is rendered in the queue, poll only the invoice prop.
const { pause, resume } = useIntervalFn(() => router.reload({ only: ['invoice'] }), 3000, { immediate: false });
watch(
    () => invoice.value.pdf_status,
    (status) => (status === 'pending' ? resume() : pause()),
    { immediate: true },
);

const editForm = useForm({ due_date: invoice.value.due_date, notes: invoice.value.notes ?? '' });
const saveDraft = () => editForm.put(route('finance.invoices.update', invoice.value.id), { preserveScroll: true });

const issue = () => router.post(route('finance.invoices.issue', invoice.value.id), {}, { preserveScroll: true });
const regenerate = () => router.post(route('finance.invoices.pdf.regenerate', invoice.value.id), {}, { preserveScroll: true });

const cancelOpen = ref(false);
const cancelForm = useForm({ reason: '' });
const cancel = () =>
    cancelForm.post(route('finance.invoices.cancel', invoice.value.id), { preserveScroll: true, onSuccess: () => (cancelOpen.value = false) });

const formatDate = (value: string) => new Date(`${value}T00:00:00`).toLocaleDateString(undefined, { dateStyle: 'medium' });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="invoice.number ?? 'Draft invoice'" />

        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" as-child>
                        <Link :href="route('finance.invoices.index')" aria-label="Back to invoices"><ArrowLeft class="h-4 w-4" /></Link>
                    </Button>
                    <div>
                        <div class="flex items-center gap-2">
                            <HeadingSmall :title="invoice.number ?? 'Draft invoice'" />
                            <StatusBadge :status="invoice.status" :label="invoice.status_label" />
                        </div>
                        <p class="text-sm text-muted-foreground">
                            <Link :href="route('sales.customers.show', invoice.customer.id)" class="hover:underline">{{
                                invoice.customer.name
                            }}</Link>
                            · from sale
                            <Link :href="route('sales.orders.show', invoice.sale.id)" class="font-mono hover:underline">{{
                                invoice.sale.number
                            }}</Link>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <template v-if="invoice.number">
                        <Button v-if="invoice.pdf_status === 'ready'" variant="outline" as-child>
                            <a :href="route('finance.invoices.pdf', invoice.id)"><Download class="mr-2 h-4 w-4" /> Download PDF</a>
                        </Button>
                        <span v-else-if="invoice.pdf_status === 'pending'" class="inline-flex items-center gap-2 text-sm text-muted-foreground">
                            <Loader2 class="h-4 w-4 animate-spin" /> Generating PDF…
                        </span>
                        <span v-else-if="invoice.pdf_status === 'failed'" class="inline-flex items-center gap-2 text-sm text-red-600">
                            <FileWarning class="h-4 w-4" /> PDF failed
                        </span>
                        <Button
                            v-if="can.regeneratePdf && invoice.pdf_status !== 'pending'"
                            variant="ghost"
                            size="icon"
                            title="Regenerate PDF"
                            @click="regenerate"
                        >
                            <RefreshCw class="h-4 w-4" />
                        </Button>
                    </template>
                    <Button v-if="can.issue" @click="issue"><Send class="mr-2 h-4 w-4" /> Issue invoice</Button>
                    <Button v-if="can.cancel" variant="outline" class="text-red-600" @click="cancelOpen = true"
                        ><Ban class="mr-2 h-4 w-4" /> Cancel</Button
                    >
                </div>
            </div>

            <PageAlerts />

            <p
                v-if="invoice.status === 'cancelled'"
                class="rounded-md border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-700 dark:text-red-300"
            >
                Cancelled: {{ invoice.cancel_reason }}
            </p>

            <form
                v-if="can.edit"
                class="grid gap-4 rounded-lg border border-dashed p-4 sm:grid-cols-[1fr_2fr_auto] sm:items-end"
                @submit.prevent="saveDraft"
            >
                <div class="grid gap-2">
                    <Label for="due_date">Due date</Label>
                    <Input id="due_date" v-model="editForm.due_date" type="date" required />
                    <InputError :message="editForm.errors.due_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="notes">Notes on the invoice</Label>
                    <Input id="notes" v-model="editForm.notes" placeholder="Payment instructions, bank account…" />
                </div>
                <Button type="submit" variant="outline" :disabled="editForm.processing || !editForm.isDirty">Save draft</Button>
            </form>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="overflow-x-auto rounded-lg border lg:col-span-2">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Description</th>
                                <th class="px-4 py-3 text-right font-medium">Qty</th>
                                <th class="hidden px-4 py-3 text-right font-medium sm:table-cell">Price</th>
                                <th class="hidden px-4 py-3 text-right font-medium md:table-cell">Tax</th>
                                <th class="px-4 py-3 text-right font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="item in invoice.items" :key="item.id">
                                <td class="px-4 py-3">
                                    {{ item.description }}
                                    <span v-if="Number(item.discount_rate)" class="text-xs text-muted-foreground">
                                        (−{{ Number(item.discount_rate) }}%)</span
                                    >
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ item.quantity }}</td>
                                <td class="hidden px-4 py-3 text-right tabular-nums sm:table-cell">{{ formatMoney(item.unit_price) }}</td>
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
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Issue date</dt>
                            <dd>{{ invoice.issue_date ? formatDate(invoice.issue_date) : 'On issue' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Due date</dt>
                            <dd :class="invoice.status === 'overdue' ? 'font-medium text-red-600' : ''">{{ formatDate(invoice.due_date) }}</dd>
                        </div>
                        <div v-if="invoice.issued_by" class="flex justify-between">
                            <dt class="text-muted-foreground">Issued by</dt>
                            <dd>{{ invoice.issued_by }}</dd>
                        </div>
                    </dl>
                    <dl class="space-y-2 rounded-lg border p-4 text-sm">
                        <div v-if="invoice.discount_total.amount" class="flex justify-between text-muted-foreground">
                            <dt>Discounts</dt>
                            <dd class="tabular-nums">−{{ formatMoney(invoice.discount_total) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Subtotal</dt>
                            <dd class="tabular-nums">{{ formatMoney(invoice.subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Tax</dt>
                            <dd class="tabular-nums">{{ formatMoney(invoice.tax_total) }}</dd>
                        </div>
                        <div class="flex justify-between border-t pt-2 text-base font-semibold">
                            <dt>Total</dt>
                            <dd class="tabular-nums">{{ formatMoney(invoice.total) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Paid</dt>
                            <dd class="tabular-nums">{{ formatMoney(invoice.amount_paid) }}</dd>
                        </div>
                        <div class="flex justify-between font-medium">
                            <dt>Balance due</dt>
                            <dd class="tabular-nums">{{ formatMoney(invoice.balance_due) }}</dd>
                        </div>
                    </dl>
                    <p v-if="invoice.notes && !can.edit" class="rounded-lg border p-4 text-sm">{{ invoice.notes }}</p>
                </aside>
            </div>
        </div>

        <Dialog v-model:open="cancelOpen">
            <DialogContent>
                <form class="space-y-5" @submit.prevent="cancel">
                    <DialogHeader>
                        <DialogTitle>Cancel {{ invoice.number ?? 'this draft' }}?</DialogTitle>
                        <DialogDescription>The number stays used and the reason is recorded. The sale can then be invoiced again.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-2">
                        <Label for="reason">Reason</Label>
                        <Input id="reason" v-model="cancelForm.reason" required />
                        <InputError :message="cancelForm.errors.reason" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="cancelOpen = false">Keep invoice</Button>
                        <Button type="submit" variant="destructive" :disabled="cancelForm.processing">Cancel invoice</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

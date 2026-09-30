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
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Ban } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Bill {
    id: string;
    number: string;
    supplier_reference: string;
    status: string;
    status_label: string;
    supplier: { id: string; name: string };
    purchase_order: { id: string; number: string };
    bill_date: string;
    due_date: string;
    subtotal: MoneyValue;
    tax_total: MoneyValue;
    total: MoneyValue;
    amount_paid: MoneyValue;
    balance_due: MoneyValue;
    notes: string | null;
    created_by: string | null;
    cancel_reason: string | null;
    items: { id: number; description: string; quantity: number; unit_cost: MoneyValue; tax_rate: string; line_total: MoneyValue }[];
}

const props = defineProps<{
    bill: { data: Bill };
    can: { cancel: boolean };
}>();

const bill = computed(() => props.bill.data);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Finance', href: '/finance/invoices' },
    { title: 'Supplier bills', href: '/finance/bills' },
    { title: bill.value.number, href: '#' },
]);

const cancelOpen = ref(false);
const cancelForm = useForm({ reason: '' });
const cancel = () =>
    cancelForm.post(route('finance.bills.cancel', bill.value.id), { preserveScroll: true, onSuccess: () => (cancelOpen.value = false) });

const formatDate = (value: string) => new Date(`${value}T00:00:00`).toLocaleDateString(undefined, { dateStyle: 'medium' });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="bill.number" />

        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" as-child>
                        <Link :href="route('finance.bills.index')" aria-label="Back to bills"><ArrowLeft class="h-4 w-4" /></Link>
                    </Button>
                    <div>
                        <div class="flex items-center gap-2">
                            <HeadingSmall :title="bill.number" />
                            <StatusBadge :status="bill.status" :label="bill.status_label" />
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ bill.supplier.name }} · ref. <span class="font-mono">{{ bill.supplier_reference }}</span> · order
                            <Link :href="route('purchasing.orders.show', bill.purchase_order.id)" class="font-mono hover:underline">
                                {{ bill.purchase_order.number }}
                            </Link>
                        </p>
                    </div>
                </div>
                <Button v-if="can.cancel" variant="outline" class="text-red-600" @click="cancelOpen = true"
                    ><Ban class="mr-2 h-4 w-4" /> Cancel</Button
                >
            </div>

            <PageAlerts />

            <p
                v-if="bill.status === 'cancelled'"
                class="rounded-md border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-700 dark:text-red-300"
            >
                Cancelled: {{ bill.cancel_reason }}
            </p>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="overflow-x-auto rounded-lg border lg:col-span-2">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Description</th>
                                <th class="px-4 py-3 text-right font-medium">Qty</th>
                                <th class="hidden px-4 py-3 text-right font-medium sm:table-cell">Unit cost</th>
                                <th class="hidden px-4 py-3 text-right font-medium md:table-cell">Tax</th>
                                <th class="px-4 py-3 text-right font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="item in bill.items" :key="item.id">
                                <td class="px-4 py-3">{{ item.description }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ item.quantity }}</td>
                                <td class="hidden px-4 py-3 text-right tabular-nums sm:table-cell">{{ formatMoney(item.unit_cost) }}</td>
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
                            <dt class="text-muted-foreground">Bill date</dt>
                            <dd>{{ formatDate(bill.bill_date) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Due date</dt>
                            <dd :class="bill.status === 'overdue' ? 'font-medium text-red-600' : ''">{{ formatDate(bill.due_date) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Registered by</dt>
                            <dd>{{ bill.created_by ?? '—' }}</dd>
                        </div>
                    </dl>
                    <dl class="space-y-2 rounded-lg border p-4 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Subtotal</dt>
                            <dd class="tabular-nums">{{ formatMoney(bill.subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Tax</dt>
                            <dd class="tabular-nums">{{ formatMoney(bill.tax_total) }}</dd>
                        </div>
                        <div class="flex justify-between border-t pt-2 text-base font-semibold">
                            <dt>Total</dt>
                            <dd class="tabular-nums">{{ formatMoney(bill.total) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Paid</dt>
                            <dd class="tabular-nums">{{ formatMoney(bill.amount_paid) }}</dd>
                        </div>
                        <div class="flex justify-between font-medium">
                            <dt>Balance due</dt>
                            <dd class="tabular-nums">{{ formatMoney(bill.balance_due) }}</dd>
                        </div>
                    </dl>
                    <p v-if="bill.notes" class="rounded-lg border p-4 text-sm">{{ bill.notes }}</p>
                </aside>
            </div>
        </div>

        <Dialog v-model:open="cancelOpen">
            <DialogContent>
                <form class="space-y-5" @submit.prevent="cancel">
                    <DialogHeader>
                        <DialogTitle>Cancel {{ bill.number }}?</DialogTitle>
                        <DialogDescription>Its quantities become billable again so the correct bill can be registered.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-2">
                        <Label for="reason">Reason</Label>
                        <Input id="reason" v-model="cancelForm.reason" required />
                        <InputError :message="cancelForm.errors.reason" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="cancelOpen = false">Keep bill</Button>
                        <Button type="submit" variant="destructive" :disabled="cancelForm.processing">Cancel bill</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

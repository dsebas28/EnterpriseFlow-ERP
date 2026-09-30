<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney, type MoneyValue } from '@/composables/useMoney';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { Banknote, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

export interface PaymentRow {
    id: string;
    number: string;
    method_label: string;
    amount: MoneyValue;
    paid_at: string;
    reference: string | null;
    status: 'posted' | 'voided';
    created_by: string | null;
    voided_by: string | null;
    void_reason: string | null;
}

const props = defineProps<{
    payments: PaymentRow[];
    balance: MoneyValue;
    storeUrl: string;
    options: { methods: Option[]; today: string; currency: { code: string; decimals: number } };
    canPay: boolean;
    canVoid: boolean;
    title?: string;
}>();

const payOpen = ref(false);
const form = useForm({ amount: '', method: 'bank_transfer', paid_at: props.options.today, reference: '', notes: '' });
const step = computed(() =>
    props.options.currency.decimals === 0 ? '1' : (1 / 10 ** props.options.currency.decimals).toFixed(props.options.currency.decimals),
);

const openPay = () => {
    form.reset();
    form.clearErrors();
    form.amount = props.balance.decimal;
    payOpen.value = true;
};
const record = () => form.post(props.storeUrl, { preserveScroll: true, onSuccess: () => (payOpen.value = false) });

const voiding = ref<PaymentRow | null>(null);
const voidForm = useForm({ reason: '' });
const voidPayment = () =>
    voidForm.post(route('finance.payments.void', voiding.value!.id), {
        preserveScroll: true,
        onSuccess: () => {
            voiding.value = null;
            voidForm.reset();
        },
    });

const formatDate = (value: string) => new Date(`${value}T00:00:00`).toLocaleDateString(undefined, { dateStyle: 'medium' });
</script>

<template>
    <section class="rounded-lg border">
        <div class="flex items-center justify-between border-b px-4 py-3">
            <h3 class="flex items-center gap-2 text-sm font-medium"><Banknote class="h-4 w-4" /> {{ title ?? 'Payments' }}</h3>
            <Button v-if="canPay && balance.amount > 0" size="sm" @click="openPay"><Plus class="mr-1 h-4 w-4" /> Record payment</Button>
        </div>
        <ul v-if="payments.length" class="divide-y text-sm">
            <li v-for="payment in payments" :key="payment.id" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                <div :class="{ 'opacity-60': payment.status === 'voided' }">
                    <p class="font-medium">
                        <span :class="{ 'line-through': payment.status === 'voided' }">{{ formatMoney(payment.amount) }}</span>
                        <span class="ml-2 font-mono text-xs text-muted-foreground">{{ payment.number }}</span>
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ payment.method_label }} · {{ formatDate(payment.paid_at)
                        }}<template v-if="payment.reference"> · {{ payment.reference }}</template>
                        <template v-if="payment.created_by"> · {{ payment.created_by }}</template>
                    </p>
                    <p v-if="payment.status === 'voided'" class="text-xs text-red-600">
                        Voided by {{ payment.voided_by }}: {{ payment.void_reason }}
                    </p>
                </div>
                <Button v-if="canVoid && payment.status === 'posted'" variant="ghost" size="sm" class="text-red-600" @click="voiding = payment"
                    >Void</Button
                >
            </li>
        </ul>
        <p v-else class="px-4 py-6 text-center text-sm text-muted-foreground">No payments recorded yet.</p>

        <Dialog v-model:open="payOpen">
            <DialogContent>
                <form class="space-y-5" @submit.prevent="record">
                    <DialogHeader>
                        <DialogTitle>Record payment</DialogTitle>
                        <DialogDescription>Balance due: {{ formatMoney(balance) }}. Payments cannot exceed it.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="p-amount">Amount ({{ options.currency.code }})</Label>
                            <Input id="p-amount" v-model="form.amount" type="number" min="0" :max="balance.decimal" :step="step" required />
                            <InputError :message="form.errors.amount" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="p-method">Method</Label>
                            <select
                                id="p-method"
                                v-model="form.method"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option v-for="method in options.methods" :key="method.value" :value="method.value">{{ method.label }}</option>
                            </select>
                            <InputError :message="form.errors.method" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="p-date">Date</Label>
                            <Input id="p-date" v-model="form.paid_at" type="date" :max="options.today" required />
                            <InputError :message="form.errors.paid_at" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="p-ref">Reference</Label>
                            <Input id="p-ref" v-model="form.reference" placeholder="Transfer ID, voucher…" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="payOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">Record payment</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="voiding !== null" @update:open="(value) => !value && (voiding = null)">
            <DialogContent>
                <form class="space-y-5" @submit.prevent="voidPayment">
                    <DialogHeader>
                        <DialogTitle>Void payment {{ voiding?.number }}?</DialogTitle>
                        <DialogDescription>The payment stays in the history as voided and the balance due is restored.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-2">
                        <Label for="v-reason">Reason</Label>
                        <Input id="v-reason" v-model="voidForm.reason" required placeholder="Bounced transfer" />
                        <InputError :message="voidForm.errors.reason" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="voiding = null">Keep payment</Button>
                        <Button type="submit" variant="destructive" :disabled="voidForm.processing">Void payment</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </section>
</template>

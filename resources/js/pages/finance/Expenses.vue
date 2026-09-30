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
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Option, Paginated } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Check, Paperclip, Pencil, Plus, Search, Tags, Trash2, Wallet, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface ExpenseRow {
    id: string;
    number: string;
    category: { id: number; name: string };
    supplier: { id: string; name: string } | null;
    description: string;
    amount: MoneyValue;
    expense_date: string;
    payment_method: string | null;
    payment_method_label: string | null;
    has_receipt: boolean;
    status: 'pending' | 'approved' | 'rejected';
    status_label: string;
    created_by: string | null;
    reviewed_by: string | null;
    rejection_reason: string | null;
    can: { update: boolean; review: boolean };
}

const props = defineProps<{
    expenses: Paginated<ExpenseRow>;
    totals: { approved: MoneyValue; pending: MoneyValue };
    categories: { id: number; name: string }[];
    suppliers: { id: string; name: string }[];
    methods: Option[];
    currency: { code: string; decimals: number };
    today: string;
    filters: { search?: string; status?: string; category_id?: string; from?: string; to?: string };
    can: { create: boolean; manageCategories: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Finance', href: '/finance/invoices' },
    { title: 'Expenses', href: '/finance/expenses' },
];

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    category_id: props.filters.category_id ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});
const apply = () => {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
    router.get(route('finance.expenses.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};
watch(() => filters.search, useDebounceFn(apply, 300));
watch(() => [filters.status, filters.category_id, filters.from, filters.to], apply);

// Create / edit
type ExpenseForm = {
    category_id: number | null;
    supplier_id: string | null;
    description: string;
    amount: string;
    expense_date: string;
    payment_method: string | null;
    receipt: File | null;
};
const formOpen = ref(false);
const editing = ref<ExpenseRow | null>(null);
const form = useForm<ExpenseForm>({
    category_id: null,
    supplier_id: null,
    description: '',
    amount: '',
    expense_date: props.today,
    payment_method: null,
    receipt: null,
});
const openForm = (expense: ExpenseRow | null) => {
    editing.value = expense;
    form.clearErrors();
    form.category_id = expense?.category.id ?? props.categories[0]?.id ?? null;
    form.supplier_id = expense?.supplier?.id ?? null;
    form.description = expense?.description ?? '';
    form.amount = expense?.amount.decimal ?? '';
    form.expense_date = expense?.expense_date ?? props.today;
    form.payment_method = expense?.payment_method ?? null;
    form.receipt = null;
    formOpen.value = true;
};
const onReceipt = (event: Event) => (form.receipt = (event.target as HTMLInputElement).files?.[0] ?? null);
const save = () => {
    const options = { preserveScroll: true, forceFormData: true, onSuccess: () => (formOpen.value = false) };
    if (editing.value) {
        // Multipart bodies cannot be sent with PUT in PHP: spoof the method.
        form.transform((data) => ({ ...data, _method: 'put' })).post(route('finance.expenses.update', editing.value.id), options);
    } else {
        form.transform((data) => data).post(route('finance.expenses.store'), options);
    }
};
const step = computed(() => (props.currency.decimals === 0 ? '1' : (1 / 10 ** props.currency.decimals).toFixed(props.currency.decimals)));

// Review
const approve = (expense: ExpenseRow) => router.post(route('finance.expenses.approve', expense.id), {}, { preserveScroll: true });
const rejecting = ref<ExpenseRow | null>(null);
const rejectForm = useForm({ reason: '' });
const reject = () =>
    rejectForm.post(route('finance.expenses.reject', rejecting.value!.id), {
        preserveScroll: true,
        onSuccess: () => {
            rejecting.value = null;
            rejectForm.reset();
        },
    });

// Delete
const deleting = ref<ExpenseRow | null>(null);
const destroy = () =>
    router.delete(route('finance.expenses.destroy', deleting.value!.id), { preserveScroll: true, onFinish: () => (deleting.value = null) });

// Categories
const categoryOpen = ref(false);
const categoryForm = useForm({ name: '' });
const addCategory = () =>
    categoryForm.post(route('finance.expenses.categories.store'), {
        preserveScroll: true,
        onSuccess: () => {
            categoryForm.reset();
            categoryOpen.value = false;
        },
    });

const formatDate = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { dateStyle: 'medium' });
const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Expenses" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <HeadingSmall title="Expenses" description="Operating costs with receipts. Someone other than the author approves each expense." />
                <div class="flex gap-2">
                    <Button v-if="can.manageCategories" variant="outline" @click="categoryOpen = true"
                        ><Tags class="mr-2 h-4 w-4" /> Add category</Button
                    >
                    <Button v-if="can.create" :disabled="categories.length === 0" @click="openForm(null)"
                        ><Plus class="mr-2 h-4 w-4" /> New expense</Button
                    >
                </div>
            </div>

            <PageAlerts />

            <p v-if="can.create && categories.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                Create at least one expense category (for example “Rent” or “Travel”) before recording expenses.
            </p>

            <dl class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg border p-4">
                    <dt class="text-xs uppercase tracking-wide text-muted-foreground">Approved</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ formatMoney(totals.approved) }}</dd>
                </div>
                <div class="rounded-lg border p-4">
                    <dt class="text-xs uppercase tracking-wide text-amber-700 dark:text-amber-300">Awaiting approval</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ formatMoney(totals.pending) }}</dd>
                </div>
            </dl>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full sm:w-64">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Number or description" class="pl-9" aria-label="Search expenses" />
                </div>
                <select v-model="filters.status" :class="[selectClass, 'w-auto']" aria-label="Status">
                    <option value="">Any status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <select v-model="filters.category_id" :class="[selectClass, 'w-auto']" aria-label="Category">
                    <option value="">All categories</option>
                    <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
                </select>
                <Input v-model="filters.from" type="date" class="w-auto" aria-label="From date" />
                <Input v-model="filters.to" type="date" class="w-auto" aria-label="To date" />
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Expense</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Category</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Amount</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="expense in expenses.data" :key="expense.id">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ expense.description }}</p>
                                <p class="text-xs text-muted-foreground">
                                    <span class="font-mono">{{ expense.number }}</span> · {{ formatDate(expense.expense_date) }}
                                    <template v-if="expense.supplier"> · {{ expense.supplier.name }}</template>
                                    <template v-if="expense.created_by"> · by {{ expense.created_by }}</template>
                                </p>
                                <p v-if="expense.rejection_reason" class="text-xs text-red-600">Rejected: {{ expense.rejection_reason }}</p>
                            </td>
                            <td class="hidden px-4 py-3 text-muted-foreground md:table-cell">{{ expense.category.name }}</td>
                            <td class="px-4 py-3">
                                <StatusBadge :status="expense.status === 'approved' ? 'paid' : expense.status" :label="expense.status_label" />
                            </td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums">{{ formatMoney(expense.amount) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button v-if="expense.has_receipt" variant="ghost" size="icon" title="Receipt" as-child>
                                        <a :href="route('finance.expenses.receipt', expense.id)"><Paperclip class="h-4 w-4" /></a>
                                    </Button>
                                    <template v-if="expense.can.review">
                                        <Button variant="ghost" size="icon" title="Approve" class="text-emerald-600" @click="approve(expense)">
                                            <Check class="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" title="Reject" class="text-red-600" @click="rejecting = expense"
                                            ><X class="h-4 w-4"
                                        /></Button>
                                    </template>
                                    <template v-if="expense.can.update">
                                        <Button variant="ghost" size="icon" title="Edit" @click="openForm(expense)"
                                            ><Pencil class="h-4 w-4"
                                        /></Button>
                                        <Button variant="ghost" size="icon" title="Delete" @click="deleting = expense"
                                            ><Trash2 class="h-4 w-4"
                                        /></Button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="expenses.data.length === 0">
                            <td colspan="5" class="px-4 py-16 text-center">
                                <Wallet class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                                <p class="font-medium">No expenses found</p>
                                <p class="mt-1 text-sm text-muted-foreground">Record rent, utilities, travel and other operating costs here.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="expenses.meta" />
        </div>

        <Dialog v-model:open="formOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <form class="space-y-5" @submit.prevent="save">
                    <DialogHeader>
                        <DialogTitle>{{ editing ? `Edit ${editing.number}` : 'New expense' }}</DialogTitle>
                        <DialogDescription>It will be pending until another member approves it.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="e-desc">Description</Label>
                            <Input id="e-desc" v-model="form.description" required />
                            <InputError :message="form.errors.description" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="e-cat">Category</Label>
                            <select id="e-cat" v-model="form.category_id" :class="selectClass" required>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                            <InputError :message="form.errors.category_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="e-amount">Amount ({{ currency.code }})</Label>
                            <Input id="e-amount" v-model="form.amount" type="number" min="0" :step="step" required />
                            <InputError :message="form.errors.amount" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="e-date">Date</Label>
                            <Input id="e-date" v-model="form.expense_date" type="date" :max="today" required />
                            <InputError :message="form.errors.expense_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="e-method">Paid with</Label>
                            <select id="e-method" v-model="form.payment_method" :class="selectClass">
                                <option :value="null">—</option>
                                <option v-for="method in methods" :key="method.value" :value="method.value">{{ method.label }}</option>
                            </select>
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="e-supplier">Supplier <span class="text-muted-foreground">(optional)</span></Label>
                            <select id="e-supplier" v-model="form.supplier_id" :class="selectClass">
                                <option :value="null">—</option>
                                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                            </select>
                            <InputError :message="form.errors.supplier_id" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="e-receipt">Receipt <span class="text-muted-foreground">(PDF or image, up to 5 MB)</span></Label>
                            <Input id="e-receipt" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" @change="onReceipt" />
                            <p v-if="editing?.has_receipt && !form.receipt" class="text-xs text-muted-foreground">
                                A receipt is attached; choose a file to replace it.
                            </p>
                            <InputError :message="form.errors.receipt" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="formOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">Save expense</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="rejecting !== null" @update:open="(value) => !value && (rejecting = null)">
            <DialogContent>
                <form class="space-y-5" @submit.prevent="reject">
                    <DialogHeader>
                        <DialogTitle>Reject {{ rejecting?.number }}?</DialogTitle>
                        <DialogDescription>The author will see the reason.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-2">
                        <Label for="r-reason">Reason</Label>
                        <Input id="r-reason" v-model="rejectForm.reason" required />
                        <InputError :message="rejectForm.errors.reason" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="rejecting = null">Cancel</Button>
                        <Button type="submit" variant="destructive" :disabled="rejectForm.processing">Reject</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="deleting !== null" @update:open="(value) => !value && (deleting = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {{ deleting?.number }}?</DialogTitle>
                    <DialogDescription>Only pending expenses can be deleted; the receipt file is removed too.</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="secondary" @click="deleting = null">Cancel</Button>
                    <Button variant="destructive" @click="destroy">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="categoryOpen">
            <DialogContent>
                <form class="space-y-5" @submit.prevent="addCategory">
                    <DialogHeader><DialogTitle>New expense category</DialogTitle></DialogHeader>
                    <div class="grid gap-2">
                        <Label for="c-name">Name</Label>
                        <Input id="c-name" v-model="categoryForm.name" required placeholder="Rent, Utilities, Travel…" />
                        <InputError :message="categoryForm.errors.name" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="categoryOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="categoryForm.processing">Add category</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

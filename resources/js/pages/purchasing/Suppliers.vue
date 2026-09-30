<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Building2, Mail, Pencil, Phone, Plus, Search, Trash2, Truck, User } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

interface Supplier {
    id: string;
    kind: 'company' | 'person';
    name: string;
    tax_id: string | null;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    country: string | null;
    notes: string | null;
    status: 'active' | 'inactive';
    open_orders_count?: number;
}

const props = defineProps<{
    suppliers: Paginated<Supplier>;
    filters: { search: string; status: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchasing', href: '/purchasing/orders' },
    { title: 'Suppliers', href: '/purchasing/suppliers' },
];

const { can } = usePermissions();

const filters = reactive({ ...props.filters });
const apply = () => {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
    router.get(route('purchasing.suppliers.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};
watch(() => filters.search, useDebounceFn(apply, 300));
watch(() => filters.status, apply);

// Form fields are always strings; nullable API values are mapped to ''.
type SupplierForm = {
    kind: 'company' | 'person';
    name: string;
    tax_id: string;
    contact_name: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    country: string;
    notes: string;
    status: 'active' | 'inactive';
};
const blank = (): SupplierForm => ({
    kind: 'company',
    name: '',
    tax_id: '',
    contact_name: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    country: '',
    notes: '',
    status: 'active',
});

const open = ref(false);
const editing = ref<Supplier | null>(null);
const form = useForm<SupplierForm>(blank());

const openForm = (supplier: Supplier | null) => {
    editing.value = supplier;
    form.defaults(
        supplier
            ? (Object.fromEntries(Object.entries({ ...blank(), ...supplier }).map(([key, value]) => [key, value ?? ''])) as SupplierForm)
            : blank(),
    );
    form.reset();
    form.clearErrors();
    open.value = true;
};

const save = () => {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) {
        form.put(route('purchasing.suppliers.update', editing.value.id), options);
    } else {
        form.post(route('purchasing.suppliers.store'), options);
    }
};

const deleting = ref<Supplier | null>(null);
const destroy = () =>
    router.delete(route('purchasing.suppliers.destroy', deleting.value!.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Suppliers" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <HeadingSmall title="Suppliers" description="Companies and people you buy from." />
                <Button v-if="can('suppliers.create')" @click="openForm(null)"><Plus class="mr-2 h-4 w-4" /> New supplier</Button>
            </div>

            <PageAlerts />

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Search name, tax ID or email" class="pl-9" aria-label="Search suppliers" />
                </div>
                <select v-model="filters.status" :class="[selectClass, 'w-auto']" aria-label="Status">
                    <option value="">Any status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Supplier</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Contact</th>
                            <th class="px-4 py-3 text-right font-medium">Open orders</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="supplier in suppliers.data" :key="supplier.id" :class="{ 'opacity-60': supplier.status === 'inactive' }">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-muted">
                                        <component :is="supplier.kind === 'company' ? Building2 : User" class="h-4 w-4 text-muted-foreground" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-medium">
                                            {{ supplier.name }}
                                            <span v-if="supplier.status === 'inactive'" class="ml-1 text-xs font-normal text-muted-foreground"
                                                >(inactive)</span
                                            >
                                        </p>
                                        <p class="font-mono text-xs text-muted-foreground">{{ supplier.tax_id ?? 'No tax ID' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 text-muted-foreground md:table-cell">
                                <p v-if="supplier.contact_name" class="text-foreground">{{ supplier.contact_name }}</p>
                                <p v-if="supplier.email" class="flex items-center gap-1.5"><Mail class="h-3 w-3" /> {{ supplier.email }}</p>
                                <p v-if="supplier.phone" class="flex items-center gap-1.5"><Phone class="h-3 w-3" /> {{ supplier.phone }}</p>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ supplier.open_orders_count ?? 0 }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        v-if="can('suppliers.update')"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Edit supplier"
                                        @click="openForm(supplier)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        v-if="can('suppliers.delete')"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Delete supplier"
                                        @click="deleting = supplier"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="suppliers.data.length === 0">
                            <td colspan="4" class="px-4 py-16 text-center">
                                <Truck class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                                <p class="font-medium">No suppliers found</p>
                                <p class="mt-1 text-sm text-muted-foreground">Add the companies you buy from to start issuing purchase orders.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="suppliers.meta" />
        </div>

        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form class="space-y-5" @submit.prevent="save">
                    <DialogHeader>
                        <DialogTitle>{{ editing ? `Edit ${editing.name}` : 'New supplier' }}</DialogTitle>
                    </DialogHeader>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="s-kind">Type</Label>
                            <select id="s-kind" v-model="form.kind" :class="selectClass">
                                <option value="company">Company</option>
                                <option value="person">Person</option>
                            </select>
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="s-name">{{ form.kind === 'company' ? 'Company name' : 'Full name' }}</Label>
                            <Input id="s-name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="s-tax">Tax ID</Label>
                            <Input id="s-tax" v-model="form.tax_id" class="font-mono" />
                            <InputError :message="form.errors.tax_id" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="s-contact">Contact person</Label>
                            <Input id="s-contact" v-model="form.contact_name" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="s-email">Email</Label>
                            <Input id="s-email" v-model="form.email" type="email" />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="s-phone">Phone</Label>
                            <Input id="s-phone" v-model="form.phone" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="s-address">Address</Label>
                            <Input id="s-address" v-model="form.address" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="grid gap-2">
                                <Label for="s-city">City</Label>
                                <Input id="s-city" v-model="form.city" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="s-country">Country</Label>
                                <Input id="s-country" v-model="form.country" maxlength="2" class="uppercase" placeholder="CO" />
                                <InputError :message="form.errors.country" />
                            </div>
                        </div>
                        <div class="grid gap-2 sm:col-span-3">
                            <Label for="s-notes">Notes</Label>
                            <textarea
                                id="s-notes"
                                v-model="form.notes"
                                rows="2"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.status" type="checkbox" true-value="active" false-value="inactive" class="rounded border-input" />
                            Active
                        </label>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="open = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">Save supplier</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="deleting !== null" @update:open="(value) => !value && (deleting = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {{ deleting?.name }}?</DialogTitle>
                    <DialogDescription>Past purchase orders keep their history. Suppliers with open orders cannot be deleted.</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="secondary" @click="deleting = null">Cancel</Button>
                    <Button variant="destructive" @click="destroy">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

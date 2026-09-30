<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney, type MoneyValue } from '@/composables/useMoney';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Building2, Plus, Search, User, Users } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

interface Customer {
    id: string;
    kind: 'company' | 'person';
    name: string;
    tax_id: string | null;
    email: string | null;
    phone: string | null;
    city: string | null;
    status: 'active' | 'inactive';
}

const props = defineProps<{
    customers: Paginated<Customer>;
    balances: Record<string, MoneyValue>;
    filters: { search: string; status: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sales', href: '/sales/orders' },
    { title: 'Customers', href: '/sales/customers' },
];

const { can } = usePermissions();

const filters = reactive({ ...props.filters });
const apply = () => {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
    router.get(route('sales.customers.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};
watch(() => filters.search, useDebounceFn(apply, 300));
watch(() => filters.status, apply);

const open = ref(false);
const form = useForm({ kind: 'company', name: '', tax_id: '', email: '', phone: '', address: '', city: '', country: '', status: 'active' });
const save = () =>
    form.post(route('sales.customers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Customers" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <HeadingSmall title="Customers" description="Who you sell to, what they bought and what they owe." />
                <Button v-if="can('customers.create')" @click="open = true"><Plus class="mr-2 h-4 w-4" /> New customer</Button>
            </div>

            <PageAlerts />

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Search name, tax ID or email" class="pl-9" aria-label="Search customers" />
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
                            <th class="px-4 py-3 font-medium">Customer</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Contact</th>
                            <th class="px-4 py-3 text-right font-medium">Balance due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="customer in customers.data"
                            :key="customer.id"
                            class="cursor-pointer hover:bg-muted/40"
                            :class="{ 'opacity-60': customer.status === 'inactive' }"
                            @click="router.visit(route('sales.customers.show', customer.id))"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-muted">
                                        <component :is="customer.kind === 'company' ? Building2 : User" class="h-4 w-4 text-muted-foreground" />
                                    </span>
                                    <div class="min-w-0">
                                        <Link :href="route('sales.customers.show', customer.id)" class="font-medium hover:underline" @click.stop>
                                            {{ customer.name }}
                                        </Link>
                                        <p class="font-mono text-xs text-muted-foreground">{{ customer.tax_id ?? 'No tax ID' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 text-muted-foreground md:table-cell">
                                <p>{{ customer.email ?? '—' }}</p>
                                <p class="text-xs">{{ [customer.phone, customer.city].filter(Boolean).join(' · ') }}</p>
                            </td>
                            <td
                                class="px-4 py-3 text-right tabular-nums"
                                :class="
                                    (balances[customer.id]?.amount ?? 0) > 0
                                        ? 'font-medium text-amber-700 dark:text-amber-300'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ formatMoney(balances[customer.id]) }}
                            </td>
                        </tr>
                        <tr v-if="customers.data.length === 0">
                            <td colspan="3" class="px-4 py-16 text-center">
                                <Users class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                                <p class="font-medium">No customers found</p>
                                <p class="mt-1 text-sm text-muted-foreground">Add your first customer to start selling.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="customers.meta" />
        </div>

        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <form class="space-y-5" @submit.prevent="save">
                    <DialogHeader><DialogTitle>New customer</DialogTitle></DialogHeader>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="c-kind">Type</Label>
                            <select id="c-kind" v-model="form.kind" :class="selectClass">
                                <option value="company">Company</option>
                                <option value="person">Person</option>
                            </select>
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="c-name">Name</Label>
                            <Input id="c-name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="c-tax">Tax ID</Label>
                            <Input id="c-tax" v-model="form.tax_id" class="font-mono" />
                            <InputError :message="form.errors.tax_id" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="c-email">Email</Label>
                            <Input id="c-email" v-model="form.email" type="email" />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="c-phone">Phone</Label>
                            <Input id="c-phone" v-model="form.phone" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="c-city">City</Label>
                            <Input id="c-city" v-model="form.city" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="c-country">Country</Label>
                            <Input id="c-country" v-model="form.country" maxlength="2" class="uppercase" placeholder="CO" />
                            <InputError :message="form.errors.country" />
                        </div>
                        <div class="grid gap-2 sm:col-span-3">
                            <Label for="c-address">Address</Label>
                            <Input id="c-address" v-model="form.address" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="open = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">Create customer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

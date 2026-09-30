<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Warehouse } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Star, Trash2, Warehouse as WarehouseIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{
    warehouses: { data: Warehouse[] };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventory', href: '/inventory/warehouses' },
    { title: 'Warehouses', href: '/inventory/warehouses' },
];

const { can } = usePermissions();
const canManage = computed(() => can('warehouses.manage'));

const open = ref(false);
const editing = ref<Warehouse | null>(null);
type WarehouseForm = {
    code: string;
    name: string;
    address: string;
    city: string;
    is_active: boolean;
    is_default: boolean;
};

const form = useForm<WarehouseForm>({ code: '', name: '', address: '', city: '', is_active: true, is_default: false });

const openForm = (warehouse: Warehouse | null) => {
    editing.value = warehouse;
    form.clearErrors();
    form.code = warehouse?.code ?? '';
    form.name = warehouse?.name ?? '';
    form.address = warehouse?.address ?? '';
    form.city = warehouse?.city ?? '';
    form.is_active = warehouse?.is_active ?? true;
    form.is_default = warehouse?.is_default ?? false;
    open.value = true;
};

const save = () => {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) {
        form.put(route('inventory.warehouses.update', editing.value.id), options);
    } else {
        form.post(route('inventory.warehouses.store'), options);
    }
};

const makeDefault = (warehouse: Warehouse) =>
    router.put(
        route('inventory.warehouses.update', warehouse.id),
        { code: warehouse.code, name: warehouse.name, address: warehouse.address, city: warehouse.city, is_active: true, is_default: true },
        { preserveScroll: true },
    );

const deleting = ref<Warehouse | null>(null);
const destroy = () =>
    router.delete(route('inventory.warehouses.destroy', deleting.value!.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Warehouses" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <HeadingSmall title="Warehouses" description="Each warehouse keeps its own stock. The default one is preselected on new documents." />
                <Button v-if="canManage" @click="openForm(null)"><Plus class="mr-2 h-4 w-4" /> New warehouse</Button>
            </div>

            <PageAlerts />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="warehouse in warehouses.data"
                    :key="warehouse.id"
                    class="flex flex-col rounded-lg border p-4"
                    :class="{ 'opacity-60': !warehouse.is_active }"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-md bg-muted">
                                <WarehouseIcon class="h-5 w-5 text-muted-foreground" />
                            </span>
                            <div>
                                <h3 class="font-medium">{{ warehouse.name }}</h3>
                                <p class="font-mono text-xs text-muted-foreground">{{ warehouse.code }}</p>
                            </div>
                        </div>
                        <span
                            v-if="warehouse.is_default"
                            class="inline-flex items-center gap-1 rounded bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300"
                        >
                            <Star class="h-3 w-3" /> Default
                        </span>
                        <span v-else-if="!warehouse.is_active" class="rounded bg-secondary px-2 py-0.5 text-xs">Inactive</span>
                    </div>
                    <p v-if="warehouse.address || warehouse.city" class="mt-3 flex items-start gap-1.5 text-sm text-muted-foreground">
                        <MapPin class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        {{ [warehouse.address, warehouse.city].filter(Boolean).join(', ') }}
                    </p>
                    <div v-if="canManage" class="mt-4 flex gap-1 border-t pt-3">
                        <Button variant="ghost" size="sm" @click="openForm(warehouse)"><Pencil class="mr-1.5 h-3.5 w-3.5" /> Edit</Button>
                        <Button v-if="!warehouse.is_default && warehouse.is_active" variant="ghost" size="sm" @click="makeDefault(warehouse)">
                            <Star class="mr-1.5 h-3.5 w-3.5" /> Make default
                        </Button>
                        <Button
                            v-if="!warehouse.is_default"
                            variant="ghost"
                            size="sm"
                            class="ml-auto"
                            aria-label="Delete warehouse"
                            @click="deleting = warehouse"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </article>
            </div>
        </div>

        <Dialog v-model:open="open">
            <DialogContent>
                <form class="space-y-5" @submit.prevent="save">
                    <DialogHeader>
                        <DialogTitle>{{ editing ? 'Edit warehouse' : 'New warehouse' }}</DialogTitle>
                    </DialogHeader>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="wh-code">Code</Label>
                            <Input id="wh-code" v-model="form.code" required maxlength="20" class="font-mono uppercase" />
                            <InputError :message="form.errors.code" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="wh-name">Name</Label>
                            <Input id="wh-name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="wh-address">Address</Label>
                            <Input id="wh-address" v-model="form.address" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="wh-city">City</Label>
                            <Input id="wh-city" v-model="form.city" />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-6 text-sm">
                        <label class="flex items-center gap-2">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-input" :disabled="editing?.is_default" />
                            Active
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="form.is_default" type="checkbox" class="rounded border-input" :disabled="editing?.is_default" />
                            Default warehouse
                        </label>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="open = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">Save</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="deleting !== null" @update:open="(value) => !value && (deleting = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {{ deleting?.name }}?</DialogTitle>
                    <DialogDescription>The warehouse will no longer be available for new documents.</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="secondary" @click="deleting = null">Cancel</Button>
                    <Button variant="destructive" @click="destroy">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

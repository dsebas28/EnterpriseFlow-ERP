<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney, marginPercent } from '@/composables/useMoney';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CategoryNode, Option, Product } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ImagePlus, Layers, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    product: { data: Product } | null;
    categories: CategoryNode[];
    currency: { code: string; decimals: number };
    statuses: Option[];
    types: Option[];
    maxImages: number;
}>();

const product = computed(() => props.product?.data ?? null);
const isEdit = computed(() => product.value !== null);
const { can } = usePermissions();
const canEdit = computed(() => (isEdit.value ? can('products.update') : can('products.create')));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Catalog', href: '/catalog/products' },
    { title: 'Products', href: '/catalog/products' },
    { title: product.value?.name ?? 'New product', href: '#' },
]);

const step = computed(() => (props.currency.decimals === 0 ? '1' : (1 / 10 ** props.currency.decimals).toFixed(props.currency.decimals)));

// Product form
const form = useForm({
    type: 'simple',
    name: product.value?.name ?? '',
    sku: product.value?.sku ?? '',
    barcode: product.value?.barcode ?? '',
    description: product.value?.description ?? '',
    category_id: product.value?.category?.id ?? null,
    cost: product.value?.cost.decimal ?? '',
    price: product.value?.price.decimal ?? '',
    tax_rate: product.value ? String(Number(product.value.tax_rate)) : '0',
    min_stock: product.value?.min_stock ?? 0,
    status: product.value?.status ?? 'active',
});

const margin = computed(() => marginPercent(String(form.cost), String(form.price)));

const submit = () => {
    if (isEdit.value) {
        form.transform((data) => {
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            const { type, ...rest } = data;
            return rest;
        }).put(route('catalog.products.update', product.value!.id), { preserveScroll: true });
    } else {
        form.post(route('catalog.products.store'));
    }
};

// Delete
const confirmDelete = ref(false);
const destroy = () => router.delete(route('catalog.products.destroy', product.value!.id));

// Images
const fileInput = ref<HTMLInputElement>();
const imageForm = useForm<{ image: File | null }>({ image: null });
const uploadImage = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    imageForm.image = file;
    imageForm.post(route('catalog.products.images.store', product.value!.id), {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            imageForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};
const removeImage = (id: number) => router.delete(route('catalog.products.images.destroy', [product.value!.id, id]), { preserveScroll: true });

// Variants
const variantOpen = ref(false);
const editingVariant = ref<Product | null>(null);
const variantForm = useForm({
    sku: '',
    barcode: '',
    cost: '',
    price: '',
    min_stock: 0,
    status: 'active',
    attributes: [{ name: '', value: '' }] as { name: string; value: string }[],
});

const openVariant = (variant: Product | null) => {
    editingVariant.value = variant;
    variantForm.clearErrors();
    variantForm.sku = variant?.sku ?? `${product.value!.sku}-`;
    variantForm.barcode = variant?.barcode ?? '';
    variantForm.cost = variant?.cost.decimal ?? product.value!.cost.decimal;
    variantForm.price = variant?.price.decimal ?? product.value!.price.decimal;
    variantForm.min_stock = variant?.min_stock ?? product.value!.min_stock;
    variantForm.status = variant?.status ?? 'active';
    variantForm.attributes = variant?.attributes
        ? Object.entries(variant.attributes).map(([name, value]) => ({ name, value }))
        : [{ name: '', value: '' }];
    variantOpen.value = true;
};

const saveVariant = () => {
    const options = { preserveScroll: true, onSuccess: () => (variantOpen.value = false) };
    if (editingVariant.value) {
        variantForm.put(route('catalog.products.variants.update', [product.value!.id, editingVariant.value.id]), options);
    } else {
        variantForm.post(route('catalog.products.variants.store', product.value!.id), options);
    }
};

const deletingVariant = ref<Product | null>(null);
const destroyVariant = () =>
    router.delete(route('catalog.products.variants.destroy', [product.value!.id, deletingVariant.value!.id]), {
        preserveScroll: true,
        onFinish: () => (deletingVariant.value = null),
    });

const fieldClass =
    'flex w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-50';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="product?.name ?? 'New product'" />

        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" as-child>
                        <Link :href="route('catalog.products.index')" aria-label="Back to products"><ArrowLeft class="h-4 w-4" /></Link>
                    </Button>
                    <HeadingSmall
                        :title="product?.name ?? 'New product'"
                        :description="product ? `SKU ${product.sku}` : 'Add an item to your catalogue.'"
                    />
                </div>
                <Button v-if="isEdit && can('products.delete')" variant="outline" class="text-red-600" @click="confirmDelete = true">
                    <Trash2 class="mr-2 h-4 w-4" /> Delete
                </Button>
            </div>

            <PageAlerts />

            <div v-if="can('audit.view') && product" class="-mt-3 text-right">
                <Link :href="route('audit.index', { type: 'product', id: product.id })" class="text-xs text-muted-foreground hover:text-foreground">
                    View change history
                </Link>
            </div>

            <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="submit">
                <fieldset :disabled="!canEdit" class="space-y-6 lg:col-span-2">
                    <section class="space-y-4 rounded-lg border p-5">
                        <h3 class="font-medium">General</h3>

                        <div v-if="!isEdit" class="grid gap-2">
                            <Label>Type</Label>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="type in types"
                                    :key="type.value"
                                    class="flex cursor-pointer items-start gap-3 rounded-md border p-3 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                                >
                                    <input v-model="form.type" type="radio" :value="type.value" class="mt-0.5" />
                                    <span>
                                        <span class="font-medium">{{ type.label }}</span>
                                        <span class="block text-muted-foreground">
                                            {{
                                                type.value === 'simple'
                                                    ? 'A single item with its own stock.'
                                                    : 'A template with options like size or colour.'
                                            }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <InputError :message="form.errors.type" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="sku">SKU</Label>
                                <Input id="sku" v-model="form.sku" required class="font-mono uppercase" />
                                <InputError :message="form.errors.sku" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="barcode">Barcode</Label>
                                <Input id="barcode" v-model="form.barcode" class="font-mono" />
                                <InputError :message="form.errors.barcode" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="category">Category</Label>
                            <select id="category" v-model="form.category_id" :class="[fieldClass, 'h-9']">
                                <option :value="null">No category</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ '  '.repeat(category.depth) }}{{ category.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.category_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">Description</Label>
                            <textarea id="description" v-model="form.description" rows="4" :class="[fieldClass, 'py-2']" />
                            <InputError :message="form.errors.description" />
                        </div>
                    </section>

                    <section class="space-y-4 rounded-lg border p-5">
                        <div class="flex items-baseline justify-between">
                            <h3 class="font-medium">Pricing</h3>
                            <span class="text-xs text-muted-foreground">Amounts in {{ currency.code }}</span>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="cost">Cost</Label>
                                <Input id="cost" v-model="form.cost" type="number" min="0" :step="step" required class="tabular-nums" />
                                <InputError :message="form.errors.cost" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="price">Price</Label>
                                <Input id="price" v-model="form.price" type="number" min="0" :step="step" required class="tabular-nums" />
                                <InputError :message="form.errors.price" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tax_rate">Tax (%)</Label>
                                <Input
                                    id="tax_rate"
                                    v-model="form.tax_rate"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    required
                                    class="tabular-nums"
                                />
                                <InputError :message="form.errors.tax_rate" />
                            </div>
                        </div>
                        <p v-if="margin !== null" class="text-sm text-muted-foreground">
                            Gross margin:
                            <span :class="Number(margin) < 0 ? 'font-medium text-red-600' : 'font-medium text-foreground'">{{ margin }}%</span>
                        </p>
                    </section>
                </fieldset>

                <fieldset :disabled="!canEdit" class="space-y-6">
                    <section class="space-y-4 rounded-lg border p-5">
                        <h3 class="font-medium">Status & stock</h3>
                        <div class="grid gap-2">
                            <Label for="status">Status</Label>
                            <select id="status" v-model="form.status" :class="[fieldClass, 'h-9']">
                                <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="min_stock">Minimum stock</Label>
                            <Input id="min_stock" v-model.number="form.min_stock" type="number" min="0" step="1" required />
                            <p class="text-xs text-muted-foreground">You will be alerted when stock falls below this level.</p>
                            <InputError :message="form.errors.min_stock" />
                        </div>
                    </section>

                    <Button v-if="canEdit" type="submit" class="w-full" :disabled="form.processing">
                        {{ isEdit ? 'Save changes' : 'Create product' }}
                    </Button>
                </fieldset>
            </form>

            <!-- Images -->
            <section v-if="product" class="space-y-4 rounded-lg border p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-medium">Images</h3>
                        <p class="text-sm text-muted-foreground">JPG, PNG or WebP, up to 2 MB. {{ product.images?.length ?? 0 }} / {{ maxImages }}</p>
                    </div>
                    <template v-if="can('products.update') && (product.images?.length ?? 0) < maxImages">
                        <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadImage" />
                        <Button variant="outline" size="sm" :disabled="imageForm.processing" @click="fileInput?.click()">
                            <ImagePlus class="mr-2 h-4 w-4" /> {{ imageForm.processing ? 'Uploading…' : 'Upload' }}
                        </Button>
                    </template>
                </div>
                <InputError :message="imageForm.errors.image" />
                <div v-if="product.images?.length" class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
                    <div v-for="image in product.images" :key="image.id" class="group relative aspect-square overflow-hidden rounded-md border">
                        <img :src="image.url" :alt="product.name" class="h-full w-full object-cover" />
                        <button
                            v-if="can('products.update')"
                            type="button"
                            class="absolute right-1 top-1 rounded-full bg-black/60 p-1 text-white opacity-0 transition focus:opacity-100 group-hover:opacity-100"
                            aria-label="Remove image"
                            @click="removeImage(image.id)"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">No images yet.</p>
            </section>

            <!-- Variants -->
            <section v-if="product?.type === 'variable'" class="space-y-4 rounded-lg border p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="flex items-center gap-2 font-medium"><Layers class="h-4 w-4" /> Variants</h3>
                        <p class="text-sm text-muted-foreground">Each variant is a sellable item with its own SKU, price and stock.</p>
                    </div>
                    <Button v-if="can('products.update')" variant="outline" size="sm" @click="openVariant(null)">
                        <Plus class="mr-2 h-4 w-4" /> Add variant
                    </Button>
                </div>
                <div v-if="product.variants?.length" class="overflow-x-auto rounded-md border">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 font-medium">Options</th>
                                <th class="px-3 py-2 font-medium">SKU</th>
                                <th class="px-3 py-2 text-right font-medium">Price</th>
                                <th class="px-3 py-2 font-medium">Status</th>
                                <th class="px-3 py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="variant in product.variants" :key="variant.id">
                                <td class="px-3 py-2">
                                    <span
                                        v-for="(value, key) in variant.attributes"
                                        :key="key"
                                        class="mr-1 inline-block rounded bg-secondary px-1.5 py-0.5 text-xs"
                                    >
                                        <span class="capitalize text-muted-foreground">{{ key }}:</span> {{ value }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-mono text-xs">{{ variant.sku }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(variant.price) }}</td>
                                <td class="px-3 py-2 text-xs capitalize">{{ variant.status }}</td>
                                <td class="px-3 py-2 text-right">
                                    <template v-if="can('products.update')">
                                        <Button variant="ghost" size="sm" @click="openVariant(variant)">Edit</Button>
                                        <Button variant="ghost" size="sm" aria-label="Delete variant" @click="deletingVariant = variant">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                    No variants yet. Add options such as size or colour to start selling this product.
                </p>
            </section>
        </div>

        <!-- Variant dialog -->
        <Dialog v-model:open="variantOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <form class="space-y-5" @submit.prevent="saveVariant">
                    <DialogHeader>
                        <DialogTitle>{{ editingVariant ? 'Edit variant' : 'New variant' }}</DialogTitle>
                        <DialogDescription>Category and tax are inherited from {{ product?.name }}.</DialogDescription>
                    </DialogHeader>

                    <div class="space-y-2">
                        <Label>Options</Label>
                        <div v-for="(attribute, index) in variantForm.attributes" :key="index" class="flex gap-2">
                            <Input v-model="attribute.name" placeholder="Size" aria-label="Option name" />
                            <Input v-model="attribute.value" placeholder="M" aria-label="Option value" />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="variantForm.attributes.length === 1"
                                aria-label="Remove option"
                                @click="variantForm.attributes.splice(index, 1)"
                            >
                                <X class="h-4 w-4" />
                            </Button>
                        </div>
                        <Button
                            v-if="variantForm.attributes.length < 5"
                            type="button"
                            variant="link"
                            size="sm"
                            class="px-0"
                            @click="variantForm.attributes.push({ name: '', value: '' })"
                        >
                            + Add option
                        </Button>
                        <InputError
                            :message="
                                variantForm.errors.attributes ??
                                Object.entries(variantForm.errors).find(([key]) => key.startsWith('attributes.'))?.[1]
                            "
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="v-sku">SKU</Label>
                            <Input id="v-sku" v-model="variantForm.sku" required class="font-mono uppercase" />
                            <InputError :message="variantForm.errors.sku" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="v-barcode">Barcode</Label>
                            <Input id="v-barcode" v-model="variantForm.barcode" class="font-mono" />
                            <InputError :message="variantForm.errors.barcode" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="v-cost">Cost</Label>
                            <Input id="v-cost" v-model="variantForm.cost" type="number" min="0" :step="step" required />
                            <InputError :message="variantForm.errors.cost" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="v-price">Price</Label>
                            <Input id="v-price" v-model="variantForm.price" type="number" min="0" :step="step" required />
                            <InputError :message="variantForm.errors.price" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="v-min">Minimum stock</Label>
                            <Input id="v-min" v-model.number="variantForm.min_stock" type="number" min="0" required />
                            <InputError :message="variantForm.errors.min_stock" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="v-status">Status</Label>
                            <select id="v-status" v-model="variantForm.status" :class="[fieldClass, 'h-9']">
                                <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                            </select>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="variantOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="variantForm.processing">Save variant</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete variant confirmation -->
        <Dialog :open="deletingVariant !== null" @update:open="(open) => !open && (deletingVariant = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete variant {{ deletingVariant?.sku }}?</DialogTitle>
                    <DialogDescription
                        >It will no longer be available for new purchases or sales. Past documents keep their history.</DialogDescription
                    >
                </DialogHeader>
                <DialogFooter>
                    <Button variant="secondary" @click="deletingVariant = null">Cancel</Button>
                    <Button variant="destructive" @click="destroyVariant">Delete variant</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete product confirmation -->
        <Dialog v-model:open="confirmDelete">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {{ product?.name }}?</DialogTitle>
                    <DialogDescription>
                        The product{{ product?.type === 'variable' ? ' and all its variants' : '' }} will be removed from the catalogue. Its SKU stays
                        reserved and past documents keep their history.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="secondary" @click="confirmDelete = false">Cancel</Button>
                    <Button variant="destructive" @click="destroy">Delete product</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

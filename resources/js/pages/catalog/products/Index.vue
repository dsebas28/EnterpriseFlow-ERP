<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/composables/useMoney';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CategoryNode, Paginated, Product } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ImageOff, Layers, PackageSearch, Plus, Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

const props = defineProps<{
    products: Paginated<Product>;
    filters: { search?: string; category_id?: string; status?: string; type?: string; sort?: string };
    categories: CategoryNode[];
    sorts: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Catalog', href: '/catalog/products' },
    { title: 'Products', href: '/catalog/products' },
];

const { can } = usePermissions();

const filters = reactive({
    search: props.filters.search ?? '',
    category_id: props.filters.category_id ?? '',
    status: props.filters.status ?? '',
    type: props.filters.type ?? '',
    sort: props.filters.sort ?? '',
});

// The URL is the source of truth: filters survive reloads and are shareable.
const apply = () => {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
    router.get(route('catalog.products.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};
const applyDebounced = useDebounceFn(apply, 300);

watch(() => filters.search, applyDebounced);
watch(() => [filters.category_id, filters.status, filters.type, filters.sort], apply);

const hasFilters = () => Object.values(filters).some((value) => value !== '');
const clearFilters = () => {
    Object.assign(filters, { search: '', category_id: '', status: '', type: '', sort: '' });
};

const sortLabels: Record<string, string> = {
    '': 'Newest first',
    name: 'Name A–Z',
    '-name': 'Name Z–A',
    sku: 'SKU',
    price: 'Price: low to high',
    '-price': 'Price: high to low',
    created: 'Oldest first',
};

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Products" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <HeadingSmall title="Products" description="Everything you buy, stock and sell." />
                <Button v-if="can('products.create')" as-child>
                    <Link :href="route('catalog.products.create')"><Plus class="mr-2 h-4 w-4" /> New product</Link>
                </Button>
            </div>

            <PageAlerts />

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Search name, SKU or barcode" class="pl-9" aria-label="Search products" />
                </div>
                <select v-model="filters.category_id" :class="selectClass" aria-label="Category">
                    <option value="">All categories</option>
                    <option v-for="category in categories" :key="category.id" :value="String(category.id)">
                        {{ '  '.repeat(category.depth) }}{{ category.name }}
                    </option>
                </select>
                <select v-model="filters.status" :class="selectClass" aria-label="Status">
                    <option value="">Any status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select v-model="filters.type" :class="selectClass" aria-label="Type">
                    <option value="">Any type</option>
                    <option value="simple">Simple</option>
                    <option value="variable">With variants</option>
                </select>
                <select v-model="filters.sort" :class="selectClass" aria-label="Sort">
                    <option v-for="(label, value) in sortLabels" :key="value" :value="value">{{ label }}</option>
                </select>
                <Button v-if="hasFilters()" variant="ghost" size="sm" @click="clearFilters">Clear</Button>
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Category</th>
                            <th class="px-4 py-3 text-right font-medium">Price</th>
                            <th class="hidden px-4 py-3 text-right font-medium sm:table-cell">Tax</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="product in products.data"
                            :key="product.id"
                            class="cursor-pointer hover:bg-muted/40"
                            @click="router.visit(route('catalog.products.edit', product.id))"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted">
                                        <img
                                            v-if="product.images?.length"
                                            :src="product.images[0].url"
                                            :alt="product.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <ImageOff v-else class="h-4 w-4 text-muted-foreground" />
                                    </div>
                                    <div class="min-w-0">
                                        <Link :href="route('catalog.products.edit', product.id)" class="font-medium hover:underline" @click.stop>
                                            {{ product.name }}
                                        </Link>
                                        <p class="flex items-center gap-2 font-mono text-xs text-muted-foreground">
                                            {{ product.sku }}
                                            <span
                                                v-if="product.type === 'variable'"
                                                class="inline-flex items-center gap-1 rounded bg-secondary px-1.5 py-0.5 font-sans text-[11px] text-secondary-foreground"
                                            >
                                                <Layers class="h-3 w-3" /> {{ product.variants_count }} variants
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 text-muted-foreground md:table-cell">{{ product.category?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(product.price) }}</td>
                            <td class="hidden px-4 py-3 text-right tabular-nums text-muted-foreground sm:table-cell">
                                {{ Number(product.tax_rate) }}%
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 text-xs font-medium"
                                    :class="product.status === 'active' ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="product.status === 'active' ? 'bg-emerald-500' : 'bg-zinc-400'" />
                                    {{ product.status === 'active' ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="products.data.length === 0">
                            <td colspan="5" class="px-4 py-16 text-center">
                                <PackageSearch class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                                <p class="font-medium">{{ hasFilters() ? 'No products match these filters' : 'No products yet' }}</p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{
                                        hasFilters()
                                            ? 'Try a different search or clear the filters.'
                                            : 'Create your first product to start tracking stock.'
                                    }}
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="products.meta" />
        </div>
    </AppLayout>
</template>

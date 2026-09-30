<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { onClickOutside, useDebounceFn } from '@vueuse/core';
import { Loader2, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

export interface PickedProduct {
    id: string;
    sku: string;
    name: string;
    cost: string;
    price: string;
    tax_rate: string;
}

const props = defineProps<{
    excludeIds?: string[];
    placeholder?: string;
}>();

const emit = defineEmits<{ pick: [product: PickedProduct] }>();

const term = ref('');
const results = ref<PickedProduct[]>([]);
const open = ref(false);
const loading = ref(false);
const highlighted = ref(0);
const root = ref<HTMLElement>();

onClickOutside(root, () => (open.value = false));

const search = useDebounceFn(async () => {
    loading.value = true;
    try {
        const response = await fetch(route('catalog.products.lookup', { q: term.value }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const products: PickedProduct[] = response.ok ? await response.json() : [];
        results.value = products.filter((p) => !props.excludeIds?.includes(p.id));
        highlighted.value = 0;
        open.value = true;
    } finally {
        loading.value = false;
    }
}, 250);

watch(term, (value) => {
    if (value.trim().length === 0) {
        open.value = false;
        return;
    }
    search();
});

const pick = (product: PickedProduct) => {
    emit('pick', product);
    term.value = '';
    open.value = false;
};

const onKeydown = (event: KeyboardEvent) => {
    if (!open.value || results.value.length === 0) return;
    if (event.key === 'ArrowDown') {
        highlighted.value = (highlighted.value + 1) % results.value.length;
        event.preventDefault();
    } else if (event.key === 'ArrowUp') {
        highlighted.value = (highlighted.value - 1 + results.value.length) % results.value.length;
        event.preventDefault();
    } else if (event.key === 'Enter') {
        pick(results.value[highlighted.value]);
        event.preventDefault();
    } else if (event.key === 'Escape') {
        open.value = false;
    }
};
</script>

<template>
    <div ref="root" class="relative">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
            v-model="term"
            :placeholder="placeholder ?? 'Add a product by name, SKU or barcode…'"
            class="pl-9"
            role="combobox"
            :aria-expanded="open"
            aria-autocomplete="list"
            @keydown="onKeydown"
            @focus="term && (open = true)"
        />
        <Loader2 v-if="loading" class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-muted-foreground" />

        <ul v-if="open" class="absolute z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-popover p-1 shadow-lg" role="listbox">
            <li
                v-for="(product, index) in results"
                :key="product.id"
                role="option"
                :aria-selected="index === highlighted"
                class="flex cursor-pointer items-center justify-between gap-3 rounded px-3 py-2 text-sm"
                :class="index === highlighted ? 'bg-accent text-accent-foreground' : ''"
                @mouseenter="highlighted = index"
                @mousedown.prevent="pick(product)"
            >
                <span class="min-w-0">
                    <span class="block truncate font-medium">{{ product.name }}</span>
                    <span class="font-mono text-xs text-muted-foreground">{{ product.sku }}</span>
                </span>
            </li>
            <li v-if="results.length === 0" class="px-3 py-2 text-sm text-muted-foreground">No matching products.</li>
        </ul>
    </div>
</template>

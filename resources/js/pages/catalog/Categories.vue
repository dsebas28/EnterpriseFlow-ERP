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
import type { BreadcrumbItem, CategoryNode } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CornerDownRight, FolderTree, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    categories: CategoryNode[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Catalog', href: '/catalog/products' },
    { title: 'Categories', href: '/catalog/categories' },
];

const { can } = usePermissions();
const canManage = computed(() => can('categories.manage'));

const open = ref(false);
const editing = ref<CategoryNode | null>(null);
const form = useForm<{ name: string; parent_id: number | null; description: string }>({ name: '', parent_id: null, description: '' });

// A category cannot be moved under itself or its descendants (the server enforces this too).
const descendantsOf = (id: number): Set<number> => {
    const result = new Set<number>([id]);
    let grew = true;
    while (grew) {
        grew = false;
        for (const category of props.categories) {
            if (category.parent_id !== null && result.has(category.parent_id) && !result.has(category.id)) {
                result.add(category.id);
                grew = true;
            }
        }
    }
    return result;
};

const parentOptions = computed(() => {
    const excluded = editing.value ? descendantsOf(editing.value.id) : new Set<number>();
    return props.categories.filter((category) => !excluded.has(category.id));
});

const openForm = (category: CategoryNode | null, parentId: number | null = null) => {
    editing.value = category;
    form.clearErrors();
    form.name = category?.name ?? '';
    form.parent_id = category ? category.parent_id : parentId;
    form.description = category?.description ?? '';
    open.value = true;
};

const save = () => {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) {
        form.put(route('catalog.categories.update', editing.value.id), options);
    } else {
        form.post(route('catalog.categories.store'), options);
    }
};

const deleting = ref<CategoryNode | null>(null);
const destroy = () =>
    router.delete(route('catalog.categories.destroy', deleting.value!.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Categories" />

        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <HeadingSmall title="Categories" description="Organise the catalogue. Filtering by a category includes its subcategories." />
                <Button v-if="canManage" @click="openForm(null)"><Plus class="mr-2 h-4 w-4" /> New category</Button>
            </div>

            <PageAlerts />

            <ul v-if="categories.length" class="divide-y rounded-lg border">
                <li v-for="category in categories" :key="category.id" class="group flex items-center gap-3 px-4 py-3">
                    <div class="flex min-w-0 flex-1 items-center gap-2" :style="{ paddingLeft: `${category.depth * 1.5}rem` }">
                        <CornerDownRight v-if="category.depth > 0" class="h-4 w-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0">
                            <p class="font-medium">{{ category.name }}</p>
                            <p v-if="category.description" class="truncate text-sm text-muted-foreground">{{ category.description }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 text-xs tabular-nums text-muted-foreground">
                        {{ category.products_count }} {{ category.products_count === 1 ? 'product' : 'products' }}
                    </span>
                    <div v-if="canManage" class="flex shrink-0 gap-1">
                        <Button variant="ghost" size="icon" aria-label="Add subcategory" @click="openForm(null, category.id)">
                            <Plus class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" aria-label="Edit category" @click="openForm(category)">
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" aria-label="Delete category" @click="deleting = category">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </li>
            </ul>
            <div v-else class="rounded-lg border border-dashed p-12 text-center">
                <FolderTree class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                <p class="font-medium">No categories yet</p>
                <p class="mt-1 text-sm text-muted-foreground">Group products to make them easier to find and report on.</p>
            </div>
        </div>

        <Dialog v-model:open="open">
            <DialogContent>
                <form class="space-y-5" @submit.prevent="save">
                    <DialogHeader>
                        <DialogTitle>{{ editing ? 'Edit category' : 'New category' }}</DialogTitle>
                    </DialogHeader>
                    <div class="grid gap-2">
                        <Label for="cat-name">Name</Label>
                        <Input id="cat-name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="cat-parent">Parent</Label>
                        <select
                            id="cat-parent"
                            v-model="form.parent_id"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option :value="null">None (top level)</option>
                            <option v-for="option in parentOptions" :key="option.id" :value="option.id">
                                {{ '  '.repeat(option.depth) }}{{ option.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.parent_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="cat-description">Description</Label>
                        <Input id="cat-description" v-model="form.description" />
                        <InputError :message="form.errors.description" />
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
                    <DialogTitle>Delete “{{ deleting?.name }}”?</DialogTitle>
                    <DialogDescription>Only empty categories without subcategories can be deleted.</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="secondary" @click="deleting = null">Cancel</Button>
                    <Button variant="destructive" @click="destroy">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

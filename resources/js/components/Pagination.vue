<script setup lang="ts">
import type { Paginated } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    meta: Paginated<unknown>['meta'];
}>();
</script>

<template>
    <div v-if="meta.total > 0" class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p class="text-muted-foreground">
            Showing <span class="font-medium text-foreground">{{ meta.from }}</span
            >–<span class="font-medium text-foreground">{{ meta.to }}</span> of <span class="font-medium text-foreground">{{ meta.total }}</span>
        </p>
        <nav v-if="meta.last_page > 1" class="flex flex-wrap items-center gap-1" aria-label="Pagination">
            <template v-for="link in meta.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    class="rounded-md px-3 py-1.5"
                    :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                >
                    <!-- Labels come from the server paginator ("&laquo; Previous"), never from user input. -->
                    <span v-html="link.label" />
                </Link>
                <span v-else class="px-3 py-1.5 text-muted-foreground" v-html="link.label" />
            </template>
        </nav>
    </div>
</template>

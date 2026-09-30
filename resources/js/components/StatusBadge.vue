<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    status: string;
    label: string;
}>();

// One visual language for document states across modules.
const tone = computed(() => {
    switch (props.status) {
        case 'draft':
            return 'bg-zinc-500/10 text-zinc-700 dark:text-zinc-300';
        case 'pending':
        case 'issued':
            return 'bg-sky-500/10 text-sky-700 dark:text-sky-300';
        case 'approved':
        case 'confirmed':
            return 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300';
        case 'partially_received':
        case 'partially_paid':
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
        case 'received':
        case 'paid':
            return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
        case 'cancelled':
        case 'overdue':
            return 'bg-red-500/10 text-red-700 dark:text-red-300';
        default:
            return 'bg-secondary text-secondary-foreground';
    }
});
</script>

<template>
    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium" :class="tone">{{ label }}</span>
</template>

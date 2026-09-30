<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import { AlertCircle, Download, Loader2 } from 'lucide-vue-next';
import { computed, watch } from 'vue';

export interface ExportRow {
    id: string;
    report: string;
    format: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    rows_count: number | null;
    error: string | null;
    created_at: string;
}

const props = defineProps<{ exports: ExportRow[]; showReport?: boolean }>();

// Poll only the exports prop while something is still being generated.
const busy = computed(() => props.exports.some((e) => e.status === 'pending' || e.status === 'processing'));
const { pause, resume } = useIntervalFn(() => router.reload({ only: ['exports'] }), 3000, { immediate: false });
watch(busy, (value) => (value ? resume() : pause()), { immediate: true });

const formatDateTime = (value: string) => new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <ul v-if="exports.length" class="divide-y rounded-lg border text-sm">
        <li v-for="item in exports" :key="item.id" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
            <span>
                <span class="font-mono text-xs uppercase">{{ item.format }}</span>
                <span v-if="showReport" class="ml-2">{{ item.report }}</span>
                <span class="ml-2 text-muted-foreground">{{ formatDateTime(item.created_at) }}</span>
            </span>
            <a
                v-if="item.status === 'completed'"
                :href="route('reports.exports.download', item.id)"
                class="inline-flex items-center gap-1.5 font-medium text-primary hover:underline"
            >
                <Download class="h-4 w-4" /> Download ({{ item.rows_count }} rows)
            </a>
            <span v-else-if="item.status === 'failed'" class="inline-flex items-center gap-1.5 text-red-600" :title="item.error ?? ''">
                <AlertCircle class="h-4 w-4" /> {{ item.error ?? 'Failed' }}
            </span>
            <span v-else class="inline-flex items-center gap-1.5 text-muted-foreground"><Loader2 class="h-4 w-4 animate-spin" /> Preparing…</span>
        </li>
    </ul>
</template>

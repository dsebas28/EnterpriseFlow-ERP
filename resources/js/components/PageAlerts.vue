<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Flash status and business-rule errors (422 "rule") returned by the backend.
 */
const page = usePage<SharedData>();
const status = computed(() => page.props.flash?.status);
const ruleError = computed(() => (page.props.errors as Record<string, string> | undefined)?.rule);
</script>

<template>
    <div v-if="status || ruleError" class="space-y-2" role="status">
        <p
            v-if="status"
            class="flex items-center gap-2 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-700 dark:text-emerald-300"
        >
            <CircleCheck class="h-4 w-4 shrink-0" /> {{ status }}
        </p>
        <p
            v-if="ruleError"
            class="flex items-center gap-2 rounded-md border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-700 dark:text-red-300"
        >
            <CircleAlert class="h-4 w-4 shrink-0" /> {{ ruleError }}
        </p>
    </div>
</template>

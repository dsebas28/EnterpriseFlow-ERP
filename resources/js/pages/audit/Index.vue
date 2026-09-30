<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, ScrollText, X } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

interface AuditRow {
    id: number;
    event: string;
    type: string;
    subject_id: string;
    user: string | null;
    changes: { field: string; old: unknown; new: unknown }[];
    ip_address: string | null;
    user_agent: string | null;
    method: string | null;
    url: string | null;
    created_at: string;
}

type LaravelPaginator<T> = {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    logs: LaravelPaginator<AuditRow>;
    filters: { type?: string; id?: string; user_id?: number; event?: string; from?: string; to?: string };
    types: string[];
    events: string[];
    users: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Team', href: '/team/members' },
    { title: 'Audit trail', href: '/audit' },
];

const filters = reactive({
    type: props.filters.type ?? '',
    id: props.filters.id ?? '',
    user_id: props.filters.user_id ? String(props.filters.user_id) : '',
    event: props.filters.event ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});
watch(
    () => ({ ...filters }),
    () => {
        const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
        router.get(route('audit.index'), query, { preserveState: true, preserveScroll: true, replace: true });
    },
);

const expanded = ref<Set<number>>(new Set());
const toggle = (id: number) => {
    const next = new Set(expanded.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    expanded.value = next;
};

const eventTone = (event: string) => {
    if (event === 'created' || event === 'restored' || event.endsWith('reactivated'))
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    if (event === 'deleted' || event.endsWith('suspended')) return 'bg-red-500/10 text-red-700 dark:text-red-300';
    if (event === 'updated') return 'bg-sky-500/10 text-sky-700 dark:text-sky-300';
    return 'bg-violet-500/10 text-violet-700 dark:text-violet-300';
};

const show = (value: unknown) => {
    if (value === null || value === undefined) return '∅';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
};

const paginationMeta = () => ({ ...props.logs, links: props.logs.links });
const formatDateTime = (value: string) => new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'medium' });
const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Audit trail" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <HeadingSmall
                title="Audit trail"
                description="Who changed what, when and from where. Entries are append-only: nobody, including administrators, can edit or delete them."
            />

            <div class="flex flex-wrap items-center gap-2">
                <select v-model="filters.type" :class="selectClass" aria-label="Record type">
                    <option value="">All record types</option>
                    <option v-for="type in types" :key="type" :value="type">{{ type.replace('_', ' ') }}</option>
                </select>
                <select v-model="filters.event" :class="selectClass" aria-label="Event">
                    <option value="">All events</option>
                    <option v-for="event in events" :key="event" :value="event">{{ event }}</option>
                </select>
                <select v-model="filters.user_id" :class="selectClass" aria-label="User">
                    <option value="">Anyone</option>
                    <option v-for="user in users" :key="user.id" :value="String(user.id)">{{ user.name }}</option>
                </select>
                <Input v-model="filters.from" type="date" class="w-auto" aria-label="From date" />
                <Input v-model="filters.to" type="date" class="w-auto" aria-label="To date" />
                <span v-if="filters.id" class="inline-flex h-9 items-center gap-2 rounded-md border bg-secondary px-3 text-sm">
                    Record <span class="font-mono text-xs">{{ filters.id }}</span>
                    <button type="button" aria-label="Clear record filter" @click="filters.id = ''"><X class="h-3.5 w-3.5" /></button>
                </span>
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="w-8 px-2 py-3"></th>
                            <th class="px-4 py-3 font-medium">When</th>
                            <th class="px-4 py-3 font-medium">Event</th>
                            <th class="px-4 py-3 font-medium">Record</th>
                            <th class="px-4 py-3 font-medium">User</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">Changed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <template v-for="log in logs.data" :key="log.id">
                            <tr class="cursor-pointer hover:bg-muted/30" @click="toggle(log.id)">
                                <td class="px-2 py-3 text-muted-foreground">
                                    <component :is="expanded.has(log.id) ? ChevronDown : ChevronRight" class="h-4 w-4" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">{{ formatDateTime(log.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded px-1.5 py-0.5 font-mono text-xs" :class="eventTone(log.event)">{{ log.event }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="capitalize">{{ log.type.replace('_', ' ') }}</span>
                                    <button
                                        type="button"
                                        class="ml-1 font-mono text-xs text-muted-foreground hover:underline"
                                        @click.stop="
                                            filters.type = log.type;
                                            filters.id = log.subject_id;
                                        "
                                    >
                                        {{ log.subject_id.slice(-8) }}
                                    </button>
                                </td>
                                <td class="px-4 py-3">{{ log.user ?? 'System' }}</td>
                                <td class="hidden max-w-xs truncate px-4 py-3 text-xs text-muted-foreground md:table-cell">
                                    {{ log.changes.map((c) => c.field).join(', ') || '—' }}
                                </td>
                            </tr>
                            <tr v-if="expanded.has(log.id)" class="bg-muted/20">
                                <td></td>
                                <td colspan="5" class="px-4 py-3">
                                    <table v-if="log.changes.length" class="w-full max-w-3xl text-xs">
                                        <thead class="text-muted-foreground">
                                            <tr>
                                                <th class="py-1 pr-4 text-left font-medium">Field</th>
                                                <th class="py-1 pr-4 text-left font-medium">Before</th>
                                                <th class="py-1 text-left font-medium">After</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="change in log.changes" :key="change.field" class="align-top">
                                                <td class="py-1 pr-4 font-mono">{{ change.field }}</td>
                                                <td class="break-all py-1 pr-4 text-red-700 dark:text-red-300">{{ show(change.old) }}</td>
                                                <td class="break-all py-1 text-emerald-700 dark:text-emerald-300">{{ show(change.new) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <p class="mt-2 text-xs text-muted-foreground">
                                        {{ log.method }} {{ log.url }} · IP {{ log.ip_address ?? '—' }}
                                        <span v-if="log.user_agent" class="block truncate">{{ log.user_agent }}</span>
                                    </p>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="logs.data.length === 0">
                            <td colspan="6" class="px-4 py-16 text-center">
                                <ScrollText class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                                <p class="font-medium">No audit entries match these filters</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="paginationMeta()" />
        </div>
    </AppLayout>
</template>

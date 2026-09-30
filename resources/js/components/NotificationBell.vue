<script setup lang="ts">
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const POLL_MS = 60_000;

const page = usePage<SharedData>();
const count = ref(page.props.unreadNotifications ?? 0);

// Every Inertia visit refreshes the shared prop; the poll covers idle tabs.
watch(
    () => page.props.unreadNotifications,
    (value) => {
        if (typeof value === 'number') count.value = value;
    },
);

let timer: ReturnType<typeof setInterval> | undefined;

async function refresh() {
    if (document.visibilityState !== 'visible') return;

    try {
        const response = await fetch(route('notifications.unread-count'), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (response.ok) count.value = ((await response.json()) as { count: number }).count;
    } catch {
        // Offline or session expired: keep the last known count.
    }
}

onMounted(() => {
    timer = setInterval(refresh, POLL_MS);
    document.addEventListener('visibilitychange', refresh);
});

onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', refresh);
});

const label = computed(() => (count.value > 0 ? `Notifications (${count.value} unread)` : 'Notifications'));
const badge = computed(() => (count.value > 99 ? '99+' : String(count.value)));
</script>

<template>
    <Link
        v-if="page.props.unreadNotifications !== null"
        :href="route('notifications.index')"
        class="relative inline-flex size-9 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
        :aria-label="label"
        :title="label"
    >
        <Bell class="size-5" />
        <span
            v-if="count > 0"
            class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-none text-white"
            aria-hidden="true"
        >
            {{ badge }}
        </span>
    </Link>
</template>

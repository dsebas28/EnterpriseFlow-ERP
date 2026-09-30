<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { relativeTime } from '@/lib/relativeTime';
import type { AppNotification, BreadcrumbItem, NotificationLevel, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { BellOff, CircleAlert, CircleCheck, Info, TriangleAlert, type LucideIcon } from 'lucide-vue-next';

defineProps<{
    notifications: Paginated<AppNotification>;
    filter: 'all' | 'unread';
    unread: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifications', href: '/notifications' }];

const levels: Record<NotificationLevel, { icon: LucideIcon; class: string }> = {
    info: { icon: Info, class: 'text-sky-600 dark:text-sky-400' },
    success: { icon: CircleCheck, class: 'text-emerald-600 dark:text-emerald-400' },
    warning: { icon: TriangleAlert, class: 'text-amber-600 dark:text-amber-400' },
    danger: { icon: CircleAlert, class: 'text-red-600 dark:text-red-400' },
};

const tabs = [
    { value: 'all', label: 'All' },
    { value: 'unread', label: 'Unread' },
] as const;

const markRead = (notification: AppNotification) => {
    router.post(route('notifications.read', notification.id), {}, { preserveScroll: true });
};

const markAllRead = () => {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Notifications" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <HeadingSmall title="Notifications" description="What happened in this company that needs your attention." />
                <Button variant="outline" size="sm" :disabled="unread === 0" @click="markAllRead">Mark all as read</Button>
            </div>

            <PageAlerts />

            <nav class="flex gap-1 border-b" aria-label="Filter notifications">
                <Link
                    v-for="tab in tabs"
                    :key="tab.value"
                    :href="route('notifications.index', tab.value === 'unread' ? { filter: 'unread' } : {})"
                    class="-mb-px border-b-2 px-3 py-2 text-sm font-medium"
                    :class="
                        filter === tab.value ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    preserve-scroll
                >
                    {{ tab.label }}
                    <span v-if="tab.value === 'unread' && unread > 0" class="ml-1 rounded-full bg-muted px-1.5 text-xs">{{ unread }}</span>
                </Link>
            </nav>

            <div v-if="notifications.data.length === 0" class="flex flex-col items-center gap-2 py-16 text-center text-muted-foreground">
                <BellOff class="size-8" />
                <p>{{ filter === 'unread' ? 'You are all caught up.' : 'No notifications yet.' }}</p>
            </div>

            <ul v-else class="divide-y rounded-lg border">
                <li
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    class="flex items-start gap-3 p-4"
                    :class="notification.read_at ? '' : 'bg-muted/40'"
                >
                    <component :is="levels[notification.level].icon" class="mt-0.5 size-5 shrink-0" :class="levels[notification.level].class" />

                    <div class="min-w-0 flex-1">
                        <a :href="route('notifications.open', notification.id)" class="font-medium hover:underline">
                            {{ notification.title }}
                        </a>
                        <p class="mt-0.5 text-sm text-muted-foreground">{{ notification.body }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            <time :datetime="notification.created_at" :title="new Date(notification.created_at).toLocaleString()">
                                {{ relativeTime(notification.created_at) }}
                            </time>
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <span v-if="!notification.read_at" class="size-2 rounded-full bg-primary" aria-label="Unread" />
                        <Button v-if="!notification.read_at" variant="ghost" size="sm" @click="markRead(notification)">Mark read</Button>
                    </div>
                </li>
            </ul>

            <Pagination :meta="notifications.meta" />
        </div>
    </AppLayout>
</template>

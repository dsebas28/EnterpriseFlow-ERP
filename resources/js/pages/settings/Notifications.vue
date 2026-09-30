<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import PageAlerts from '@/components/PageAlerts.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface CategoryPreference {
    value: string;
    label: string;
    description: string;
    database: boolean;
    mail: boolean;
}

type PreferencesForm = {
    preferences: Record<string, { database: boolean; mail: boolean }>;
};

const props = defineProps<{
    categories: CategoryPreference[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifications', href: '/settings/notifications' }];

const form = useForm<PreferencesForm>({
    preferences: Object.fromEntries(props.categories.map((c) => [c.value, { database: c.database, mail: c.mail }])),
});

const submit = () => {
    form.put(route('notification-preferences.update'), { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Notification settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Notifications"
                    description="Choose how you hear about each kind of event. You only receive notifications your role allows you to act on."
                />

                <PageAlerts />

                <form class="space-y-6" @submit.prevent="submit">
                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-2 font-medium">Event</th>
                                    <th class="w-20 px-4 py-2 text-center font-medium">In app</th>
                                    <th class="w-20 px-4 py-2 text-center font-medium">Email</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="category in categories" :key="category.value">
                                    <td class="px-4 py-3">
                                        <p class="font-medium">{{ category.label }}</p>
                                        <p class="text-muted-foreground">{{ category.description }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <Checkbox
                                            v-model:checked="form.preferences[category.value].database"
                                            :aria-label="`${category.label}: in app`"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <Checkbox v-model:checked="form.preferences[category.value].mail" :aria-label="`${category.label}: email`" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="text-xs text-muted-foreground">Invitations to join a company are always delivered.</p>

                    <Button type="submit" :disabled="form.processing || !form.isDirty">Save preferences</Button>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

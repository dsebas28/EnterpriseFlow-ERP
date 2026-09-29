<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Monitor, Smartphone } from 'lucide-vue-next';

interface BrowserSession {
    id: string;
    ip_address: string | null;
    platform: string;
    browser: string;
    is_current: boolean;
    last_active_at: string;
}

defineProps<{
    supported: boolean;
    sessions: BrowserSession[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Sessions', href: '/settings/sessions' }];

const form = useForm({ password: '' });

const isMobile = (session: BrowserSession) => ['iOS', 'Android'].includes(session.platform);

const relativeTime = (iso: string) => {
    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) return rtf.format(Math.round(seconds / size), unit);
    }
    return rtf.format(seconds, 'second');
};

const revoke = (session: BrowserSession) => {
    router.delete(route('sessions.destroy', session.id), { preserveScroll: true });
};

const logoutOthers = () => {
    form.delete(route('sessions.destroy-others'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Browser sessions" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Browser sessions"
                    description="Review where your account is signed in and end sessions you do not recognise."
                />

                <p v-if="!supported" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                    Session management requires the <code>database</code> session driver.
                </p>

                <ul v-else class="divide-y rounded-lg border">
                    <li v-for="session in sessions" :key="session.id" class="flex items-center gap-4 p-4">
                        <component :is="isMobile(session) ? Smartphone : Monitor" class="h-6 w-6 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="font-medium">{{ session.browser }} on {{ session.platform }}</p>
                            <p class="text-muted-foreground">
                                {{ session.ip_address ?? 'Unknown IP' }} ·
                                <span v-if="session.is_current" class="font-medium text-emerald-600 dark:text-emerald-400">This device</span>
                                <span v-else>Last active {{ relativeTime(session.last_active_at) }}</span>
                            </p>
                        </div>
                        <Button v-if="!session.is_current" variant="ghost" size="sm" @click="revoke(session)">Revoke</Button>
                    </li>
                    <li v-if="sessions.length === 0" class="p-4 text-sm text-muted-foreground">No active sessions.</li>
                </ul>

                <Dialog v-if="supported && sessions.length > 1">
                    <DialogTrigger as-child>
                        <Button variant="outline">Log out other sessions</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <form class="space-y-6" @submit.prevent="logoutOthers">
                            <DialogHeader class="space-y-3">
                                <DialogTitle>Log out other sessions?</DialogTitle>
                                <DialogDescription>
                                    Every session except this one will be ended. Confirm with your password.
                                </DialogDescription>
                            </DialogHeader>
                            <div class="grid gap-2">
                                <Label for="password" class="sr-only">Password</Label>
                                <Input id="password" v-model="form.password" type="password" placeholder="Password" autocomplete="current-password" />
                                <InputError :message="form.errors.password" />
                            </div>
                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button variant="secondary" type="button">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" :disabled="form.processing">Log out other sessions</Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

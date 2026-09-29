<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Boxes, ShieldCheck, Workflow } from 'lucide-vue-next';

const page = usePage<SharedData>();

const pillars = [
    {
        icon: Boxes,
        title: 'Inventory you can audit',
        text: 'Every unit of stock is a movement in a ledger. Current stock is always reconstructible.',
    },
    { icon: Workflow, title: 'Purchase to payment', text: 'Orders, receipts, invoices and payments flow through explicit, validated states.' },
    { icon: ShieldCheck, title: 'Isolated by design', text: 'Each company’s data is walled off in the application and in the database itself.' },
];
</script>

<template>
    <Head title="Welcome" />
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-2 font-semibold">
                <AppLogoIcon class="size-7 fill-current" />
                EnterpriseFlow
            </div>
            <nav class="flex items-center gap-3 text-sm">
                <Link
                    v-if="page.props.auth.user"
                    :href="route('dashboard')"
                    class="rounded-md bg-primary px-4 py-2 font-medium text-primary-foreground"
                >
                    Open dashboard
                </Link>
                <template v-else>
                    <Link :href="route('login')" class="px-3 py-2 hover:underline">Log in</Link>
                    <Link :href="route('register')" class="rounded-md bg-primary px-4 py-2 font-medium text-primary-foreground">Create account</Link>
                </template>
            </nav>
        </header>

        <main class="mx-auto flex w-full max-w-6xl flex-1 flex-col justify-center px-6 py-16">
            <p class="text-sm font-medium uppercase tracking-widest text-muted-foreground">Multi-company ERP</p>
            <h1 class="mt-3 max-w-3xl text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">
                Run purchasing, sales, inventory and finance for every company you manage.
            </h1>
            <p class="mt-5 max-w-2xl text-lg text-muted-foreground">
                One workspace per company, granular roles for every team member, and a complete audit trail of what changed and who changed it.
            </p>

            <div class="mt-14 grid gap-6 sm:grid-cols-3">
                <div v-for="pillar in pillars" :key="pillar.title" class="rounded-lg border p-5">
                    <component :is="pillar.icon" class="h-5 w-5 text-muted-foreground" />
                    <h2 class="mt-3 font-medium">{{ pillar.title }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">{{ pillar.text }}</p>
                </div>
            </div>
        </main>
    </div>
</template>

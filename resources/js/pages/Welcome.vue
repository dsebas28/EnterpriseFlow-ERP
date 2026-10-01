<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LedgerTrail from '@/components/public/LedgerTrail.vue';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps<{
    demo: boolean;
}>();

const page = usePage<SharedData>();

const repository = 'https://github.com/dsebas28/EnterpriseFlow-ERP';

const modules = [
    { name: 'Inventory', text: 'Stock across warehouses kept as a ledger of movements, so transfers, counts and costs reconcile to the unit.' },
    { name: 'Purchasing', text: 'Orders go through approval, arrive in part or in full, and are checked against the supplier’s bill.' },
    { name: 'Sales', text: 'Confirming a sale takes the stock it needs in one step. Cancelling it puts the stock back.' },
    { name: 'Invoices and payments', text: 'Gap-free numbering for each company, partial payments, overdue tracking and PDF invoices.' },
    { name: 'Reports', text: 'Sales, margins, profit and loss, aging and stock value, exported to CSV, Excel or PDF.' },
    {
        name: 'Teams and companies',
        text: 'Roles per company, email invitations, an audit trail of every change and one login for every company you run.',
    },
];

const focusRing = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ledger-green';
</script>

<template>
    <Head title="Multi-company ERP" />

    <div class="min-h-screen bg-ledger-paper font-sans text-ledger-ink">
        <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-5 sm:px-6">
            <Link :href="route('home')" class="flex items-center gap-2.5 rounded-md" :class="focusRing">
                <span class="flex size-8 items-center justify-center rounded-md bg-ledger-ink text-ledger-paper">
                    <AppLogoIcon class="size-5" />
                </span>
                <span class="font-display text-lg font-semibold tracking-tight">EnterpriseFlow</span>
            </Link>

            <nav class="flex items-center gap-1 text-sm sm:gap-2" aria-label="Main">
                <a :href="repository" class="hidden rounded-md px-3 py-2 text-ledger-muted hover:text-ledger-ink sm:inline-block" :class="focusRing">
                    Source code
                </a>
                <Link
                    v-if="page.props.auth.user"
                    :href="route('dashboard')"
                    class="rounded-md bg-ledger-ink px-4 py-2 font-medium text-ledger-paper hover:bg-ledger-ink/90"
                    :class="focusRing"
                >
                    Open dashboard
                </Link>
                <template v-else>
                    <Link :href="route('login')" class="rounded-md px-3 py-2 hover:underline" :class="focusRing">Sign in</Link>
                    <Link
                        v-if="!demo"
                        :href="route('register')"
                        class="rounded-md bg-ledger-ink px-4 py-2 font-medium text-ledger-paper hover:bg-ledger-ink/90"
                        :class="focusRing"
                    >
                        Create account
                    </Link>
                </template>
            </nav>
        </header>

        <main>
            <section class="mx-auto grid w-full max-w-6xl items-center gap-14 px-4 pb-20 pt-10 sm:px-6 lg:grid-cols-12 lg:gap-12 lg:pb-28 lg:pt-16">
                <div class="lg:col-span-5">
                    <h1 class="max-w-[12ch] font-display text-[clamp(2.6rem,5.2vw,4.25rem)] font-semibold leading-[0.98] tracking-[-0.035em]">
                        Every figure, traced to the transaction behind it.
                    </h1>
                    <p class="mt-6 max-w-[34rem] text-lg leading-relaxed text-ledger-muted">
                        EnterpriseFlow runs purchasing, sales, inventory and finance for one company or several. Stock is a ledger of movements,
                        documents move through approvals, and every change records who made it.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-x-6 gap-y-4">
                        <Link
                            :href="page.props.auth.user ? route('dashboard') : route('login')"
                            class="rounded-md bg-ledger-ink px-6 py-3 text-base font-medium text-ledger-paper hover:bg-ledger-ink/90"
                            :class="focusRing"
                        >
                            {{ page.props.auth.user ? 'Open dashboard' : demo ? 'Explore the demo' : 'Sign in' }}
                        </Link>
                        <a
                            :href="repository"
                            class="rounded-md text-base font-medium underline underline-offset-4 hover:text-ledger-green"
                            :class="focusRing"
                        >
                            Read the code
                        </a>
                    </div>
                    <p v-if="demo && !page.props.auth.user" class="mt-4 text-sm text-ledger-muted">
                        Seven demo accounts, one for each role. Pick one on the sign-in page.
                    </p>
                </div>

                <div class="lg:col-span-7 lg:pl-4">
                    <LedgerTrail />
                </div>
            </section>

            <section class="border-t border-ledger-rule bg-ledger-sheet" aria-labelledby="modules-heading">
                <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
                    <h2 id="modules-heading" class="max-w-2xl font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                        What it runs, company by company
                    </h2>
                    <dl class="mt-10 grid gap-x-10 gap-y-9 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="module in modules" :key="module.name" class="border-t border-ledger-rule pt-4">
                            <dt class="font-medium">{{ module.name }}</dt>
                            <dd class="mt-2 text-sm leading-relaxed text-ledger-muted">{{ module.text }}</dd>
                        </div>
                    </dl>
                </div>
            </section>
        </main>

        <footer
            class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-8 text-sm text-ledger-muted sm:flex-row sm:items-center sm:justify-between sm:px-6"
        >
            <p>EnterpriseFlow ERP is an open-source portfolio project built with Laravel and Vue.</p>
            <div class="flex gap-5">
                <a :href="repository" class="rounded hover:text-ledger-ink" :class="focusRing">GitHub</a>
                <a :href="`${repository}/blob/main/docs/ARCHITECTURE.md`" class="rounded hover:text-ledger-ink" :class="focusRing">Architecture</a>
            </div>
        </footer>
    </div>
</template>

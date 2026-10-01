<script setup lang="ts">
import BrandLogo from '@/components/BrandLogo.vue';
import LedgerTrail from '@/components/public/LedgerTrail.vue';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BarChart3, Boxes, ClipboardList, Receipt, ReceiptText, Users } from 'lucide-vue-next';

defineProps<{
    demo: boolean;
}>();

const page = usePage<SharedData>();

const repository = 'https://github.com/dsebas28/EnterpriseFlow-ERP';

const modules = [
    {
        icon: Boxes,
        name: 'Inventory',
        text: 'Stock across warehouses kept as a ledger of movements, so transfers, counts and costs reconcile to the unit.',
    },
    {
        icon: ClipboardList,
        name: 'Purchasing',
        text: 'Orders go through approval, arrive in part or in full, and are checked against the supplier’s bill.',
    },
    { icon: Receipt, name: 'Sales', text: 'Confirming a sale takes the stock it needs in one step. Cancelling it puts the stock back.' },
    {
        icon: ReceiptText,
        name: 'Invoices and payments',
        text: 'Gap-free numbering for each company, partial payments, overdue tracking and PDF invoices.',
    },
    { icon: BarChart3, name: 'Reports', text: 'Sales, margins, profit and loss, aging and stock value, exported to CSV, Excel or PDF.' },
    {
        icon: Users,
        name: 'Teams and companies',
        text: 'Roles per company, email invitations, an audit trail of every change and one login for every company you run.',
    },
];

const focusRing = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary';
const primaryButton = 'rounded-lg bg-brand-primary font-medium text-white hover:bg-brand-primary/90 dark:text-[#1e1b4b]';
</script>

<template>
    <Head title="Multi-company ERP" />

    <div class="min-h-screen bg-brand-paper font-sans text-brand-ink">
        <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-5 sm:px-6">
            <Link :href="route('home')" class="rounded-md" :class="focusRing" aria-label="EnterpriseFlow ERP home">
                <BrandLogo />
            </Link>

            <nav class="flex items-center gap-1 text-sm sm:gap-2" aria-label="Main">
                <a :href="repository" class="hidden rounded-md px-3 py-2 text-brand-muted hover:text-brand-ink sm:inline-block" :class="focusRing">
                    Source code
                </a>
                <Link v-if="page.props.auth.user" :href="route('dashboard')" class="px-4 py-2" :class="[primaryButton, focusRing]">
                    Open dashboard
                </Link>
                <template v-else>
                    <Link :href="route('login')" class="rounded-md px-3 py-2 font-medium hover:text-brand-primary" :class="focusRing">Sign in</Link>
                    <Link v-if="!demo" :href="route('register')" class="px-4 py-2" :class="[primaryButton, focusRing]">Create account</Link>
                </template>
            </nav>
        </header>

        <main>
            <section class="mx-auto grid w-full max-w-6xl items-center gap-14 px-4 pb-20 pt-10 sm:px-6 lg:grid-cols-12 lg:gap-12 lg:pb-24 lg:pt-14">
                <div class="lg:col-span-5">
                    <h1 class="max-w-[12ch] font-display text-[clamp(2.6rem,5.2vw,4.25rem)] font-semibold leading-[0.98] tracking-[-0.035em]">
                        Every figure, traced to the transaction behind it.
                    </h1>
                    <p class="mt-6 max-w-[34rem] text-lg leading-relaxed text-brand-muted">
                        EnterpriseFlow runs purchasing, sales, inventory and finance for one company or several. Stock is a ledger of movements,
                        documents move through approvals, and every change records who made it.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-x-6 gap-y-4">
                        <Link
                            :href="page.props.auth.user ? route('dashboard') : route('login')"
                            class="px-6 py-3 text-base shadow-[0_8px_24px_-8px_rgb(67_56_202/0.6)]"
                            :class="[primaryButton, focusRing]"
                        >
                            {{ page.props.auth.user ? 'Open dashboard' : demo ? 'Explore the demo' : 'Sign in' }}
                        </Link>
                        <a
                            :href="repository"
                            class="rounded-md text-base font-medium underline decoration-[#f59e0b] decoration-2 underline-offset-[6px] hover:text-brand-primary"
                            :class="focusRing"
                        >
                            Read the code
                        </a>
                    </div>
                    <p v-if="demo && !page.props.auth.user" class="mt-4 text-sm text-brand-muted">
                        Seven demo accounts, one for each role. Pick one on the sign-in page.
                    </p>
                </div>

                <div class="lg:col-span-7 lg:pl-4">
                    <LedgerTrail />
                </div>
            </section>

            <!-- The product itself, on the brand's deep indigo. -->
            <section class="bg-[#1e1b4b] text-white" aria-labelledby="product-heading">
                <div class="mx-auto w-full max-w-6xl px-4 pb-0 pt-16 sm:px-6 lg:pt-20">
                    <div class="max-w-2xl">
                        <h2 id="product-heading" class="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                            The whole company on one screen
                        </h2>
                        <p class="mt-4 text-lg leading-relaxed text-indigo-200">
                            Today’s sales, what customers owe, what is running low and what needs approval, computed from the records themselves and
                            filtered by what each role may see.
                        </p>
                    </div>

                    <figure class="mt-12 overflow-hidden rounded-t-xl border border-b-0 border-white/15 bg-white/5 p-2 pb-0 sm:p-3 sm:pb-0">
                        <div class="flex items-center gap-1.5 px-2 pb-2.5 sm:pb-3" aria-hidden="true">
                            <span class="size-2.5 rounded-full bg-white/25" />
                            <span class="size-2.5 rounded-full bg-white/25" />
                            <span class="size-2.5 rounded-full bg-[#f59e0b]" />
                        </div>
                        <img
                            src="/images/dashboard.png"
                            alt="EnterpriseFlow dashboard with sales, receivables, low stock and recent documents for Demo Company"
                            width="2160"
                            height="1350"
                            loading="lazy"
                            class="block w-full rounded-t-lg"
                        />
                    </figure>
                </div>
            </section>

            <section class="bg-brand-surface" aria-labelledby="modules-heading">
                <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
                    <h2 id="modules-heading" class="max-w-2xl font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                        What it runs, company by company
                    </h2>
                    <dl class="mt-10 grid gap-x-10 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="module in modules" :key="module.name">
                            <dt class="flex items-center gap-3 font-medium">
                                <span class="flex size-9 items-center justify-center rounded-lg bg-brand-primary/10 text-brand-primary">
                                    <component :is="module.icon" class="size-[18px]" />
                                </span>
                                {{ module.name }}
                            </dt>
                            <dd class="mt-3 text-sm leading-relaxed text-brand-muted">{{ module.text }}</dd>
                        </div>
                    </dl>
                </div>
            </section>
        </main>

        <footer class="border-t border-brand-line">
            <div
                class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-8 text-sm text-brand-muted sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <BrandLogo size="sm" />
                <p>An open-source portfolio project built with Laravel and Vue.</p>
                <div class="flex gap-5">
                    <a :href="repository" class="rounded hover:text-brand-ink" :class="focusRing">GitHub</a>
                    <a :href="`${repository}/blob/main/docs/ARCHITECTURE.md`" class="rounded hover:text-brand-ink" :class="focusRing">Architecture</a>
                </div>
            </div>
        </footer>
    </div>
</template>

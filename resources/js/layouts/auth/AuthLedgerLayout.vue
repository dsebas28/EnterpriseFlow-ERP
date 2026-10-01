<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid min-h-dvh bg-ledger-sheet text-ledger-ink lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
        <!-- Ruled ledger paper, with the red margin line of an account book. -->
        <!-- Sticky at viewport height: the form column can be taller (demo accounts). -->
        <aside
            class="bg-ledger-ruled relative hidden overflow-hidden border-r border-ledger-rule bg-ledger-paper lg:sticky lg:top-0 lg:flex lg:h-dvh lg:flex-col"
        >
            <div class="absolute inset-y-0 left-16 w-px bg-ledger-red/40" aria-hidden="true" />

            <Link
                :href="route('home')"
                class="relative ml-24 mt-8 flex w-fit items-center gap-2.5 rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ledger-green"
            >
                <span class="flex size-8 items-center justify-center rounded-md bg-ledger-ink text-ledger-paper">
                    <AppLogoIcon class="size-5" />
                </span>
                <span class="font-display text-lg font-semibold tracking-tight">EnterpriseFlow</span>
            </Link>

            <div class="relative mb-16 ml-24 mr-12 mt-auto">
                <p class="max-w-md font-display text-[2.6rem] font-semibold leading-[1.04] tracking-[-0.03em]">
                    Purchasing, sales, stock and money in one ledger.
                </p>
                <p class="mt-5 max-w-sm leading-relaxed text-ledger-muted">For every company you run, with roles for each person on the team.</p>
            </div>
        </aside>

        <main class="flex items-center justify-center px-4 py-12 sm:px-6">
            <div class="w-full max-w-sm">
                <Link :href="route('home')" class="mb-10 flex w-fit items-center gap-2.5 lg:hidden">
                    <span class="flex size-8 items-center justify-center rounded-md bg-ledger-ink text-ledger-paper">
                        <AppLogoIcon class="size-5" />
                    </span>
                    <span class="font-display text-lg font-semibold tracking-tight">EnterpriseFlow</span>
                </Link>

                <h1 v-if="title" class="font-display text-3xl font-semibold tracking-tight">{{ title }}</h1>
                <p v-if="description" class="mt-2 text-sm text-ledger-muted">{{ description }}</p>

                <div class="mt-8">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>

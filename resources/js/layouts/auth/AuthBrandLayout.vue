<script setup lang="ts">
import BrandLogo from '@/components/BrandLogo.vue';
import FlowDiagram from '@/components/public/FlowDiagram.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid min-h-dvh bg-brand-paper text-brand-ink lg:grid-cols-2">
        <!-- Brand panel: deep indigo in both themes, sticky at viewport height. -->
        <aside
            class="relative hidden overflow-hidden bg-[#1e1b4b] text-white lg:sticky lg:top-0 lg:flex lg:h-dvh lg:flex-col"
            style="background-image: radial-gradient(60% 50% at 35% 45%, rgb(99 102 241 / 0.32), transparent 70%)"
        >
            <div class="px-12 pt-10">
                <Link
                    :href="route('home')"
                    class="inline-block rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-amber-400"
                >
                    <BrandLogo inverse />
                </Link>
            </div>

            <div class="flex flex-1 items-center px-12">
                <div class="w-full max-w-lg">
                    <FlowDiagram />
                </div>
            </div>

            <div class="px-12 pb-12">
                <p class="max-w-md font-display text-[2.35rem] font-semibold leading-[1.06] tracking-[-0.03em]">
                    Every document connected, from the order to the payment.
                </p>
                <p class="mt-4 max-w-sm leading-relaxed text-indigo-200">Purchasing, sales, stock and finance for every company you run.</p>
            </div>
        </aside>

        <main class="flex items-center justify-center px-4 py-12 sm:px-8">
            <div class="w-full max-w-md">
                <Link :href="route('home')" class="mb-10 inline-block lg:hidden">
                    <BrandLogo />
                </Link>

                <div
                    class="rounded-2xl border border-brand-line bg-brand-surface p-6 shadow-[0_1px_2px_rgb(30_27_75/0.04),0_12px_32px_-12px_rgb(30_27_75/0.18)] sm:p-8"
                >
                    <h1 v-if="title" class="font-display text-[1.75rem] font-semibold tracking-tight">{{ title }}</h1>
                    <p v-if="description" class="mt-1.5 text-sm text-brand-muted">{{ description }}</p>

                    <div class="mt-7">
                        <slot />
                    </div>
                </div>

                <slot name="after" />
            </div>
        </main>
    </div>
</template>

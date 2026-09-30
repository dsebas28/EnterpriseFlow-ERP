<script setup lang="ts">
import ExportsList, { type ExportRow } from '@/components/ExportsList.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { BarChart3, ChevronRight } from 'lucide-vue-next';

defineProps<{
    reports: Record<string, { key: string; title: string; description: string; group: string }[]>;
    exports: ExportRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '/reports' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Reports" />

        <div class="flex flex-col gap-8 p-4 md:p-6">
            <HeadingSmall title="Reports" description="Figures computed from your transactions. Every report can be filtered and exported." />

            <section v-for="(items, group) in reports" :key="group" class="space-y-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ group }}</h3>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="report in items"
                        :key="report.key"
                        :href="route('reports.show', report.key)"
                        class="group flex items-start gap-3 rounded-lg border p-4 transition hover:border-primary/50 hover:bg-muted/30"
                    >
                        <BarChart3 class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground" />
                        <span class="flex-1">
                            <span class="block font-medium">{{ report.title }}</span>
                            <span class="block text-sm text-muted-foreground">{{ report.description }}</span>
                        </span>
                        <ChevronRight class="h-4 w-4 text-muted-foreground transition group-hover:translate-x-0.5" />
                    </Link>
                </div>
            </section>

            <section v-if="exports.length" class="space-y-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Your recent exports</h3>
                <ExportsList :exports="exports" show-report />
            </section>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <!-- Sidebar logo: the mark stays visible when the sidebar collapses to icons. -->
    <AppLogoIcon class="size-8 shrink-0 text-brand-indigo" />
    <div class="ml-0.5 grid flex-1 text-left leading-none">
        <span class="truncate font-display text-base font-bold tracking-tight text-brand-indigo">EnterpriseFlow</span>
        <span class="mt-1 flex items-center gap-1.5 text-[10px] font-semibold tracking-[0.18em] text-[#f59e0b]">
            <span class="h-px w-3 bg-current" aria-hidden="true" />ERP
        </span>
    </div>
</template>

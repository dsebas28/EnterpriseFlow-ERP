<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

/**
 * Full logo: mark, "EnterpriseFlow" wordmark and the amber "ERP" tag.
 * `inverse` is for dark brand surfaces (the sign-in panel) in either theme.
 */
withDefaults(
    defineProps<{
        size?: 'sm' | 'md' | 'lg';
        inverse?: boolean;
    }>(),
    { size: 'md', inverse: false },
);

const sizes = {
    sm: { mark: 'size-7', word: 'text-base', tag: 'text-[10px]' },
    md: { mark: 'size-9', word: 'text-xl', tag: 'text-[11px]' },
    lg: { mark: 'size-12', word: 'text-3xl', tag: 'text-xs' },
};
</script>

<template>
    <span class="inline-flex items-center gap-2.5" :class="inverse ? 'text-indigo-50' : 'text-brand-indigo'">
        <AppLogoIcon :class="sizes[size].mark" />
        <span class="flex flex-col leading-none">
            <span class="font-display font-bold tracking-tight" :class="sizes[size].word">EnterpriseFlow</span>
            <span class="mt-1 flex items-center gap-1.5 font-semibold tracking-[0.18em] text-[#f59e0b]" :class="sizes[size].tag">
                <span class="h-px w-4 bg-current" aria-hidden="true" />ERP
            </span>
        </span>
    </span>
</template>

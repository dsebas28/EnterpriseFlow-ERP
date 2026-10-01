<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthBrandLayout.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description">
        <slot />
        <template #after>
            <slot name="after" />
        </template>
    </AuthLayout>
</template>

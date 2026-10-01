<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthLedgerLayout.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description">
        <slot />
    </AuthLayout>
</template>

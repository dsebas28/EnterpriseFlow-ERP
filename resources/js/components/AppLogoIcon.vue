<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <!-- EnterpriseFlow mark: an "E" whose bars flow to the right. -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" :class="className" v-bind="$attrs" aria-hidden="true">
        <path
            fill="currentColor"
            d="M6 6a2 2 0 0 1 2-2h20.5a2 2 0 0 1 1.6.8l3 4a2 2 0 0 1-1.6 3.2H12v5h13.5a2 2 0 0 1 1.6.8l2.2 3a2 2 0 0 1-1.6 3.2H12v5h19.5a2 2 0 0 1 1.6 3.2l-3 4a2 2 0 0 1-1.6.8H8a2 2 0 0 1-2-2V6Z"
        />
    </svg>
</template>

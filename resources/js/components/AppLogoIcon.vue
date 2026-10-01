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
    <!--
        EnterpriseFlow mark: an "E" whose three bars end in nodes, the flow
        between documents. Bars take the text color (indigo by default, light
        on dark surfaces); nodes are always the brand amber.
    -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" :class="className" v-bind="$attrs" aria-hidden="true">
        <g fill="currentColor">
            <rect x="5" y="5" width="6.5" height="30" rx="1.5" />
            <rect x="5" y="5" width="22" height="6.5" rx="1.5" />
            <rect x="5" y="16.75" width="16" height="6.5" rx="1.5" />
            <rect x="5" y="28.5" width="22" height="6.5" rx="1.5" />
        </g>
        <g fill="#f59e0b">
            <circle cx="30" cy="8.25" r="4.5" />
            <circle cx="24" cy="20" r="4.5" />
            <circle cx="30" cy="31.75" r="4.5" />
        </g>
    </svg>
</template>

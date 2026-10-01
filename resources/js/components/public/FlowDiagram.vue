<script setup lang="ts">
/**
 * The logo's "E", unfolded: a spine with three lanes (purchasing, sales,
 * inventory) whose nodes are the documents each flow goes through. A pulse
 * travels each lane; with reduced motion the diagram is static.
 */
const lanes = [
    { y: 62, title: 'Purchasing', steps: ['Purchase order', 'Received', 'Bill paid'], begin: '0s' },
    { y: 152, title: 'Sales', steps: ['Sale', 'Invoiced', 'Collected'], begin: '-1.6s' },
    { y: 242, title: 'Inventory', steps: ['Stock in', 'Transfer', 'Counted'], begin: '-3.2s' },
];

const stops = [150, 270, 390];
</script>

<template>
    <svg viewBox="0 0 440 300" class="h-auto w-full" role="img" aria-label="Purchasing, sales and inventory flowing through their documents">
        <!-- Spine and lanes: the bars of the logo. -->
        <rect x="30" y="54" width="14" height="196" rx="4" fill="#4f46e5" fill-opacity="0.55" />
        <g v-for="lane in lanes" :key="lane.title">
            <rect x="30" :y="lane.y - 7" width="372" height="14" rx="4" fill="#4f46e5" fill-opacity="0.38" />
            <text x="62" :y="lane.y - 18" class="fill-indigo-200 text-[13px] font-medium">{{ lane.title }}</text>

            <g v-for="(step, i) in lane.steps" :key="step">
                <circle :cx="stops[i]" :cy="lane.y" r="11" fill="#f59e0b" stroke="#1e1b4b" stroke-width="4" />
                <text :x="stops[i]" :y="lane.y + 32" text-anchor="middle" class="fill-indigo-100/80 text-[12px]">{{ step }}</text>
            </g>

            <!-- A document moving through the flow. -->
            <circle class="flow-pulse" r="5" fill="#ffffff">
                <animateMotion :path="`M44 ${lane.y} H402`" dur="4.8s" :begin="lane.begin" repeatCount="indefinite" />
            </circle>
        </g>
    </svg>
</template>

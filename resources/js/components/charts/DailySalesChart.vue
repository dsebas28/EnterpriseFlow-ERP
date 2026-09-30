<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * Single-series column chart of daily sales (last 30 days).
 *
 * Follows the dataviz spec: one validated hue (blue, light/dark steps),
 * columns capped at 24px with a 4px rounded data-end and a 2px surface
 * gap, recessive hairline grid, clean rounded ticks, and a per-column
 * hover tooltip with a hit target taller than the mark. A single series
 * needs no legend; the card title names it. The table view is the
 * visually-hidden <table> below for screen readers.
 */
const props = defineProps<{
    points: { date: string; total: number }[];
    currency: string;
    decimals: number;
}>();

const width = 720;
const height = 220;
const pad = { top: 12, right: 8, bottom: 26, left: 56 };
const plotW = width - pad.left - pad.right;
const plotH = height - pad.top - pad.bottom;

const values = computed(() => props.points.map((p) => p.total / 10 ** props.decimals));

// Clean tick step: 1, 2 or 5 × 10^n, about four gridlines.
const ticks = computed(() => {
    const max = Math.max(...values.value, 0);
    if (max === 0) return [0, 1];
    const raw = max / 4;
    const magnitude = 10 ** Math.floor(Math.log10(raw));
    const step = [1, 2, 5, 10].map((m) => m * magnitude).find((s) => s >= raw) ?? raw;
    const result: number[] = [];
    for (let v = 0; v <= max + step * 0.001; v += step) result.push(v);
    if (result[result.length - 1] < max) result.push(result[result.length - 1] + step);
    return result;
});
const yMax = computed(() => ticks.value[ticks.value.length - 1] || 1);
const y = (v: number) => pad.top + plotH - (v / yMax.value) * plotH;

const band = computed(() => plotW / Math.max(props.points.length, 1));
const barW = computed(() => Math.min(24, band.value - 2)); // 2px surface gap
const x = (i: number) => pad.left + i * band.value + (band.value - barW.value) / 2;

// Column path: square at the baseline, 4px rounded data-end.
const barPath = (i: number) => {
    const top = y(values.value[i]);
    const base = y(0);
    const h = base - top;
    if (h <= 0) return '';
    const r = Math.min(4, h, barW.value / 2);
    const left = x(i);
    const right = left + barW.value;
    return `M${left},${base} V${top + r} Q${left},${top} ${left + r},${top} H${right - r} Q${right},${top} ${right},${top + r} V${base} Z`;
};

const compact = new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 });
const full = computed(() => new Intl.NumberFormat(undefined, { style: 'currency', currency: props.currency }));
const dayLabel = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });

const hovered = ref<number | null>(null);
const tooltipLeft = computed(() => (hovered.value === null ? 0 : ((x(hovered.value) + barW.value / 2) / width) * 100));
</script>

<template>
    <div class="relative">
        <svg
            :viewBox="`0 0 ${width} ${height}`"
            class="h-auto w-full overflow-visible"
            role="img"
            aria-label="Daily sales for the last 30 days"
            @mouseleave="hovered = null"
        >
            <g>
                <line
                    v-for="tick in ticks"
                    :key="tick"
                    :x1="pad.left"
                    :x2="width - pad.right"
                    :y1="y(tick)"
                    :y2="y(tick)"
                    class="stroke-border"
                    stroke-width="1"
                />
                <text
                    v-for="tick in ticks"
                    :key="`t${tick}`"
                    :x="pad.left - 8"
                    :y="y(tick)"
                    dy="0.32em"
                    text-anchor="end"
                    class="fill-muted-foreground text-[11px]"
                >
                    {{ compact.format(tick) }}
                </text>
            </g>

            <g>
                <path
                    v-for="(point, i) in points"
                    :key="point.date"
                    :d="barPath(i)"
                    class="chart-bar"
                    :class="{ 'opacity-60': hovered !== null && hovered !== i }"
                />
            </g>

            <!-- First, middle and last day: enough to orient without crowding. -->
            <g>
                <text
                    v-for="i in [0, Math.floor((points.length - 1) / 2), points.length - 1]"
                    :key="`x${i}`"
                    :x="x(i) + barW / 2"
                    :y="height - 6"
                    text-anchor="middle"
                    class="fill-muted-foreground text-[11px]"
                >
                    {{ points[i] ? dayLabel(points[i].date) : '' }}
                </text>
            </g>

            <!-- Hit targets: the full column band, taller than the mark. -->
            <rect
                v-for="(point, i) in points"
                :key="`h${point.date}`"
                :x="pad.left + i * band"
                :y="pad.top"
                :width="band"
                :height="plotH"
                fill="transparent"
                @mouseenter="hovered = i"
                @focus="hovered = i"
                @blur="hovered = null"
                tabindex="0"
                :aria-label="`${dayLabel(point.date)}: ${full.format(values[i])}`"
            />
        </svg>

        <div
            v-if="hovered !== null && points[hovered]"
            class="pointer-events-none absolute top-0 -translate-x-1/2 rounded-md border bg-popover px-2.5 py-1.5 text-xs shadow-md"
            :style="{ left: `${tooltipLeft}%` }"
        >
            <p class="text-muted-foreground">{{ dayLabel(points[hovered].date) }}</p>
            <p class="font-semibold tabular-nums text-foreground">{{ full.format(values[hovered]) }}</p>
        </div>

        <table class="sr-only">
            <caption>
                Daily sales
            </caption>
            <tr v-for="(point, i) in points" :key="`r${point.date}`">
                <th scope="row">{{ point.date }}</th>
                <td>{{ full.format(values[i]) }}</td>
            </tr>
        </table>
    </div>
</template>

<style scoped>
/* Validated series hue: blue slot 1, stepped separately for each mode. */
.chart-bar {
    fill: #2a78d6;
    transition: opacity 120ms;
}
:global(.dark) .chart-bar {
    fill: #3987e5;
}
</style>

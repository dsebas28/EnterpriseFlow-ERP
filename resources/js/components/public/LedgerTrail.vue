<script setup lang="ts">
/**
 * One sale followed end to end, printed as ledger lines: the record of the
 * sale, the stock it took, the invoice and the payment that settled it.
 * Figures match the demo data so the story holds after signing in.
 */
interface Line {
    when: string;
    document: string;
    text: string;
    amount: string;
    kind: 'neutral' | 'in' | 'out';
    note?: string;
}

const lines: Line[] = [
    { when: 'Sep 18, 09:12', document: 'SO-000149', text: 'Sale to Andes Logistics confirmed', amount: '$894.40', kind: 'neutral' },
    { when: '09:12', document: 'ELE-002', text: '24" Full HD monitor left Main warehouse', amount: '−1', kind: 'out', note: '36 in stock' },
    { when: '09:12', document: 'ELE-003', text: 'Wireless keyboards left Main warehouse', amount: '−3', kind: 'out', note: '58 in stock' },
    { when: 'Sep 19, 09:30', document: 'INV-000149', text: 'Invoice issued, due Oct 19', amount: '$894.40', kind: 'neutral' },
    { when: 'Oct 2, 14:00', document: 'PAY-000108', text: 'Bank transfer received', amount: '+$894.40', kind: 'in' },
];

const amountClass = (kind: Line['kind']) => ({ in: 'text-brand-positive', out: 'text-brand-negative', neutral: 'text-brand-ink' })[kind];
</script>

<template>
    <figure class="relative">
        <!-- The sheet underneath: a ledger is a stack of pages. -->
        <div class="relative">
            <div class="absolute inset-0 translate-x-2 translate-y-2 rounded-lg border border-brand-line bg-brand-paper" aria-hidden="true" />

            <div class="relative overflow-hidden rounded-lg border border-brand-line bg-brand-surface">
                <div class="flex items-baseline justify-between gap-4 border-b border-brand-line px-5 py-4">
                    <h2 class="font-display text-lg font-semibold tracking-tight">One sale, start to finish</h2>
                    <span class="text-sm text-brand-muted">Demo Company</span>
                </div>

                <ol class="tabular-nums">
                    <li
                        v-for="(line, index) in lines"
                        :key="line.document"
                        class="ledger-print grid grid-cols-[1fr_auto] gap-x-4 gap-y-0.5 border-b border-brand-line px-5 py-3 sm:grid-cols-[5.75rem_6.5rem_1fr_auto] sm:items-baseline"
                        :style="{ animationDelay: `${250 + index * 160}ms` }"
                    >
                        <span class="order-3 col-span-2 text-xs text-brand-muted sm:order-none sm:col-span-1 sm:text-sm">{{ line.when }}</span>
                        <span class="order-1 text-sm font-semibold sm:order-none">{{ line.document }}</span>
                        <span class="order-4 col-span-2 text-sm text-brand-ink/85 sm:order-none sm:col-span-1">{{ line.text }}</span>
                        <span class="order-2 text-right sm:order-none">
                            <span class="text-sm font-semibold" :class="amountClass(line.kind)">{{ line.amount }}</span>
                            <span v-if="line.note" class="block text-xs text-brand-muted">{{ line.note }}</span>
                        </span>
                    </li>
                </ol>

                <div
                    class="ledger-print flex items-baseline justify-between px-5 py-4 tabular-nums"
                    :style="{ animationDelay: `${250 + lines.length * 160}ms` }"
                >
                    <span class="text-sm font-medium">Balance due</span>
                    <!-- Double rule: the accountant's mark for a settled total. -->
                    <span class="border-b-[3px] border-double border-brand-ink font-display text-xl font-semibold">$0.00</span>
                </div>
            </div>
        </div>

        <figcaption class="mt-5 text-sm text-brand-muted">
            Every line is a record you can open, and every change to it is in the audit trail.
        </figcaption>
    </figure>
</template>

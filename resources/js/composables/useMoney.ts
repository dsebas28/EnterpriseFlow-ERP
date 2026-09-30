export interface MoneyValue {
    amount: number;
    decimal: string;
    currency: string;
}

/**
 * Display formatting only. Amounts travel as integer minor units and
 * decimal strings; they are never recomputed with floats on the client.
 */
export function formatMoney(money: MoneyValue | null | undefined): string {
    if (!money) return '—';

    return new Intl.NumberFormat(undefined, { style: 'currency', currency: money.currency }).format(Number(money.decimal));
}

/**
 * Gross margin over price as a percentage string, for a hint in forms.
 */
export function marginPercent(cost: string, price: string): string | null {
    const c = Number(cost);
    const p = Number(price);
    if (!Number.isFinite(c) || !Number.isFinite(p) || p <= 0) return null;

    return (((p - c) / p) * 100).toFixed(1);
}

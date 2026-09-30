@php
    use App\Support\Money\BasisPoints;
    use App\Support\Money\MoneyFormatter;

    /** @var \App\Models\Invoice $invoice */
    /** @var \App\Models\Company $company */
    $money = fn (int $minor) => MoneyFormatter::format($invoice->money($minor));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    {{-- dompdf supports CSS 2.1 only: tables and simple properties. --}}
    <style>
        @page { margin: 36px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 22px; margin: 0; letter-spacing: 1px; color: #111827; }
        .muted { color: #6b7280; }
        .small { font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .box { border: 1px solid #e5e7eb; padding: 10px; }
        .label { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 3px; }
        .lines { margin-top: 18px; }
        .lines th { background: #111827; color: #fff; font-size: 8px; text-transform: uppercase; letter-spacing: 1px; padding: 7px 6px; text-align: left; }
        .lines td { padding: 7px 6px; border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 45%; margin-left: 55%; margin-top: 12px; }
        .totals td { padding: 4px 6px; }
        .grand td { border-top: 2px solid #111827; font-size: 12px; font-weight: bold; padding-top: 7px; }
        .footer { position: fixed; bottom: -10px; left: 0; right: 0; text-align: center; font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 55%">
                <div style="font-size: 16px; font-weight: bold;">{{ $company->legal_name ?? $company->name }}</div>
                @if ($company->tax_id)<div class="muted">Tax ID {{ $company->tax_id }}</div>@endif
                <div class="muted">{{ collect([$company->address, $company->city, $company->country])->filter()->implode(', ') }}</div>
                <div class="muted">{{ collect([$company->email, $company->phone])->filter()->implode(' · ') }}</div>
            </td>
            <td style="width: 45%; text-align: right;">
                <h1>INVOICE</h1>
                <div style="font-size: 13px; font-weight: bold; margin-top: 4px;">{{ $invoice->number }}</div>
                <table style="margin-top: 8px;">
                    <tr><td class="muted" style="text-align: right;">Issue date</td><td class="num" style="width: 90px;">{{ $invoice->issue_date?->toDateString() }}</td></tr>
                    <tr><td class="muted" style="text-align: right;">Due date</td><td class="num"><strong>{{ $invoice->due_date->toDateString() }}</strong></td></tr>
                    <tr><td class="muted" style="text-align: right;">Currency</td><td class="num">{{ $invoice->currency }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 20px;">
        <tr>
            <td class="box" style="width: 55%;">
                <div class="label">Bill to</div>
                <div style="font-size: 12px; font-weight: bold;">{{ $invoice->customer->name }}</div>
                @if ($invoice->customer->tax_id)<div class="muted">Tax ID {{ $invoice->customer->tax_id }}</div>@endif
                <div class="muted">{{ collect([$invoice->customer->address, $invoice->customer->city, $invoice->customer->country])->filter()->implode(', ') }}</div>
                @if ($invoice->customer->email)<div class="muted">{{ $invoice->customer->email }}</div>@endif
            </td>
            <td style="width: 45%;"></td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Description</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Disc.</th>
                <th class="num">Tax</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ $money($item->unit_price) }}</td>
                    <td class="num">{{ $item->discount_rate ? BasisPoints::toPercent($item->discount_rate).'%' : '—' }}</td>
                    <td class="num">{{ BasisPoints::toPercent($item->tax_rate) }}%</td>
                    <td class="num">{{ $money($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @if ($invoice->discount_total > 0)
            <tr><td class="muted">Discounts</td><td class="num">−{{ $money($invoice->discount_total) }}</td></tr>
        @endif
        <tr><td class="muted">Subtotal</td><td class="num">{{ $money($invoice->subtotal) }}</td></tr>
        <tr><td class="muted">Tax</td><td class="num">{{ $money($invoice->tax_total) }}</td></tr>
        <tr class="grand"><td>Total due</td><td class="num">{{ $money($invoice->total) }}</td></tr>
    </table>

    @if ($invoice->notes)
        <div style="margin-top: 24px;">
            <div class="label">Notes</div>
            <div>{{ $invoice->notes }}</div>
        </div>
    @endif

    <div class="footer">
        {{ $company->name }} · Invoice {{ $invoice->number }} · Generated by EnterpriseFlow ERP
    </div>
</body>
</html>

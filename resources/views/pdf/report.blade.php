@php
    use App\Reports\Column;
    use App\Support\Money\Money;
    use App\Support\Money\MoneyFormatter;

    /** @var \App\Reports\Report $report */
    $format = function (Column $column, $value) use ($company) {
        if ($value === null) {
            return '—';
        }

        return match ($column->type) {
            Column::MONEY => MoneyFormatter::format(new Money((int) $value, $company->currency)),
            Column::PERCENT => number_format((float) $value, 1).'%',
            Column::NUMBER => number_format((int) $value),
            default => (string) $value,
        };
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title() }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th { background: #111827; color: #fff; text-align: left; padding: 6px 5px; font-size: 8px; text-transform: uppercase; }
        td { padding: 5px; border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; border-top: 2px solid #111827; border-bottom: none; }
    </style>
</head>
<body>
    <h1>{{ $report->title() }}</h1>
    <div class="muted">
        {{ $company->name }} ·
        @if (in_array('from', $report->filters(), true)) {{ $filters->from }} – {{ $filters->to }} · @endif
        Generated {{ now($company->timezone)->format('Y-m-d H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($report->columns() as $column)
                    <th class="{{ $column->type === Column::TEXT ? '' : 'num' }}">{{ $column->label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($report->columns() as $column)
                        <td class="{{ $column->type === Column::TEXT ? '' : 'num' }}">{{ $format($column, $row[$column->key] ?? null) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report->columns()) }}" class="muted">No data for the selected filters.</td></tr>
            @endforelse
        </tbody>
        @if ($totals !== [])
            <tfoot>
                <tr>
                    @foreach ($report->columns() as $column)
                        <td class="{{ $column->type === Column::TEXT ? '' : 'num' }}">{{ array_key_exists($column->key, $totals) ? $format($column, $totals[$column->key]) : '' }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>

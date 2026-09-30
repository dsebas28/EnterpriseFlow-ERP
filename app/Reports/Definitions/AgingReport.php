<?php

namespace App\Reports\Definitions;

use App\Enums\InvoiceStatus;
use App\Reports\Column;
use App\Reports\ReportFilters;
use App\Reports\Support\BaseReport;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Open balances bucketed by days past due, per counterparty. Shared by
 * receivables (invoices) and payables (supplier bills).
 */
abstract class AgingReport extends BaseReport
{
    public function __construct(protected readonly TenantContext $tenant) {}

    public function group(): string
    {
        return 'Finance';
    }

    public function columns(): array
    {
        return [
            Column::text('party', $this->partyLabel()),
            Column::money('current', 'Not yet due'),
            Column::money('d1_30', '1–30 days'),
            Column::money('d31_60', '31–60 days'),
            Column::money('d61_90', '61–90 days'),
            Column::money('d90_plus', '90+ days'),
            Column::money('total', 'Total'),
        ];
    }

    abstract protected function partyLabel(): string;

    /**
     * Open documents with party name, due date and open balance.
     *
     * @return Collection<int, array{party: string, due_date: string, balance: int}>
     */
    abstract protected function openDocuments(ReportFilters $filters): Collection;

    /**
     * Normalise an untyped database row.
     *
     * @return array{party: string, due_date: string, balance: int}
     */
    protected static function document(object $row): array
    {
        return [
            'party' => (string) $row->party,
            'due_date' => (string) $row->due_date,
            'balance' => (int) $row->balance,
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $today = Carbon::parse(now($this->tenant->companyOrFail()->timezone)->toDateString());
        $rows = [];

        foreach ($this->openDocuments($filters) as $document) {
            $balance = $document['balance'];
            if ($balance <= 0) {
                continue;
            }

            $daysPastDue = (int) Carbon::parse($document['due_date'])->startOfDay()->diffInDays($today, false);
            $bucket = match (true) {
                $daysPastDue <= 0 => 'current',
                $daysPastDue <= 30 => 'd1_30',
                $daysPastDue <= 60 => 'd31_60',
                $daysPastDue <= 90 => 'd61_90',
                default => 'd90_plus',
            };

            $party = $document['party'];
            $rows[$party] ??= ['party' => $party, 'current' => 0, 'd1_30' => 0, 'd31_60' => 0, 'd61_90' => 0, 'd90_plus' => 0, 'total' => 0];
            $rows[$party][$bucket] += $balance;
            $rows[$party]['total'] += $balance;
        }

        uasort($rows, fn (array $a, array $b) => $b['total'] <=> $a['total']);

        return array_values($rows);
    }

    /**
     * @return list<string>
     */
    protected function openStatuses(): array
    {
        return InvoiceStatus::openValues();
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Inventory\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RebuildInventory extends Command
{
    protected $signature = 'inventory:rebuild
        {--company= : Only check this company id}
        {--fix : Correct drifted stock levels (default is a dry run)}';

    protected $description = 'Recompute stock levels from the stock movement ledger and report or fix discrepancies';

    public function handle(TenantContext $tenant, StockLedger $ledger): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        $totalIssues = 0;

        foreach ($companies as $company) {
            $tenant->run($company, function () use ($company, $ledger, &$totalIssues): void {
                $issues = $ledger->discrepancies();
                $totalIssues += count($issues);

                if ($issues === []) {
                    $this->components->info("{$company->name}: stock levels match the ledger.");

                    return;
                }

                $this->components->warn("{$company->name}: ".count($issues).' discrepancies.');
                $this->table(['Product', 'Warehouse', 'Ledger', 'Stored'], array_map(
                    fn (array $i) => [$i['product_id'], $i['warehouse_id'], $i['expected'], $i['actual']],
                    $issues,
                ));

                Log::channel('app_json')->warning('inventory.discrepancies', ['company_id' => $company->id, 'issues' => $issues]);

                if ($this->option('fix')) {
                    $fixed = $ledger->rebuild();
                    $this->components->info("Corrected {$fixed} stock levels.");
                }
            });
        }

        // Non-zero exit on a dry run with issues, so monitoring can alert on it.
        return $totalIssues > 0 && ! $this->option('fix') ? self::FAILURE : self::SUCCESS;
    }
}

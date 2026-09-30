<?php

namespace App\Console\Commands;

use App\Actions\Invoicing\MarkOverdueInvoices;
use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class MarkOverdueInvoicesCommand extends Command
{
    protected $signature = 'invoices:mark-overdue {--company= : Only process this company id}';

    protected $description = 'Flag open customer invoices and supplier bills whose due date has passed as overdue';

    public function handle(TenantContext $tenant, MarkOverdueInvoices $markOverdue): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        $totals = ['invoices' => 0, 'bills' => 0];

        foreach ($companies as $company) {
            $counts = $tenant->run($company, fn () => $markOverdue->handle());
            $totals['invoices'] += $counts['invoices'];
            $totals['bills'] += $counts['bills'];

            if ($counts['invoices'] + $counts['bills'] > 0) {
                $this->components->info("{$company->name}: {$counts['invoices']} invoices and {$counts['bills']} supplier bills marked overdue.");
            }
        }

        $this->components->info("Done. {$totals['invoices']} invoices and {$totals['bills']} supplier bills marked overdue.");

        return self::SUCCESS;
    }
}

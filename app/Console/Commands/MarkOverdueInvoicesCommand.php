<?php

namespace App\Console\Commands;

use App\Actions\Invoicing\MarkOverdueInvoices;
use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class MarkOverdueInvoicesCommand extends Command
{
    protected $signature = 'invoices:mark-overdue {--company= : Only process this company id}';

    protected $description = 'Flag open invoices whose due date has passed as overdue';

    public function handle(TenantContext $tenant, MarkOverdueInvoices $markOverdue): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        $total = 0;

        foreach ($companies as $company) {
            $count = $tenant->run($company, fn () => $markOverdue->handle());
            $total += $count;

            if ($count > 0) {
                $this->components->info("{$company->name}: {$count} invoices marked overdue.");
            }
        }

        $this->components->info("Done. {$total} invoices marked overdue.");

        return self::SUCCESS;
    }
}

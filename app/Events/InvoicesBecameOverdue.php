<?php

namespace App\Events;

use App\Models\Company;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Customer invoices that turned overdue in one run of the overdue job.
 */
class InvoicesBecameOverdue implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<string>  $invoiceIds
     */
    public function __construct(
        public readonly Company $company,
        public readonly array $invoiceIds,
    ) {}
}

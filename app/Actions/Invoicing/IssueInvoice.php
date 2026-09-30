<?php

namespace App\Actions\Invoicing;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\PdfStatus;
use App\Events\InvoiceIssued;
use App\Exceptions\BusinessRuleViolation;
use App\Jobs\GenerateInvoicePdf;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Documents\DocumentNumberGenerator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Issues a draft invoice: assigns its legal number and date, then renders
 * the PDF asynchronously once the transaction has committed.
 */
final class IssueInvoice
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(Invoice $invoice, User $user): Invoice
    {
        $issued = DB::transaction(function () use ($invoice, $user): Invoice {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            $invoice->transitionTo(InvoiceStatus::Issued);

            $today = now($this->tenant->companyOrFail()->timezone)->toDateString();

            if ($invoice->due_date->toDateString() < $today) {
                throw new BusinessRuleViolation('The due date cannot be earlier than the issue date.');
            }

            $invoice->forceFill([
                'number' => $this->numbers->next(DocumentType::Invoice),
                'issue_date' => $today,
                'issued_by' => $user->id,
                'issued_at' => now(),
                'pdf_status' => PdfStatus::Pending,
            ])->save();

            return $invoice;
        });

        // afterCommit: the worker must see the issued invoice.
        GenerateInvoicePdf::dispatch($issued->id)->afterCommit();
        InvoiceIssued::dispatch($issued);

        return $issued;
    }
}

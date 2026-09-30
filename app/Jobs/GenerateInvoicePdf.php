<?php

namespace App\Jobs;

use App\Enums\PdfStatus;
use App\Jobs\Concerns\TenantAware;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Renders an invoice to PDF outside the HTTP request and stores it on the
 * private disk. Idempotent: running it twice just rewrites the same file.
 */
class GenerateInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TenantAware;

    public int $tries = 3;

    /**
     * @var list<int> seconds between attempts
     */
    public array $backoff = [10, 60];

    public function __construct(public readonly string $invoiceId)
    {
        $this->captureTenant();
        $this->onQueue('documents');
    }

    public function handle(): void
    {
        $invoice = Invoice::with(['items', 'customer', 'company'])->findOrFail($this->invoiceId);

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice, 'company' => $invoice->company])
            ->setPaper('a4');

        $path = "companies/{$invoice->company_id}/invoices/{$invoice->number}.pdf";
        Storage::disk(Invoice::PDF_DISK)->put($path, $pdf->output());

        $invoice->forceFill([
            'pdf_status' => PdfStatus::Ready,
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ])->save();

        Log::channel('queue')->info('invoice.pdf_generated', ['invoice_id' => $invoice->id, 'company_id' => $invoice->company_id]);
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('queue')->error('invoice.pdf_failed', [
            'invoice_id' => $this->invoiceId,
            'company_id' => $this->companyId,
            'error' => $exception->getMessage(),
        ]);

        $this->inTenant(fn () => Invoice::whereKey($this->invoiceId)->update(['pdf_status' => PdfStatus::Failed->value]));
    }
}

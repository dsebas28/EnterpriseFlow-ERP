<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Invoicing\CancelInvoice;
use App\Actions\Invoicing\CreateInvoiceFromSale;
use App\Actions\Invoicing\IssueInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PdfStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Jobs\GenerateInvoicePdf;
use App\Models\Invoice;
use App\Models\Sale;
use App\Queries\InvoiceIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request, InvoiceIndexQuery $query): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $filters = $request->only(['search', 'status', 'customer_id', 'from', 'to']);

        return Inertia::render('finance/invoices/Index', [
            'invoices' => InvoiceResource::collection($query->paginate($filters)),
            'filters' => $filters,
            'statuses' => array_map(fn (InvoiceStatus $s) => ['value' => $s->value, 'label' => $s->label()], InvoiceStatus::cases()),
        ]);
    }

    /**
     * Draft the invoice of a confirmed sale.
     */
    public function store(Request $request, Sale $sale, CreateInvoiceFromSale $create): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);

        $validated = $request->validate([
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $invoice = $create->handle($sale, $validated['due_date'], $validated['notes'] ?? null, $request->user());

        return to_route('finance.invoices.show', $invoice)->with('status', 'Draft invoice created. Review it and issue it.');
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['items', 'customer', 'sale', 'issuer']);
        $user = $request->user();

        return Inertia::render('finance/invoices/Show', [
            'invoice' => InvoiceResource::make($invoice),
            'can' => [
                'edit' => $invoice->status === InvoiceStatus::Draft && ($user?->can('update', $invoice) ?? false),
                'issue' => $invoice->status === InvoiceStatus::Draft && ($user?->can('update', $invoice) ?? false),
                'cancel' => $invoice->status->canTransitionTo(InvoiceStatus::Cancelled) && $invoice->amount_paid === 0 && ($user?->can('cancel', $invoice) ?? false),
                'regeneratePdf' => $invoice->number !== null && ($user?->can('update', $invoice) ?? false),
            ],
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new BusinessRuleViolation('Issued invoices cannot be edited.');
        }

        $validated = $request->validate([
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $invoice->forceFill($validated)->save();

        return back()->with('status', 'Invoice updated.');
    }

    public function issue(Request $request, Invoice $invoice, IssueInvoice $issue): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        $issued = $issue->handle($invoice, $request->user());

        return back()->with('status', "Invoice {$issued->number} issued. The PDF is being generated.");
    }

    public function cancel(Request $request, Invoice $invoice, CancelInvoice $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $invoice);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $cancel->handle($invoice, $request->user(), $validated['reason']);

        return back()->with('status', 'Invoice cancelled.');
    }

    public function regeneratePdf(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        if ($invoice->number === null) {
            throw new BusinessRuleViolation('Issue the invoice before generating its PDF.');
        }

        $invoice->forceFill(['pdf_status' => PdfStatus::Pending])->save();
        GenerateInvoicePdf::dispatch($invoice->id);

        return back()->with('status', 'The PDF is being regenerated.');
    }

    /**
     * Private file streamed only after authorization; never a public URL.
     */
    public function downloadPdf(Invoice $invoice): StreamedResponse
    {
        Gate::authorize('view', $invoice);

        abort_unless(
            $invoice->pdf_status === PdfStatus::Ready
                && $invoice->pdf_path !== null
                && Storage::disk(Invoice::PDF_DISK)->exists($invoice->pdf_path),
            404,
        );

        return Storage::disk(Invoice::PDF_DISK)->download($invoice->pdf_path, "{$invoice->number}.pdf");
    }
}

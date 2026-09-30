<?php

use App\Actions\Invoicing\CreateInvoiceFromSale;
use App\Actions\Invoicing\IssueInvoice;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\SaveSale;
use App\DTOs\SaleData;
use App\DTOs\StockMovementData;
use App\Enums\InvoiceStatus;
use App\Enums\PdfStatus;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Events\InvoiceIssued;
use App\Jobs\GenerateInvoicePdf;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-09-29 10:00:00');

    [$this->accountant, $this->company] = memberWithRole(SystemRole::Accountant);
    [$this->manager] = memberWithRole(SystemRole::Manager, $this->company);

    actAsCompany($this->company);
    $this->actingAs($this->manager);
    $warehouse = Warehouse::factory()->default()->create();
    $product = Product::factory()->create(['name' => 'Office chair']);
    app(InventoryService::class)->record(new StockMovementData($product, $warehouse, 10, StockMovementType::ManualIn));

    $this->makeSale = function (bool $confirm = true) use ($warehouse, $product): Sale {
        $sale = app(SaveSale::class)->handle(null, new SaleData(Customer::factory()->create()->id, $warehouse->id, '2026-09-29', null, [
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100000, 'discount_rate' => 1000, 'tax_rate' => 1900],
        ]), $this->manager);

        return $confirm ? app(ConfirmSale::class)->handle($sale, $this->manager) : $sale;
    };
    $this->sale = ($this->makeSale)();

    tenant()->set(null);
    auth()->logout();
});

function draftInvoiceFor(object $test, ?Sale $sale = null): Invoice
{
    return tenant()->run($test->company, fn () => app(CreateInvoiceFromSale::class)->handle($sale ?? $test->sale, '2026-10-29', null, $test->accountant));
}

it('drafts an invoice from a confirmed sale, copying lines and totals', function () {
    $this->actingAs($this->accountant)
        ->post(route('sales.orders.invoice', $this->sale), ['due_date' => '2026-10-29', 'notes' => 'Net 30'])
        ->assertRedirect();

    $invoice = tenant()->run($this->company, fn () => Invoice::with('items')->sole());

    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->number)->toBeNull()
        ->and($invoice->total)->toBe($this->sale->total)
        ->and($invoice->discount_total)->toBe($this->sale->discount_total)
        ->and($invoice->items)->toHaveCount(1)
        ->and($invoice->items[0]->description)->toBe('Office chair')
        ->and($invoice->items[0]->line_total)->toBe($this->sale->items()->withoutGlobalScopes()->first()->line_total);
});

it('only invoices confirmed sales', function () {
    $draft = tenant()->run($this->company, function () {
        $this->actingAs($this->manager);

        return ($this->makeSale)(false);
    });

    $this->actingAs($this->accountant)
        ->post(route('sales.orders.invoice', $draft), ['due_date' => '2026-10-29'])
        ->assertSessionHasErrors('rule');
});

it('allows a single active invoice per sale', function () {
    draftInvoiceFor($this);

    $this->actingAs($this->accountant)
        ->post(route('sales.orders.invoice', $this->sale), ['due_date' => '2026-10-29'])
        ->assertSessionHasErrors('rule');
});

it('enforces a single active invoice per sale in the database', function () {
    $invoice = draftInvoiceFor($this);

    DB::table('invoices')->insert([
        ...collect($invoice->getAttributes())->except(['id', 'created_at', 'updated_at'])->all(),
        'id' => (string) Str::ulid(),
    ]);
})->throws(QueryException::class);

it('control: a cancelled duplicate is allowed by the partial index', function () {
    $invoice = draftInvoiceFor($this);

    DB::table('invoices')->insert([
        ...collect($invoice->getAttributes())->except(['id', 'created_at', 'updated_at'])->all(),
        'id' => (string) Str::ulid(),
        'status' => 'cancelled',
    ]);

    expect(DB::table('invoices')->count())->toBe(2);
});

it('lets a cancelled invoice be replaced', function () {
    $invoice = draftInvoiceFor($this);
    $this->actingAs($this->accountant)->post(route('finance.invoices.cancel', $invoice), ['reason' => 'Wrong due date']);

    $this->actingAs($this->accountant)
        ->post(route('sales.orders.invoice', $this->sale), ['due_date' => '2026-11-15'])
        ->assertSessionHasNoErrors();

    expect(tenant()->run($this->company, fn () => Invoice::count()))->toBe(2);
});

it('assigns the legal number only when issuing and queues the PDF', function () {
    Queue::fake();
    Event::fake([InvoiceIssued::class]);
    $first = draftInvoiceFor($this);

    $this->actingAs($this->accountant)->post(route('finance.invoices.issue', $first))->assertSessionHasNoErrors();

    $first = tenant()->run($this->company, fn () => $first->fresh());
    expect($first->status)->toBe(InvoiceStatus::Issued)
        ->and($first->number)->toBe('INV-000001')
        ->and($first->issue_date->toDateString())->toBe('2026-09-29')
        ->and($first->issued_by)->toBe($this->accountant->id)
        ->and($first->pdf_status)->toBe(PdfStatus::Pending);

    Queue::assertPushedOn('documents', GenerateInvoicePdf::class, fn (GenerateInvoicePdf $job) => $job->invoiceId === $first->id
        && $job->companyId === $this->company->id);
    Event::assertDispatched(InvoiceIssued::class);

    // Issuing twice is an invalid transition.
    $this->actingAs($this->accountant)->post(route('finance.invoices.issue', $first))->assertSessionHasErrors('rule');
});

it('generates the PDF through the queued job and stores it privately', function () {
    tenant()->run($this->company, fn () => app(IssueInvoice::class)->handle(draftInvoiceFor($this), $this->accountant));

    $invoice = tenant()->run($this->company, fn () => Invoice::sole());

    expect($invoice->pdf_status)->toBe(PdfStatus::Ready)
        ->and($invoice->pdf_path)->toBe("companies/{$this->company->id}/invoices/INV-000001.pdf");

    $content = Storage::disk('local')->get($invoice->pdf_path);
    expect(substr($content, 0, 4))->toBe('%PDF');
});

it('restores the tenant inside the worker from the captured company id', function () {
    Queue::fake();
    $invoice = tenant()->run($this->company, fn () => app(IssueInvoice::class)->handle(draftInvoiceFor($this), $this->accountant));

    /** @var GenerateInvoicePdf $job */
    $job = Queue::pushed(GenerateInvoicePdf::class)->first();
    tenant()->set(null);

    // Simulate the worker: unserialize and run through the job middleware.
    $job = unserialize(serialize($job));
    (new Illuminate\Pipeline\Pipeline(app()))
        ->send($job)
        ->through($job->middleware())
        ->then(fn ($job) => $job->handle());

    expect(tenant()->check())->toBeFalse()
        ->and(tenant()->run($this->company, fn () => $invoice->fresh()->pdf_status))->toBe(PdfStatus::Ready);
});

it('downloads the PDF only for authorised members of the company', function () {
    $invoice = tenant()->run($this->company, fn () => app(IssueInvoice::class)->handle(draftInvoiceFor($this), $this->accountant));
    [$outsider] = memberWithRole(SystemRole::Owner);
    [$warehouseUser] = memberWithRole(SystemRole::Warehouse, $this->company);

    $this->actingAs($this->accountant)
        ->get(route('finance.invoices.pdf', $invoice))
        ->assertOk()
        ->assertDownload('INV-000001.pdf');

    $this->actingAs($warehouseUser)->get(route('finance.invoices.pdf', $invoice))->assertForbidden();
    $this->actingAs($outsider)->get(route('finance.invoices.pdf', $invoice))->assertNotFound();
});

it('edits only draft invoices', function () {
    $invoice = draftInvoiceFor($this);

    $this->actingAs($this->accountant)
        ->put(route('finance.invoices.update', $invoice), ['due_date' => '2026-11-30', 'notes' => 'Updated'])
        ->assertSessionHasNoErrors();

    Queue::fake();
    $this->actingAs($this->accountant)->post(route('finance.invoices.issue', $invoice));

    $this->actingAs($this->accountant)
        ->put(route('finance.invoices.update', $invoice), ['due_date' => '2026-12-30'])
        ->assertSessionHasErrors('rule');
});

it('cancels unpaid invoices with a reason, keeping their number', function () {
    Queue::fake();
    $invoice = tenant()->run($this->company, fn () => app(IssueInvoice::class)->handle(draftInvoiceFor($this), $this->accountant));

    $this->actingAs($this->accountant)->post(route('finance.invoices.cancel', $invoice), ['reason' => 'Customer dispute'])->assertSessionHasNoErrors();

    $invoice = tenant()->run($this->company, fn () => $invoice->fresh());
    expect($invoice->status)->toBe(InvoiceStatus::Cancelled)
        ->and($invoice->number)->toBe('INV-000001')
        ->and($invoice->cancel_reason)->toBe('Customer dispute');
});

it('refuses to cancel invoices with payments', function () {
    Queue::fake();
    $invoice = tenant()->run($this->company, function () {
        $invoice = app(IssueInvoice::class)->handle(draftInvoiceFor($this), $this->accountant);
        $invoice->forceFill(['amount_paid' => 100])->save();

        return $invoice;
    });

    $this->actingAs($this->accountant)->post(route('finance.invoices.cancel', $invoice), ['reason' => 'x'])->assertSessionHasErrors('rule');
});

it('marks open invoices past their due date as overdue', function () {
    Queue::fake();
    $invoice = tenant()->run($this->company, fn () => app(IssueInvoice::class)->handle(draftInvoiceFor($this), $this->accountant));

    $this->artisan('invoices:mark-overdue')->assertSuccessful();
    expect(tenant()->run($this->company, fn () => $invoice->fresh()->status))->toBe(InvoiceStatus::Issued);

    $this->travelTo('2026-10-30 09:00:00');
    $this->artisan('invoices:mark-overdue')->expectsOutputToContain('1 invoices marked overdue')->assertSuccessful();

    expect(tenant()->run($this->company, fn () => $invoice->fresh()->status))->toBe(InvoiceStatus::Overdue);
});

it('lists and isolates invoices per company', function () {
    $invoice = draftInvoiceFor($this);
    [$outsider] = memberWithRole(SystemRole::Owner);

    $this->actingAs($this->accountant)
        ->get(route('finance.invoices.index'))
        ->assertInertia(fn (Assert $page) => $page->component('finance/invoices/Index')->has('invoices.data', 1));

    $this->actingAs($outsider)->get(route('finance.invoices.show', $invoice))->assertNotFound();
    $this->actingAs($outsider)->post(route('finance.invoices.issue', $invoice))->assertNotFound();
    $this->actingAs($outsider)->post(route('finance.invoices.cancel', $invoice), ['reason' => 'x'])->assertNotFound();
    $this->actingAs($outsider)->post(route('sales.orders.invoice', $this->sale), ['due_date' => '2026-10-29'])->assertNotFound();
});

it('requires invoicing permissions', function () {
    [$seller] = memberWithRole(SystemRole::Sales, $this->company);
    $invoice = draftInvoiceFor($this);

    // Sales can create invoices but not cancel them.
    $this->actingAs($seller)->post(route('finance.invoices.cancel', $invoice), ['reason' => 'x'])->assertForbidden();

    [$employee] = memberWithRole(SystemRole::Employee, $this->company);
    $this->actingAs($employee)->get(route('finance.invoices.index'))->assertForbidden();
});

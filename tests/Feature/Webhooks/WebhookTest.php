<?php

use App\Actions\Invoicing\CreateInvoiceFromSale;
use App\Actions\Invoicing\IssueInvoice;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\SaveSale;
use App\DTOs\SaleData;
use App\DTOs\StockMovementData;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Enums\WebhookEventStatus;
use App\Jobs\GenerateInvoicePdf;
use App\Jobs\ProcessWebhookEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WebhookEvent;
use App\Services\Inventory\InventoryService;
use App\Support\Webhooks\WebhookSignature;
use App\Webhooks\Contracts\WebhookHandler;
use App\Webhooks\Handlers\PaymentGatewayHandler;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    // Only the invoice PDF is faked; webhook jobs run on the sync queue.
    Queue::fake([GenerateInvoicePdf::class]);
    config(['webhooks.providers.payments.secret' => 'whsec_test']);
    $this->travelTo('2026-09-29 10:00:00');

    [$manager, $this->company] = memberWithRole(SystemRole::Manager);
    actAsCompany($this->company);

    $warehouse = Warehouse::factory()->default()->create();
    $product = Product::factory()->create();
    app(InventoryService::class)->record(new StockMovementData($product, $warehouse, 5, StockMovementType::ManualIn));

    // Issued invoice of 1000.00 (no tax).
    $sale = app(SaveSale::class)->handle(null, new SaleData(Customer::factory()->create()->id, $warehouse->id, '2026-09-29', null, [
        ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100000, 'discount_rate' => 0, 'tax_rate' => 0],
    ]), $manager);
    app(ConfirmSale::class)->handle($sale, $manager);
    $this->invoice = app(IssueInvoice::class)->handle(
        app(CreateInvoiceFromSale::class)->handle($sale, '2026-10-29', null, $manager),
        $manager,
    );

    tenant()->set(null);
});

function paymentEvent(object $test, array $data = [], array $overrides = []): array
{
    return [
        'id' => 'evt_1',
        'type' => 'payment.succeeded',
        'data' => [
            'company_id' => $test->company->id,
            'invoice_id' => $test->invoice->id,
            'amount' => 100000,
            'currency' => $test->invoice->currency,
            'paid_at' => '2026-09-29',
            'reference' => 'ch_1',
            ...$data,
        ],
        ...$overrides,
    ];
}

function sendWebhook(object $test, array $body, string $secret = 'whsec_test', ?int $timestamp = null, string $provider = 'payments'): TestResponse
{
    $json = (string) json_encode($body);

    return $test->call('POST', "/api/webhooks/{$provider}", server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_WEBHOOK_SIGNATURE' => WebhookSignature::sign($json, $secret, $timestamp ?? now()->getTimestamp()),
    ], content: $json);
}

function invoicePayments(object $test): int
{
    return tenant()->run($test->company, fn () => Payment::count());
}

function invoiceStatus(object $test): InvoiceStatus
{
    return tenant()->run($test->company, fn () => Invoice::find($test->invoice->id)->status);
}

describe('receiving', function () {
    it('stores the event, acknowledges with 202 and queues processing', function () {
        Queue::fake();

        sendWebhook($this, paymentEvent($this))
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.duplicate', false);

        $event = WebhookEvent::sole();
        expect($event->provider)->toBe('payments')
            ->and($event->external_id)->toBe('evt_1')
            ->and($event->status)->toBe(WebhookEventStatus::Pending);

        Queue::assertPushedOn('webhooks', ProcessWebhookEvent::class, fn ($job) => $job->eventId === $event->id);
    });

    it('rejects bad signatures without storing anything', function (string $case) {
        $response = match ($case) {
            'wrong secret' => sendWebhook($this, paymentEvent($this), secret: 'guess'),
            'expired timestamp' => sendWebhook($this, paymentEvent($this), timestamp: now()->getTimestamp() - 301),
            'tampered body' => $this->call('POST', '/api/webhooks/payments', server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => WebhookSignature::sign('{"id":"evt_1"}', 'whsec_test', now()->getTimestamp()),
            ], content: (string) json_encode(paymentEvent($this, ['amount' => 1]))),
            'missing header' => $this->postJson('/api/webhooks/payments', paymentEvent($this)),
        };

        $response->assertUnauthorized()->assertJsonPath('success', false);

        expect(WebhookEvent::count())->toBe(0)->and(invoicePayments($this))->toBe(0);
    })->with(['wrong secret', 'expired timestamp', 'tampered body', 'missing header']);

    it('answers 404 for unknown or unconfigured providers', function () {
        sendWebhook($this, paymentEvent($this), provider: 'nope')->assertNotFound();

        config(['webhooks.providers.payments.secret' => null]);
        sendWebhook($this, paymentEvent($this), secret: '')->assertNotFound();
    });

    it('requires an event id and type', function () {
        sendWebhook($this, ['type' => 'payment.succeeded'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.id.0', 'The id field is required.');
    });
});

describe('processing', function () {
    it('records the payment and settles the invoice', function () {
        sendWebhook($this, paymentEvent($this))->assertStatus(202);

        $event = WebhookEvent::sole();
        $payment = tenant()->run($this->company, fn () => Payment::sole());

        expect($event->status)->toBe(WebhookEventStatus::Processed)
            ->and($event->attempts)->toBe(1)
            ->and($event->company_id)->toBe($this->company->id)
            ->and($event->processed_at)->not->toBeNull()
            ->and($payment->amount)->toBe(100000)
            ->and($payment->method)->toBe(PaymentMethod::Card)
            ->and($payment->reference)->toBe('ch_1')
            ->and($payment->created_by)->toBeNull()
            ->and(invoiceStatus($this))->toBe(InvoiceStatus::Paid);
    });

    it('applies a redelivered event only once', function () {
        sendWebhook($this, paymentEvent($this, ['amount' => 40000]))->assertStatus(202);

        sendWebhook($this, paymentEvent($this, ['amount' => 40000]))
            ->assertOk()
            ->assertJsonPath('message', 'Event already received.')
            ->assertJsonPath('data.duplicate', true);

        // A redelivered job (e.g. worker crash after commit) is a no-op too.
        (new ProcessWebhookEvent(WebhookEvent::sole()->id))->handle();

        expect(WebhookEvent::count())->toBe(1)
            ->and(invoicePayments($this))->toBe(1)
            ->and(invoiceStatus($this))->toBe(InvoiceStatus::PartiallyPaid);
    });

    it('treats distinct events as distinct payments', function () {
        sendWebhook($this, paymentEvent($this, ['amount' => 40000]))->assertStatus(202);
        sendWebhook($this, paymentEvent($this, ['amount' => 60000, 'reference' => 'ch_2'], ['id' => 'evt_2']))->assertStatus(202);

        expect(invoicePayments($this))->toBe(2)->and(invoiceStatus($this))->toBe(InvoiceStatus::Paid);
    });

    it('never touches another company\'s invoice', function () {
        [, $attacker] = memberWithRole(SystemRole::Owner);

        sendWebhook($this, paymentEvent($this, ['company_id' => $attacker->id]))->assertStatus(202);

        $event = WebhookEvent::sole();
        expect($event->status)->toBe(WebhookEventStatus::Failed)
            ->and($event->last_error)->toBe('Unknown invoice.')
            ->and(invoicePayments($this))->toBe(0)
            ->and(invoiceStatus($this))->toBe(InvoiceStatus::Issued);
    });

    it('fails permanently on business rule violations and can be re-queued by redelivery', function () {
        sendWebhook($this, paymentEvent($this, ['amount' => 150000]))->assertStatus(202);

        $event = WebhookEvent::sole();
        expect($event->status)->toBe(WebhookEventStatus::Failed)
            ->and($event->attempts)->toBe(1)
            ->and($event->last_error)->toContain('exceeds the balance due')
            ->and(invoicePayments($this))->toBe(0);

        Queue::fake();
        sendWebhook($this, paymentEvent($this, ['amount' => 150000]))
            ->assertStatus(202)
            ->assertJsonPath('message', 'Event re-queued.');

        expect($event->fresh()->status)->toBe(WebhookEventStatus::Pending);
        Queue::assertPushed(ProcessWebhookEvent::class);
    });

    it('fails permanently on inconsistent data', function (array $data) {
        sendWebhook($this, paymentEvent($this, $data))->assertStatus(202);

        expect(WebhookEvent::sole()->status)->toBe(WebhookEventStatus::Failed)
            ->and(invoicePayments($this))->toBe(0);
    })->with([
        'currency mismatch' => [['currency' => 'EUR']],
        'unknown company' => [['company_id' => '01JZZZZZZZZZZZZZZZZZZZZZZZ']],
        'non-positive amount' => [['amount' => 0]],
    ]);

    it('ignores event types it does not act on', function () {
        sendWebhook($this, paymentEvent($this, overrides: ['type' => 'payment.failed']))->assertStatus(202);

        expect(WebhookEvent::sole()->status)->toBe(WebhookEventStatus::Ignored)
            ->and(invoicePayments($this))->toBe(0);
    });

    it('retries transient errors and marks the event failed once attempts run out', function () {
        app()->bind(PaymentGatewayHandler::class, fn () => new class implements WebhookHandler
        {
            public function handle(WebhookEvent $event): WebhookEventStatus
            {
                throw new RuntimeException('Database unavailable');
            }
        });

        Queue::fake();
        sendWebhook($this, paymentEvent($this))->assertStatus(202);
        $job = new ProcessWebhookEvent(WebhookEvent::sole()->id);

        expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'Database unavailable');

        $event = WebhookEvent::sole();
        expect($event->status)->toBe(WebhookEventStatus::Pending)
            ->and($event->attempts)->toBe(1)
            ->and($event->last_error)->toBe('Database unavailable')
            ->and($job->tries)->toBe(5)
            ->and($job->backoff)->toBe([10, 60, 300, 900]);

        $job->failed(new RuntimeException('Database unavailable'));
        expect($event->fresh()->status)->toBe(WebhookEventStatus::Failed);
    });

    it('re-queues failed events from the console', function () {
        sendWebhook($this, paymentEvent($this, ['amount' => 150000]))->assertStatus(202);
        expect(WebhookEvent::sole()->status)->toBe(WebhookEventStatus::Failed);

        Queue::fake();
        $this->artisan('webhooks:retry')->assertExitCode(2);
        $this->artisan('webhooks:retry', ['--all' => true])
            ->expectsOutputToContain('1 webhook events re-queued.')
            ->assertSuccessful();

        expect(WebhookEvent::sole()->status)->toBe(WebhookEventStatus::Pending);
        Queue::assertPushed(ProcessWebhookEvent::class, 1);
    });
});

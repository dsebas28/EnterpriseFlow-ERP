<?php

use App\Actions\Invoicing\CreateInvoiceFromSale;
use App\Actions\Invoicing\IssueInvoice;
use App\Actions\Purchasing\ChangePurchaseOrderStatus;
use App\Actions\Purchasing\SavePurchaseOrder;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\SaveSale;
use App\DTOs\PurchaseOrderData;
use App\DTOs\SaleData;
use App\DTOs\StockMovementData;
use App\Enums\MembershipStatus;
use App\Enums\NotificationCategory;
use App\Enums\Permission;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Events\MemberJoinedCompany;
use App\Jobs\GenerateInvoicePdf;
use App\Jobs\GenerateReportExport;
use App\Models\Customer;
use App\Models\Notification as NotificationModel;
use App\Models\Product;
use App\Models\ReportExport;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Notifications\InvoicesOverdueDigest;
use App\Notifications\LowStockAlert;
use App\Notifications\MemberJoinedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\PurchaseOrderApprovedNotification;
use App\Notifications\PurchaseOrderAwaitingApproval;
use App\Notifications\ReportExportFinished;
use App\Notifications\SaleConfirmedNotification;
use App\Notifications\SystemAlert;
use App\Services\Inventory\InventoryService;
use App\Services\Notifications\Notifier;
use App\Support\Webhooks\WebhookSignature;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->owner, $this->company] = memberWithRole(SystemRole::Owner);
    [$this->manager] = memberWithRole(SystemRole::Manager, $this->company);
    [$this->accountant] = memberWithRole(SystemRole::Accountant, $this->company);
    [$this->seller] = memberWithRole(SystemRole::Sales, $this->company);
    [$this->storekeeper] = memberWithRole(SystemRole::Warehouse, $this->company);
    [$this->employee] = memberWithRole(SystemRole::Employee, $this->company);
});

/**
 * @return list<int>
 */
function recipientIds(object $test, Permission $permission): array
{
    return app(Notifier::class)->membersWith($test->company, $permission)->pluck('id')->all();
}

function aSystemAlert(object $test, string $title = 'Heads up'): SystemAlert
{
    return new SystemAlert($test->company, $title, 'Something needs attention.', route('dashboard'));
}

describe('recipients', function () {
    it('resolves the members whose roles grant the permission, owners included', function () {
        expect(recipientIds($this, Permission::PurchasesApprove))->toEqualCanonicalizing([$this->owner->id, $this->manager->id])
            ->and(recipientIds($this, Permission::PurchasesReceive))->toEqualCanonicalizing([$this->owner->id, $this->manager->id, $this->storekeeper->id])
            ->and(recipientIds($this, Permission::CompanySettings))->toBe([$this->owner->id]);
    });

    it('skips suspended members, deactivated users and other companies', function () {
        $this->manager->membershipIn($this->company)->forceFill(['status' => MembershipStatus::Suspended])->save();
        $this->accountant->forceFill(['status' => 'inactive'])->save();
        [$outsider] = memberWithRole(SystemRole::Owner);

        expect(recipientIds($this, Permission::ReportsView))->toBe([$this->owner->id])
            ->not->toContain($outsider->id);
    });

    it('never notifies the actor', function () {
        Notification::fake();

        $notification = tenant()->run($this->company, fn () => new PurchaseOrderAwaitingApproval($this->company, purchaseOrderFor($this)));

        app(Notifier::class)->toPermittedMembers($notification, except: $this->manager->id);

        Notification::assertSentTo($this->owner, PurchaseOrderAwaitingApproval::class);
        Notification::assertNotSentTo($this->manager, PurchaseOrderAwaitingApproval::class);
    });
});

function purchaseOrderFor(object $test): App\Models\PurchaseOrder
{
    $product = Product::factory()->create();

    return app(SavePurchaseOrder::class)->handle(null, new PurchaseOrderData(
        Supplier::factory()->create(['name' => 'Acme Supplies'])->id,
        (Warehouse::first() ?? Warehouse::factory()->default()->create())->id,
        '2026-09-29',
        '2026-10-05',
        null,
        [['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 1000, 'tax_rate' => 0]],
    ), $test->manager);
}

describe('business events', function () {
    beforeEach(function () {
        Queue::fake([GenerateInvoicePdf::class]);
        Notification::fake();
        actAsCompany($this->company);
        $this->warehouse = Warehouse::factory()->default()->create();
    });

    it('alerts buyers when a product crosses its minimum stock, once', function () {
        $product = Product::factory()->create(['name' => 'Chair', 'sku' => 'CH-1', 'min_stock' => 5]);
        $stock = app(InventoryService::class);
        $stock->record(new StockMovementData($product, $this->warehouse, 6, StockMovementType::ManualIn));

        $stock->record(new StockMovementData($product, $this->warehouse, -2, StockMovementType::ManualOut));
        $stock->record(new StockMovementData($product, $this->warehouse, -1, StockMovementType::ManualOut));

        Notification::assertSentToTimes($this->owner, LowStockAlert::class, 1);
        Notification::assertSentTo($this->manager, LowStockAlert::class, function (LowStockAlert $n, array $channels) {
            return $channels === ['database', 'mail']
                && $n->title === 'Low stock: Chair'
                && str_contains($n->body, 'down to 4 units')
                && $n->companyId === $this->company->id;
        });
        Notification::assertNotSentTo([$this->seller, $this->storekeeper, $this->employee], LowStockAlert::class);
    });

    it('announces confirmed sales to report viewers, except whoever confirmed it', function () {
        $product = Product::factory()->create();
        app(InventoryService::class)->record(new StockMovementData($product, $this->warehouse, 5, StockMovementType::ManualIn));
        $sale = app(SaveSale::class)->handle(null, new SaleData(Customer::factory()->create(['name' => 'Globex'])->id, $this->warehouse->id, '2026-09-29', null, [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 250000, 'discount_rate' => 0, 'tax_rate' => 0],
        ]), $this->manager);

        $this->actingAs($this->manager);
        app(ConfirmSale::class)->handle($sale, $this->manager);

        Notification::assertSentTo($this->owner, SaleConfirmedNotification::class, fn ($n, array $channels) => $channels === ['database']
            && str_contains($n->body, 'Globex bought'));
        Notification::assertSentTo($this->accountant, SaleConfirmedNotification::class);
        Notification::assertNotSentTo([$this->manager, $this->seller], SaleConfirmedNotification::class);
    });

    it('asks approvers to review submitted orders and tells receivers once approved', function () {
        $order = purchaseOrderFor($this);
        $workflow = app(ChangePurchaseOrderStatus::class);

        $this->actingAs($this->manager);
        $workflow->submit($order);
        Notification::assertSentTo($this->owner, PurchaseOrderAwaitingApproval::class, fn ($n) => str_contains($n->body, 'Acme Supplies'));
        Notification::assertNotSentTo([$this->manager, $this->storekeeper], PurchaseOrderAwaitingApproval::class);

        $this->actingAs($this->owner);
        $workflow->approve($order, $this->owner);
        Notification::assertSentTo([$this->manager, $this->storekeeper], PurchaseOrderApprovedNotification::class);
        Notification::assertNotSentTo($this->owner, PurchaseOrderApprovedNotification::class);
    });

    it('sends a single overdue digest per run and never repeats it', function () {
        $this->travelTo('2026-09-29 10:00:00');
        $product = Product::factory()->create();
        app(InventoryService::class)->record(new StockMovementData($product, $this->warehouse, 5, StockMovementType::ManualIn));

        foreach (['2026-10-10', '2026-10-12'] as $due) {
            $sale = app(SaveSale::class)->handle(null, new SaleData(Customer::factory()->create()->id, $this->warehouse->id, '2026-09-29', null, [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100000, 'discount_rate' => 0, 'tax_rate' => 0],
            ]), $this->manager);
            app(ConfirmSale::class)->handle($sale, $this->manager);
            app(IssueInvoice::class)->handle(app(CreateInvoiceFromSale::class)->handle($sale, $due, null, $this->manager), $this->manager);
        }
        tenant()->set(null);

        $this->travelTo('2026-10-20 10:00:00');
        $this->artisan('invoices:mark-overdue')->assertSuccessful();
        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        Notification::assertSentToTimes($this->accountant, InvoicesOverdueDigest::class, 1);
        Notification::assertSentTo($this->accountant, InvoicesOverdueDigest::class, fn ($n, array $channels) => $n->title === '2 invoices became overdue'
            && str_contains($n->body, 'INV-000001, INV-000002')
            && in_array('mail', $channels, true));
    });

    it('welcomes new members to the team managers, not to themselves', function () {
        [$newcomer] = memberWithRole(SystemRole::Administrator, $this->company);

        MemberJoinedCompany::dispatch($newcomer->membershipIn($this->company));

        Notification::assertSentTo($this->owner, MemberJoinedNotification::class, fn ($n) => str_contains($n->title, $newcomer->name));
        Notification::assertNotSentTo([$newcomer, $this->manager], MemberJoinedNotification::class);
    });

    it('does not break the business operation when notifying fails', function () {
        $order = purchaseOrderFor($this);
        Notification::shouldReceive('send')->andThrow(new RuntimeException('mail server down'));

        $this->actingAs($this->manager)
            ->post(route('purchasing.orders.submit', $order))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($order->fresh()->status->value)->toBe('pending');
    });
});

describe('from background work', function () {
    it('notifies about payments applied by the gateway webhook, outside any request context', function () {
        Queue::fake([GenerateInvoicePdf::class]);
        config(['webhooks.providers.payments.secret' => 'whsec_test']);

        $invoice = tenant()->run($this->company, function () {
            $warehouse = Warehouse::factory()->default()->create();
            $product = Product::factory()->create();
            app(InventoryService::class)->record(new StockMovementData($product, $warehouse, 5, StockMovementType::ManualIn));
            $sale = app(SaveSale::class)->handle(null, new SaleData(Customer::factory()->create(['name' => 'Initech'])->id, $warehouse->id, '2026-09-29', null, [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100000, 'discount_rate' => 0, 'tax_rate' => 0],
            ]), $this->manager);
            app(ConfirmSale::class)->handle($sale, $this->manager);

            return app(IssueInvoice::class)->handle(app(CreateInvoiceFromSale::class)->handle($sale, '2026-10-29', null, $this->manager), $this->manager);
        });

        Notification::fake();
        $body = (string) json_encode(['id' => 'evt_9', 'type' => 'payment.succeeded', 'data' => [
            'company_id' => $this->company->id, 'invoice_id' => $invoice->id, 'amount' => 100000, 'currency' => $invoice->currency,
        ]]);
        $this->call('POST', '/api/webhooks/payments', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => WebhookSignature::sign($body, 'whsec_test', now()->getTimestamp()),
        ], content: $body)->assertStatus(202);

        Notification::assertSentTo($this->accountant, PaymentReceivedNotification::class, fn ($n) => str_contains($n->body, 'from Initech'));
        Notification::assertNotSentTo($this->storekeeper, PaymentReceivedNotification::class);
    });

    it('alerts administrators when a gateway event cannot be applied', function () {
        Notification::fake();
        config(['webhooks.providers.payments.secret' => 'whsec_test']);

        $body = (string) json_encode(['id' => 'evt_bad', 'type' => 'payment.succeeded', 'data' => [
            'company_id' => $this->company->id, 'invoice_id' => '01JZZZZZZZZZZZZZZZZZZZZZZZ', 'amount' => 100, 'currency' => 'USD',
        ]]);
        $this->call('POST', '/api/webhooks/payments', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => WebhookSignature::sign($body, 'whsec_test', now()->getTimestamp()),
        ], content: $body)->assertStatus(202);

        Notification::assertSentTo($this->owner, SystemAlert::class, fn (SystemAlert $n, array $channels) => str_contains($n->body, 'evt_bad')
            && $n->level === 'danger'
            && in_array('mail', $channels, true));
        Notification::assertNotSentTo($this->manager, SystemAlert::class);
    });

    it('tells the requester when their export is ready', function () {
        Storage::fake(ReportExport::DISK);
        Notification::fake();

        $this->actingAs($this->accountant)
            ->post(route('reports.export', 'profit'), ['format' => 'csv'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($this->accountant, ReportExportFinished::class, fn (ReportExportFinished $n) => $n->title === 'Your CSV export is ready'
            && str_contains((string) $n->url, '/reports/exports/'));
        Notification::assertNotSentTo($this->owner, ReportExportFinished::class);
    });

    it('tells the requester when their export fails', function () {
        Notification::fake();
        $export = tenant()->run($this->company, function () {
            $export = new ReportExport;
            $export->forceFill(['user_id' => $this->accountant->id, 'report' => 'profit', 'format' => 'pdf', 'filters' => [], 'status' => 'processing'])->save();

            return $export;
        });

        $job = tenant()->run($this->company, fn () => new GenerateReportExport($export->id));
        $job->failed(new RuntimeException('Out of memory'));

        Notification::assertSentTo($this->accountant, ReportExportFinished::class, fn ($n) => $n->title === 'Your PDF export failed' && $n->level === 'danger');
    });
});

describe('preferences', function () {
    it('delivers on the channels each user chose, with sensible defaults', function () {
        expect($this->owner->notificationChannels(NotificationCategory::LowStock))->toBe(['database', 'mail'])
            ->and($this->owner->notificationChannels(NotificationCategory::SaleConfirmed))->toBe(['database'])
            ->and($this->owner->notificationChannels(NotificationCategory::Invitation))->toBe(['database', 'mail']);

        $this->owner->forceFill(['notification_preferences' => [
            'low_stock' => ['database' => true, 'mail' => false],
            'sale_confirmed' => ['database' => false, 'mail' => false],
            // Transactional categories cannot be switched off.
            'invitation' => ['database' => false, 'mail' => false],
        ]])->save();

        expect($this->owner->notificationChannels(NotificationCategory::LowStock))->toBe(['database'])
            ->and($this->owner->notificationChannels(NotificationCategory::SaleConfirmed))->toBe([])
            ->and($this->owner->notificationChannels(NotificationCategory::Invitation))->toBe(['database', 'mail']);
    });

    it('shows and saves preferences, storing only known categories', function () {
        $this->actingAs($this->owner)
            ->get(route('notification-preferences.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Notifications')
                ->where('categories.0.value', 'low_stock')
                ->where('categories.0.mail', true)
                ->missing('categories.9'));

        $preferences = collect(NotificationCategory::configurable())
            ->mapWithKeys(fn (NotificationCategory $c) => [$c->value => ['database' => true, 'mail' => false]])
            ->put('made_up', ['database' => true, 'mail' => true])
            ->all();

        $this->actingAs($this->owner)
            ->put(route('notification-preferences.update'), ['preferences' => $preferences])
            ->assertSessionHasNoErrors();

        $stored = $this->owner->fresh()->notification_preferences;
        expect($stored)->toHaveCount(9)->not->toHaveKey('made_up')
            ->and($stored['low_stock'])->toBe(['database' => true, 'mail' => false]);

        $this->actingAs($this->owner)
            ->put(route('notification-preferences.update'), ['preferences' => ['low_stock' => ['database' => 'maybe']]])
            ->assertSessionHasErrors(['preferences.low_stock.database', 'preferences.low_stock.mail', 'preferences.sale_confirmed.mail']);
    });
});

describe('notification center', function () {
    it('stores the company and category with each in-app notification', function () {
        $this->owner->notify(aSystemAlert($this));

        $row = NotificationModel::sole();
        expect($row->company_id)->toBe($this->company->id)
            ->and($row->type)->toBe('system_alert')
            ->and($row->data)->toMatchArray(['title' => 'Heads up', 'level' => 'danger', 'company_name' => $this->company->name]);
    });

    it('mails with the company in the subject', function () {
        $mail = aSystemAlert($this)->toMail($this->owner);

        expect($mail->subject)->toBe("[{$this->company->name}] Heads up")
            ->and($mail->level)->toBe('error')
            ->and($mail->actionUrl)->toBe(route('dashboard'));
    });

    it('lists the active company\'s and personal notifications only', function () {
        [, $other] = memberWithRole(SystemRole::Owner, user: $this->owner);
        $this->owner->notify(aSystemAlert($this, 'Mine'));
        $this->owner->notify(new SystemAlert($other, 'Other company', 'x'));
        $this->manager->notify(aSystemAlert($this, 'Not mine'));

        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/Index')
                ->has('notifications.data', 1)
                ->where('notifications.data.0.title', 'Mine')
                ->where('unread', 1)
                // The bell's shared prop survives on the page listing notifications.
                ->where('unreadNotifications', 1));
    });

    it('opens a notification: marks it read and follows its link', function () {
        $this->owner->notify(aSystemAlert($this));
        $notification = NotificationModel::sole();

        $this->actingAs($this->owner)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('dashboard'));

        expect($notification->fresh()->read_at)->not->toBeNull();
    });

    it('does not follow links to other hosts', function () {
        $this->owner->notify(new SystemAlert($this->company, 'x', 'y', 'https://evil.example/phish'));

        $this->actingAs($this->owner)
            ->get(route('notifications.open', NotificationModel::sole()->id))
            ->assertRedirect(route('notifications.index'));
    });

    it('keeps other users\' notifications out of reach', function () {
        $this->manager->notify(aSystemAlert($this));
        $id = NotificationModel::sole()->id;

        $this->actingAs($this->owner)->get(route('notifications.open', $id))->assertNotFound();
        $this->actingAs($this->owner)->post(route('notifications.read', $id))->assertNotFound();
        expect(NotificationModel::sole()->read_at)->toBeNull();
    });

    it('marks everything read and reports the unread count for the bell', function () {
        $this->owner->notify(aSystemAlert($this, 'One'));
        $this->owner->notify(aSystemAlert($this, 'Two'));

        $this->actingAs($this->owner)->getJson(route('notifications.unread-count'))->assertExactJson(['count' => 2]);
        $this->actingAs($this->owner)->post(route('notifications.read-all'))->assertRedirect();
        $this->actingAs($this->owner)->getJson(route('notifications.unread-count'))->assertExactJson(['count' => 0]);
    });

    it('prunes read notifications past retention and keeps unread ones', function () {
        $this->owner->notify(aSystemAlert($this, 'Old read'));
        $this->owner->notify(aSystemAlert($this, 'Old unread'));
        $this->owner->notify(aSystemAlert($this, 'Recent read'));
        NotificationModel::all()->each(fn (NotificationModel $n) => $n->forceFill([
            'created_at' => $n->data['title'] === 'Recent read' ? now()->subDay() : now()->subDays(91),
            'read_at' => $n->data['title'] === 'Old unread' ? null : now(),
        ])->save());

        $this->artisan('model:prune', ['--model' => [NotificationModel::class]])->assertSuccessful();

        expect(NotificationModel::all()->pluck('data.title')->sort()->values()->all())->toBe(['Old unread', 'Recent read']);
    });
});

describe('api', function () {
    it('lists, filters and marks notifications of the selected company', function () {
        [, $other] = memberWithRole(SystemRole::Owner, user: $this->owner);
        $this->owner->notify(aSystemAlert($this, 'One'));
        $this->owner->notify(aSystemAlert($this, 'Two'));
        $this->owner->notify(new SystemAlert($other, 'Elsewhere', 'x'));
        Sanctum::actingAs($this->owner);
        $headers = ['X-Company-Id' => $this->company->id];

        $id = $this->getJson('/api/v1/notifications?unread=1', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.category', 'system_alert')
            ->json('data.0.id');

        $this->postJson("/api/v1/notifications/{$id}/read", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.read_at', fn ($value) => $value !== null);

        $this->postJson('/api/v1/notifications/read-all', [], $headers)->assertOk()->assertJsonPath('data.marked', 1);
        $this->getJson('/api/v1/notifications?unread=1', $headers)->assertJsonPath('meta.total', 0);

        // The other company's notification is untouched and visible there.
        $this->getJson('/api/v1/notifications?unread=1', ['X-Company-Id' => $other->id])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Elsewhere');
    });

    it('returns 404 for notifications of other users', function () {
        $this->manager->notify(aSystemAlert($this));
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/notifications/'.NotificationModel::sole()->id.'/read')->assertNotFound();
    });
});

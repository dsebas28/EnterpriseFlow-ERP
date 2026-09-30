<?php

namespace App\Listeners;

use App\Enums\InvoiceStatus;
use App\Events\InvoicesBecameOverdue;
use App\Events\MemberJoinedCompany;
use App\Events\PaymentReceived;
use App\Events\PurchaseOrderApproved;
use App\Events\PurchaseOrderSubmitted;
use App\Events\SaleConfirmed;
use App\Events\StockFellBelowMinimum;
use App\Models\Company;
use App\Models\Invoice;
use App\Notifications\CompanyNotification;
use App\Notifications\InvoicesOverdueDigest;
use App\Notifications\LowStockAlert;
use App\Notifications\MemberJoinedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\PurchaseOrderApprovedNotification;
use App\Notifications\PurchaseOrderAwaitingApproval;
use App\Notifications\SaleConfirmedNotification;
use App\Services\Notifications\Notifier;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Turns domain events into notifications (listeners are auto-discovered
 * from the `handle*` methods).
 *
 * The events fire after commit, so the business operation has already
 * succeeded: a notification problem is reported, never surfaced to the
 * user. The company is taken from the event's model rather than the
 * ambient context, which may be absent (queue jobs, webhooks, console).
 */
class SendBusinessNotifications
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly Notifier $notifier,
    ) {}

    public function handleStockFellBelowMinimum(StockFellBelowMinimum $event): void
    {
        $this->deliver(
            $event->product->company_id,
            fn (Company $company) => new LowStockAlert($company, $event->product, $event->totalStock),
        );
    }

    public function handleSaleConfirmed(SaleConfirmed $event): void
    {
        $this->deliver(
            $event->sale->company_id,
            fn (Company $company) => new SaleConfirmedNotification($company, $event->sale->loadMissing('customer')),
            except: Auth::id(),
        );
    }

    public function handlePurchaseOrderSubmitted(PurchaseOrderSubmitted $event): void
    {
        $this->deliver(
            $event->order->company_id,
            fn (Company $company) => new PurchaseOrderAwaitingApproval($company, $event->order->loadMissing('supplier')),
            except: Auth::id(),
        );
    }

    public function handlePurchaseOrderApproved(PurchaseOrderApproved $event): void
    {
        $this->deliver(
            $event->order->company_id,
            fn (Company $company) => new PurchaseOrderApprovedNotification($company, $event->order->loadMissing('supplier')),
            except: Auth::id(),
        );
    }

    public function handleInvoicesBecameOverdue(InvoicesBecameOverdue $event): void
    {
        $this->deliver($event->company->id, function (Company $company) use ($event): ?CompanyNotification {
            $invoices = Invoice::whereKey($event->invoiceIds)
                ->where('status', InvoiceStatus::Overdue)
                ->orderBy('due_date')
                ->get();

            if ($invoices->isEmpty()) {
                return null;
            }

            /** @var non-empty-list<string> $numbers */
            $numbers = $invoices->map(fn (Invoice $invoice) => (string) $invoice->number)->values()->all();
            $outstanding = (int) $invoices->sum(fn (Invoice $invoice) => $invoice->balanceDue());

            return new InvoicesOverdueDigest($company, $numbers, new Money($outstanding, $invoices->first()->currency));
        });
    }

    public function handlePaymentReceived(PaymentReceived $event): void
    {
        $this->deliver(
            $event->payment->company_id,
            fn (Company $company) => new PaymentReceivedNotification($company, $event->payment->loadMissing('invoice.customer')),
            except: Auth::id(),
        );
    }

    public function handleMemberJoinedCompany(MemberJoinedCompany $event): void
    {
        $membership = $event->membership;

        $this->deliver(
            $membership->company_id,
            fn (Company $company) => new MemberJoinedNotification($company, $membership->user),
            except: $membership->user_id,
        );
    }

    /**
     * @param  Closure(Company): ?CompanyNotification  $build  runs inside the company's tenant context
     */
    private function deliver(string $companyId, Closure $build, ?int $except = null): void
    {
        rescue(function () use ($companyId, $build, $except): void {
            $company = Company::find($companyId);

            if ($company === null) {
                return;
            }

            $notification = $this->tenant->run($company, fn () => $build($company));

            if ($notification !== null) {
                $this->notifier->toPermittedMembers($notification, $except);
            }
        }, report: true);
    }
}

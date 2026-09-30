<?php

namespace Tests\Support;

use App\Actions\Invoicing\CreateInvoiceFromSale;
use App\Actions\Invoicing\IssueInvoice;
use App\Actions\Invoicing\RegisterSupplierBill;
use App\Actions\Payments\RecordPayment;
use App\Actions\Purchasing\ChangePurchaseOrderStatus;
use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Actions\Purchasing\SavePurchaseOrder;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\SaveSale;
use App\DTOs\PaymentData;
use App\DTOs\PurchaseOrderData;
use App\DTOs\SaleData;
use App\DTOs\StockMovementData;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Jobs\GenerateInvoicePdf;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\ReportExport;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;

/**
 * One company with at least one record of every kind, in realistic states,
 * built through the real Actions (never raw inserts), so tests that sweep
 * routes or pages have something meaningful to point at.
 */
final class BusinessScenario
{
    public Warehouse $warehouse;

    public Category $category;

    public Product $chair;

    public Product $desk;

    public Customer $customer;

    public Supplier $supplier;

    /** Confirmed and invoiced. */
    public Sale $confirmedSale;

    public Sale $draftSale;

    /** Issued and partially paid. */
    public Invoice $invoice;

    public Payment $payment;

    /** Approved, partially received and billed. */
    public PurchaseOrder $approvedOrder;

    public PurchaseOrder $draftOrder;

    public SupplierBill $bill;

    public Expense $expense;

    public ReportExport $export;

    public static function build(Company $company, User $manager): self
    {
        // Invoice PDFs are irrelevant for scenarios and slow.
        Queue::fake([GenerateInvoicePdf::class]);

        $previousUser = Auth::user();
        Auth::setUser($manager);

        try {
            return app(TenantContext::class)->run($company, fn () => (new self)->populate($company, $manager));
        } finally {
            $previousUser ? Auth::setUser($previousUser) : Auth::forgetUser();
        }
    }

    private function populate(Company $company, User $manager): self
    {
        $this->warehouse = Warehouse::factory()->default()->create(['name' => 'Main warehouse']);
        $this->category = Category::factory()->create(['name' => 'Furniture']);
        $this->chair = Product::factory()->create(['name' => 'Chair', 'sku' => 'CH-1', 'cost' => 4000, 'price' => 10000, 'min_stock' => 2, 'category_id' => $this->category->id]);
        $this->desk = Product::factory()->create(['name' => 'Desk', 'sku' => 'DK-1', 'cost' => 20000, 'price' => 50000, 'min_stock' => 0]);
        $this->customer = Customer::factory()->create(['name' => 'Globex']);
        $this->supplier = Supplier::factory()->create(['name' => 'Acme Supplies']);

        app(InventoryService::class)->record(new StockMovementData($this->chair, $this->warehouse, 10, StockMovementType::ManualIn));

        $line = fn (Product $product, int $qty) => ['product_id' => $product->id, 'quantity' => $qty, 'unit_price' => $product->price, 'discount_rate' => 0, 'tax_rate' => 1900];
        $this->confirmedSale = app(ConfirmSale::class)->handle(
            app(SaveSale::class)->handle(null, new SaleData($this->customer->id, $this->warehouse->id, '2026-09-10', null, [$line($this->chair, 2)]), $manager),
            $manager,
        );
        $this->draftSale = app(SaveSale::class)->handle(null, new SaleData($this->customer->id, $this->warehouse->id, '2026-09-12', null, [$line($this->chair, 1)]), $manager);
        $invoice = app(IssueInvoice::class)->handle(
            app(CreateInvoiceFromSale::class)->handle($this->confirmedSale, '2026-10-10', null, $manager),
            $manager,
        );
        $this->payment = app(RecordPayment::class)->handle($invoice, new PaymentData(5000, PaymentMethod::BankTransfer, '2026-09-11', 'TRX-1'), $manager);
        $this->invoice = $invoice->fresh();

        $order = fn (string $date) => app(SavePurchaseOrder::class)->handle(null, new PurchaseOrderData(
            $this->supplier->id, $this->warehouse->id, $date, null, null,
            [['product_id' => $this->desk->id, 'quantity' => 4, 'unit_cost' => 20000, 'tax_rate' => 1900]],
        ), $manager);

        $approved = $order('2026-09-05');
        $workflow = app(ChangePurchaseOrderStatus::class);
        $workflow->submit($approved);
        $workflow->approve($approved, $manager);
        $item = $approved->items()->sole();
        app(ReceivePurchaseOrder::class)->handle($approved, [$item->id => 3], $manager);
        $this->bill = app(RegisterSupplierBill::class)->handle($approved, 'FV-1', '2026-09-06', '2026-10-06', [$item->id => 3], $manager);
        $this->approvedOrder = $approved->fresh();
        $this->draftOrder = $order('2026-09-15');

        $expense = new Expense;
        $expense->forceFill([
            'number' => 'EXP-000001',
            'category_id' => ExpenseCategory::create(['name' => 'Rent'])->id,
            'description' => 'Office rent',
            'amount' => 150000,
            'currency' => $company->currency,
            'expense_date' => '2026-09-01',
            'status' => 'approved',
            'created_by' => $manager->id,
        ])->save();
        $this->expense = $expense;

        $export = new ReportExport;
        $export->forceFill([
            'user_id' => $manager->id,
            'report' => 'sales-by-period',
            'format' => 'csv',
            'filters' => [],
            'status' => 'completed',
            'file_path' => "companies/{$company->id}/exports/scenario.csv",
            'rows_count' => 1,
            'completed_at' => now(),
        ])->save();
        $this->export = $export;

        return $this;
    }
}

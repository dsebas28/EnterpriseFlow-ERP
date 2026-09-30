<?php

use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ProductImageController;
use App\Http\Controllers\Catalog\ProductLookupController;
use App\Http\Controllers\Catalog\ProductVariantController;
use App\Http\Controllers\Catalog\WarehouseController;
use App\Http\Controllers\Companies\OnboardingController;
use App\Http\Controllers\Companies\SwitchCompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\SupplierBillController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\PurchaseOrderWorkflowController;
use App\Http\Controllers\Purchasing\SupplierController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\CustomerNoteController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\SaleWorkflowController;
use App\Http\Controllers\Team\AcceptInvitationController;
use App\Http\Controllers\Team\InvitationController;
use App\Http\Controllers\Team\MemberController;
use App\Http\Controllers\Team\RoleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Invitee flow: works for guests and for signed-in users of any company.
Route::get('invitations/{token}', [AcceptInvitationController::class, 'show'])->name('invitations.show');
Route::post('invitations/{token}/accept', [AcceptInvitationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('invitations.accept');

Route::middleware(['auth', 'verified'])->group(function () {
    // Tenant-less routes: the user may not belong to any company yet.
    Route::get('onboarding/company', [OnboardingController::class, 'create'])->name('onboarding.company.create');
    Route::post('onboarding/company', [OnboardingController::class, 'store'])->name('onboarding.company.store');
    Route::post('companies/{company}/switch', SwitchCompanyController::class)->name('companies.switch');

    // Everything below operates inside the active company.
    Route::middleware('tenant')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('catalog')->name('catalog.')->group(function () {
            Route::get('products/lookup', ProductLookupController::class)
                ->middleware('throttle:120,1')
                ->name('products.lookup');
            Route::resource('products', ProductController::class)->except('show');

            // Scoped bindings: a variant/image id is only resolved within its product.
            Route::scopeBindings()->group(function () {
                Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])->name('products.variants.store');
                Route::put('products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->name('products.variants.update');
                Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->name('products.variants.destroy');

                Route::post('products/{product}/images', [ProductImageController::class, 'store'])
                    ->middleware('throttle:30,1')
                    ->name('products.images.store');
                Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');
            });

            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        });

        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('stock', [StockController::class, 'index'])->name('stock.index');
            Route::get('movements', [StockMovementController::class, 'index'])->name('movements.index');
            Route::post('adjustments', [StockMovementController::class, 'adjust'])->name('adjustments.store');
            Route::post('manual-movements', [StockMovementController::class, 'manual'])->name('manual-movements.store');
            Route::post('transfers', [StockMovementController::class, 'transfer'])->name('transfers.store');

            Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
            Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
            Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
            Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
        });

        Route::prefix('purchasing')->name('purchasing.')->group(function () {
            Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
            Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

            Route::resource('orders', PurchaseOrderController::class)->parameters(['orders' => 'purchase_order']);
            Route::controller(PurchaseOrderWorkflowController::class)->prefix('orders/{purchase_order}')->name('orders.')->group(function () {
                Route::post('submit', 'submit')->name('submit');
                Route::post('approve', 'approve')->name('approve');
                Route::post('return-to-draft', 'returnToDraft')->name('return-to-draft');
                Route::post('cancel', 'cancel')->name('cancel');
                Route::post('receipts', 'receive')->name('receive');
            });
        });

        Route::prefix('sales')->name('sales.')->group(function () {
            Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
            Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
            Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
            Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
            Route::post('customers/{customer}/notes', [CustomerNoteController::class, 'store'])->name('customers.notes.store');
            Route::delete('customers/{customer}/notes/{note}', [CustomerNoteController::class, 'destroy'])
                ->scopeBindings()
                ->name('customers.notes.destroy');

            Route::resource('orders', SaleController::class)->parameters(['orders' => 'sale']);
            Route::controller(SaleWorkflowController::class)->prefix('orders/{sale}')->name('orders.')->group(function () {
                Route::post('pending', 'markPending')->name('pending');
                Route::post('return-to-draft', 'returnToDraft')->name('return-to-draft');
                Route::post('confirm', 'confirm')->name('confirm');
                Route::post('cancel', 'cancel')->name('cancel');
            });
        });

        Route::post('sales/orders/{sale}/invoice', [InvoiceController::class, 'store'])->name('sales.orders.invoice');
        Route::post('purchasing/orders/{purchase_order}/bills', [SupplierBillController::class, 'store'])->name('purchasing.orders.bills.store');

        Route::prefix('finance')->name('finance.')->group(function () {
            Route::controller(InvoiceController::class)->prefix('invoices')->name('invoices.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('{invoice}', 'show')->name('show');
                Route::put('{invoice}', 'update')->name('update');
                Route::post('{invoice}/issue', 'issue')->name('issue');
                Route::post('{invoice}/cancel', 'cancel')->name('cancel');
                Route::post('{invoice}/pdf', 'regeneratePdf')->middleware('throttle:10,1')->name('pdf.regenerate');
                Route::get('{invoice}/pdf', 'downloadPdf')->name('pdf');
            });

            Route::controller(ExpenseController::class)->prefix('expenses')->name('expenses.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::post('categories', 'storeCategory')->name('categories.store');
                // POST with _method=PUT: multipart uploads cannot use a real PUT in PHP.
                Route::put('{expense}', 'update')->name('update');
                Route::delete('{expense}', 'destroy')->name('destroy');
                Route::post('{expense}/approve', 'approve')->name('approve');
                Route::post('{expense}/reject', 'reject')->name('reject');
                Route::get('{expense}/receipt', 'receipt')->name('receipt');
            });

            Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::post('payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');
            Route::post('invoices/{invoice}/payments', [PaymentController::class, 'storeForInvoice'])->name('invoices.payments.store');
            Route::post('bills/{bill}/payments', [PaymentController::class, 'storeForBill'])->name('bills.payments.store');

            Route::controller(SupplierBillController::class)->prefix('bills')->name('bills.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('{bill}', 'show')->name('show');
                Route::post('{bill}/cancel', 'cancel')->name('cancel');
            });
        });

        Route::controller(ReportController::class)->prefix('reports')->name('reports.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('exports/{export}', 'download')->name('exports.download');
            Route::get('{key}', 'show')->where('key', '[a-z-]+')->name('show');
            Route::post('{key}/exports', 'export')->where('key', '[a-z-]+')->middleware('throttle:20,1')->name('export');
        });

        Route::prefix('team')->name('team.')->group(function () {
            Route::get('members', [MemberController::class, 'index'])->name('members.index');
            Route::put('members/{membership}/roles', [MemberController::class, 'updateRoles'])->name('members.roles');
            Route::post('members/{membership}/suspend', [MemberController::class, 'suspend'])->name('members.suspend');
            Route::post('members/{membership}/reactivate', [MemberController::class, 'reactivate'])->name('members.reactivate');

            Route::post('invitations', [InvitationController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('invitations.store');
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

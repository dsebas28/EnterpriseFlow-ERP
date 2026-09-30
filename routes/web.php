<?php

use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ProductImageController;
use App\Http\Controllers\Catalog\ProductLookupController;
use App\Http\Controllers\Catalog\ProductVariantController;
use App\Http\Controllers\Catalog\WarehouseController;
use App\Http\Controllers\Companies\OnboardingController;
use App\Http\Controllers\Companies\SwitchCompanyController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\PurchaseOrderWorkflowController;
use App\Http\Controllers\Purchasing\SupplierController;
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
        Route::get('dashboard', function () {
            return Inertia::render('Dashboard');
        })->name('dashboard');

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

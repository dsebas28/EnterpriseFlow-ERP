<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Webhooks\WebhookController;
use Illuminate\Support\Facades\Route;

/*
| API v1. Versioned by URL prefix so a future v2 can coexist.
| Every route below `auth:sanctum` resolves the active company from the
| X-Company-Id header (or the user's default company).
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');

        Route::middleware('tenant')->group(function () {
            Route::apiResource('products', ProductController::class);
            Route::apiResource('customers', CustomerController::class)->only(['index', 'store', 'show']);

            Route::apiResource('sales', SaleController::class)->only(['index', 'store', 'show']);
            Route::post('sales/{sale}/confirm', [SaleController::class, 'confirm'])->name('sales.confirm');
            Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');

            Route::apiResource('purchases', PurchaseOrderController::class)
                ->only(['index', 'store', 'show'])
                ->parameters(['purchases' => 'purchase_order']);

            Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');

            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
            Route::get('reports/{key}', [ReportController::class, 'show'])->where('key', '[a-z-]+')->name('reports.show');
        });
    });
});

/*
| Inbound webhooks. Unversioned: the payload contract belongs to the
| provider. Authenticated by HMAC signature, not by token.
*/
Route::post('webhooks/{provider}', WebhookController::class)
    ->where('provider', '[a-z0-9-]+')
    ->middleware('throttle:webhooks')
    ->name('webhooks.receive');

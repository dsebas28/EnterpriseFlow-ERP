<?php

use App\Http\Controllers\Companies\OnboardingController;
use App\Http\Controllers\Companies\SwitchCompanyController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

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
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

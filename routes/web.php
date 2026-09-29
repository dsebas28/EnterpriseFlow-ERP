<?php

use App\Http\Controllers\Companies\OnboardingController;
use App\Http\Controllers\Companies\SwitchCompanyController;
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

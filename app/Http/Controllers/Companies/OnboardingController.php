<?php

namespace App\Http\Controllers\Companies;

use App\Actions\Companies\CreateCompany;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveCurrentCompany;
use App\Http\Requests\Companies\StoreCompanyRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First-run flow for users that do not belong to any company yet.
 */
class OnboardingController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('onboarding/CreateCompany', [
            'defaults' => [
                'currency' => 'USD',
                'timezone' => config('app.timezone'),
            ],
        ]);
    }

    public function store(StoreCompanyRequest $request, CreateCompany $createCompany): RedirectResponse
    {
        $company = $createCompany->handle($request->user(), $request->toData());

        $request->session()->put(ResolveCurrentCompany::SESSION_KEY, $company->getKey());

        return to_route('dashboard');
    }
}

<?php

namespace App\Http\Controllers\Companies;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveCurrentCompany;
use App\Models\Company;
use App\Support\Logging\SecurityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SwitchCompanyController extends Controller
{
    public function __invoke(Request $request, Company $company, SecurityLogger $log): RedirectResponse
    {
        Gate::authorize('access', $company);

        $request->session()->put(ResolveCurrentCompany::SESSION_KEY, $company->getKey());

        $log->info('tenant.switched', ['company_id' => $company->getKey()]);

        return to_route('dashboard');
    }
}

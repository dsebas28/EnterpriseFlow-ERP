<?php

namespace App\Http\Controllers\Companies;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveCurrentCompany;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class SwitchCompanyController extends Controller
{
    public function __invoke(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('access', $company);

        $request->session()->put(ResolveCurrentCompany::SESSION_KEY, $company->getKey());

        Log::channel('security')->info('tenant.switched', [
            'user_id' => $request->user()?->getKey(),
            'company_id' => $company->getKey(),
            'ip' => $request->ip(),
        ]);

        return to_route('dashboard');
    }
}

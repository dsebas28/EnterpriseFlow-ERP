<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Services\Dashboard\DashboardMetrics;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Permission required to receive each dashboard section.
     */
    private const SECTIONS = [
        'sales' => Permission::SalesView,
        'finance' => Permission::ReportsView,
        'inventory' => Permission::InventoryView,
        'purchases' => Permission::PurchasesView,
    ];

    public function __invoke(Request $request, DashboardMetrics $metrics, TenantContext $tenant): Response
    {
        $user = $request->user();
        $data = $metrics->for($tenant->companyOrFail());

        // Sections are only sent to members allowed to see them: hiding them
        // in the UI would not be enough, the props would still leak.
        $props = ['generatedAt' => $data['generated_at']];
        foreach (self::SECTIONS as $section => $permission) {
            if ($user?->hasPermission($permission)) {
                $props[$section] = $data[$section];
            }
        }

        return Inertia::render('Dashboard', $props);
    }
}

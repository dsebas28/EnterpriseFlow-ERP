<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFiltersRequest;
use App\Reports\Column;
use App\Reports\Report;
use App\Reports\ReportRegistry;
use App\Support\Api\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Reports over the API use the exact same definitions as the web UI and
 * exports. Money columns are integers in minor units of `data.currency`.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportRegistry $registry) {}

    /**
     * Available reports and the filters each one accepts.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('reports.view');

        return ApiResponse::success(array_map(fn (Report $report) => [
            'key' => $report->key(),
            'title' => $report->title(),
            'group' => $report->group(),
            'filters' => $report->filters(),
        ], $this->registry->all()));
    }

    /**
     * Sales report (alias of `sales-by-period`).
     */
    public function sales(ReportFiltersRequest $request, TenantContext $tenant): JsonResponse
    {
        return ApiResponse::success($this->run($request, 'sales-by-period', $tenant));
    }

    /**
     * Run any report by key with the given filters.
     */
    public function show(ReportFiltersRequest $request, string $key, TenantContext $tenant): JsonResponse
    {
        return ApiResponse::success($this->run($request, $key, $tenant));
    }

    /**
     * @return array{report: string, columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, totals: array<string, mixed>, filters: array<string, mixed>, currency: string}
     */
    private function run(ReportFiltersRequest $request, string $key, TenantContext $tenant): array
    {
        $report = $this->registry->findOrFail($key);
        $filters = $request->toFilters();

        $rows = [];
        foreach ($report->rows($filters) as $row) {
            $rows[] = $row;
        }

        return [
            'report' => $report->key(),
            'columns' => array_map(fn (Column $column) => $column->toArray(), $report->columns()),
            'rows' => $rows,
            'totals' => $report->totals($rows),
            'filters' => $filters->toArray(),
            'currency' => $tenant->companyOrFail()->currency,
        ];
    }
}

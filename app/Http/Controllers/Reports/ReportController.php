<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFiltersRequest;
use App\Jobs\GenerateReportExport;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\ReportExport;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Reports\Column;
use App\Reports\Report;
use App\Reports\ReportRegistry;
use App\Support\Money\Currency;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Rows rendered on screen; exports always contain everything.
     */
    public const SCREEN_ROWS = 500;

    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly TenantContext $tenant,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('reports.view');

        return Inertia::render('reports/Index', [
            'reports' => collect($this->registry->all())
                ->map(fn (Report $r) => ['key' => $r->key(), 'title' => $r->title(), 'description' => $r->description(), 'group' => $r->group()])
                ->groupBy('group'),
            'exports' => $this->recentExports($request),
        ]);
    }

    public function show(ReportFiltersRequest $request, string $key): Response
    {
        $report = $this->registry->findOrFail($key);
        $filters = $request->toFilters();

        $rows = [];
        foreach ($report->rows($filters) as $row) {
            $rows[] = $row;
        }

        $company = $this->tenant->companyOrFail();

        return Inertia::render('reports/Show', [
            'report' => [
                'key' => $report->key(),
                'title' => $report->title(),
                'description' => $report->description(),
                'filters' => $report->filters(),
                'columns' => array_map(fn (Column $c) => $c->toArray(), $report->columns()),
            ],
            'rows' => array_slice($rows, 0, self::SCREEN_ROWS),
            'totalRows' => count($rows),
            'totals' => $report->totals($rows),
            'filters' => $filters->toArray(),
            'currency' => ['code' => $company->currency, 'decimals' => Currency::minorUnits($company->currency)],
            'options' => $this->filterOptions($report),
            'exports' => $this->recentExports($request, $report->key()),
            'canExport' => $request->user()?->can('reports.export') ?? false,
        ]);
    }

    public function export(ReportFiltersRequest $request, string $key): RedirectResponse
    {
        Gate::authorize('reports.export');

        $report = $this->registry->findOrFail($key);
        $request->validate(['format' => ['required']]);

        $export = new ReportExport;
        $export->forceFill([
            'user_id' => $request->user()?->id,
            'report' => $report->key(),
            'format' => $request->validated('format'),
            'filters' => $request->toFilters()->toArray(),
            'status' => 'pending',
        ])->save();

        GenerateReportExport::dispatch($export->id);

        return back()->with('status', 'Your export is being prepared. It will appear below when ready.');
    }

    /**
     * Exports are private to the member who requested them.
     */
    public function download(Request $request, ReportExport $export): StreamedResponse
    {
        abort_unless($export->user_id === $request->user()?->id, 404);
        abort_unless($export->status === 'completed' && $export->file_path && Storage::disk(ReportExport::DISK)->exists($export->file_path), 404);

        return Storage::disk(ReportExport::DISK)->download(
            $export->file_path,
            "{$export->report}-{$export->created_at?->format('Ymd-His')}.{$export->format}",
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentExports(Request $request, ?string $report = null): array
    {
        return ReportExport::query()
            ->where('user_id', $request->user()?->id)
            ->when($report, fn ($q) => $q->where('report', $report))
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ReportExport $e) => [
                'id' => $e->id,
                'report' => $e->report,
                'format' => $e->format,
                'status' => $e->status,
                'rows_count' => $e->rows_count,
                'error' => $e->error,
                'created_at' => $e->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Only the option lists the report actually filters by.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(Report $report): array
    {
        $wanted = $report->filters();
        $options = [];

        if (in_array('warehouse_id', $wanted, true)) {
            $options['warehouses'] = Warehouse::orderBy('name')->get(['id', 'name']);
        }
        if (in_array('category_id', $wanted, true)) {
            $options['categories'] = Category::orderBy('name')->get(['id', 'name']);
        }
        if (in_array('expense_category_id', $wanted, true)) {
            $options['expenseCategories'] = ExpenseCategory::orderBy('name')->get(['id', 'name']);
        }
        if (in_array('customer_id', $wanted, true)) {
            $options['customers'] = Customer::orderBy('name')->get(['id', 'name']);
        }
        if (in_array('supplier_id', $wanted, true)) {
            $options['suppliers'] = Supplier::orderBy('name')->get(['id', 'name']);
        }
        if (in_array('user_id', $wanted, true)) {
            $options['users'] = $this->tenant->companyOrFail()->users()->orderBy('name')->get(['users.id', 'users.name'])
                ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name]);
        }

        return $options;
    }
}

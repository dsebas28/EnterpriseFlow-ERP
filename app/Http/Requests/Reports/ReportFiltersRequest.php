<?php

namespace App\Http\Requests\Reports;

use App\Reports\ReportFilters;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates report filters. Every id must belong to the active company, so
 * a report can never be pointed at another tenant's data.
 */
class ReportFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reports.view') ?? false;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'group_by' => ['nullable', Rule::in(ReportFilters::GROUPINGS)],
            'warehouse_id' => ['nullable', 'string', TenantRule::exists('warehouses')],
            'category_id' => ['nullable', 'integer', TenantRule::exists('categories')],
            'expense_category_id' => ['nullable', 'integer', TenantRule::exists('expense_categories')],
            'customer_id' => ['nullable', 'string', TenantRule::exists('customers')],
            'supplier_id' => ['nullable', 'string', TenantRule::exists('suppliers')],
            // Users are not tenant rows: restrict to members of the company.
            'user_id' => ['nullable', 'integer', Rule::exists('company_user', 'user_id')->where(fn (Builder $q) => $q->where('company_id', app(TenantContext::class)->idOrFail()))],
            'format' => ['sometimes', Rule::in(['csv', 'xlsx', 'pdf'])],
        ];
    }

    public function toFilters(): ReportFilters
    {
        $company = app(TenantContext::class)->companyOrFail();

        return ReportFilters::fromArray($this->validated(), now($company->timezone)->toDateString());
    }
}

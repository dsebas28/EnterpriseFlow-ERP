<?php

namespace App\Http\Requests\Finance;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Support\Money\Currency;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SaveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            ? $this->user()?->can('update', $expense) ?? false
            : $this->user()?->can('create', Expense::class) ?? false;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', TenantRule::exists('expense_categories')],
            'supplier_id' => ['nullable', 'string', TenantRule::exists('suppliers')->whereNull('deleted_at')],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'decimal:0,'.Currency::minorUnits($this->currency()), 'gt:0', 'max:9999999999'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            // Content-sniffed MIME type; no SVG/HTML that could carry scripts.
            'receipt' => ['nullable', File::types(['pdf', 'jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)],
        ];
    }

    /**
     * @return array{category_id: int, supplier_id: string|null, description: string, amount: int, expense_date: string, payment_method: string|null}
     */
    public function toData(): array
    {
        return [
            'category_id' => (int) $this->validated('category_id'),
            'supplier_id' => $this->validated('supplier_id'),
            'description' => $this->validated('description'),
            'amount' => Money::fromDecimal((string) $this->validated('amount'), $this->currency())->minor,
            'expense_date' => $this->validated('expense_date'),
            'payment_method' => $this->validated('payment_method'),
        ];
    }

    private function currency(): string
    {
        return app(TenantContext::class)->companyOrFail()->currency;
    }
}

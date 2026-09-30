<?php

namespace App\Http\Requests\Finance;

use App\DTOs\PaymentData;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Support\Money\Currency;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Payment::class) ?? false;
    }

    /**
     * The upper bound (balance due) is checked in the action under a lock.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'decimal:0,'.Currency::minorUnits($this->currency()), 'gt:0', 'max:9999999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toData(): PaymentData
    {
        return PaymentData::fromArray($this->validated(), $this->currency());
    }

    private function currency(): string
    {
        return app(TenantContext::class)->companyOrFail()->currency;
    }
}

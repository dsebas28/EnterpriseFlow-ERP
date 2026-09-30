<?php

namespace App\Http\Requests\Sales;

use App\Enums\PartyStatus;
use App\Models\Customer;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            ? $this->user()?->can('update', $customer) ?? false
            : $this->user()?->can('create', Customer::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('tax_id'))) {
            $this->merge(['tax_id' => trim($this->input('tax_id')) ?: null]);
        }
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'kind' => ['required', Rule::in(['company', 'person'])],
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => [
                'nullable', 'string', 'max:50',
                TenantRule::unique('customers', 'tax_id')->ignore($customer instanceof Customer ? $customer->id : null),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'status' => ['required', Rule::enum(PartyStatus::class)],
        ];
    }
}

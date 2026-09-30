<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\PartyStatus;
use App\Models\Supplier;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier
            ? $this->user()?->can('update', $supplier) ?? false
            : $this->user()?->can('create', Supplier::class) ?? false;
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
        $supplier = $this->route('supplier');

        return [
            'kind' => ['required', Rule::in(['company', 'person'])],
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => [
                'nullable', 'string', 'max:50',
                TenantRule::unique('suppliers', 'tax_id')->ignore($supplier instanceof Supplier ? $supplier->id : null),
            ],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(PartyStatus::class)],
        ];
    }
}

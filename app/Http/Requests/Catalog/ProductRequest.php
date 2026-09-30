<?php

namespace App\Http\Requests\Catalog;

use App\DTOs\ProductData;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Support\Money\Currency;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Shared rules for products and variants. Money arrives as decimal strings
 * with the precision of the company currency and is converted to minor
 * units in the DTO.
 */
abstract class ProductRequest extends FormRequest
{
    /**
     * The product being edited (null on create). Its id is excluded from
     * the SKU/barcode uniqueness checks.
     */
    abstract protected function editedProduct(): ?Product;

    /**
     * Normalise identifiers before validating, so uniqueness is checked
     * against exactly the value that will be stored ("wm-001" == "WM-001").
     */
    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'sku' => is_string($this->input('sku')) ? Str::upper(trim($this->input('sku'))) : null,
            'barcode' => is_string($this->input('barcode')) ? trim($this->input('barcode')) : null,
        ], fn ($value) => $value !== null));
    }

    /**
     * @return array<string, array<mixed>>
     */
    protected function commonRules(): array
    {
        $ignore = $this->editedProduct()?->id;
        $money = ['required', 'decimal:0,'.Currency::minorUnits($this->currency()), 'min:0', 'max:9999999999'];

        return [
            // SKUs of soft-deleted products stay reserved (the unique rule
            // intentionally does not exclude trashed rows, matching the index).
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\/-]+$/', TenantRule::unique('products', 'sku')->ignore($ignore)],
            'barcode' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/', TenantRule::unique('products', 'barcode')->ignore($ignore)],
            'cost' => $money,
            'price' => $money,
            'min_stock' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.regex' => 'The SKU may only contain letters, numbers, dots, dashes, underscores and slashes.',
            'barcode.regex' => 'The barcode may only contain letters, numbers and dashes.',
        ];
    }

    public function toData(?ProductType $type = null): ProductData
    {
        return ProductData::fromArray($this->validated(), $this->currency(), $type);
    }

    protected function currency(): string
    {
        return app(TenantContext::class)->companyOrFail()->currency;
    }
}

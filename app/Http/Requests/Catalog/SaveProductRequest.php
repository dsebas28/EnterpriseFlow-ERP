<?php

namespace App\Http\Requests\Catalog;

use App\Enums\ProductType;
use App\Models\Product;
use App\Support\Tenancy\TenantRule;
use Illuminate\Validation\Rule;

class SaveProductRequest extends ProductRequest
{
    public function authorize(): bool
    {
        $product = $this->editedProduct();

        return $product
            ? $this->user()?->can('update', $product) ?? false
            : $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            // The type is chosen once, at creation.
            'type' => $this->editedProduct()
                ? ['prohibited']
                : ['required', Rule::in([ProductType::Simple->value, ProductType::Variable->value])],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', TenantRule::exists('categories')->whereNull('deleted_at')],
            'tax_rate' => ['required', 'decimal:0,2', 'min:0', 'max:100'],
        ];
    }

    protected function editedProduct(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }
}

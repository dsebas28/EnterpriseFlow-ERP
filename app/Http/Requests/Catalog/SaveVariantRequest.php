<?php

namespace App\Http\Requests\Catalog;

use App\DTOs\ProductData;
use App\Enums\ProductType;
use App\Models\Product;

class SaveVariantRequest extends ProductRequest
{
    public function authorize(): bool
    {
        $parent = $this->route('product');

        return $parent instanceof Product && ($this->user()?->can('update', $parent) ?? false);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            'attributes' => ['required', 'array', 'min:1', 'max:5'],
            'attributes.*.name' => ['required', 'string', 'max:30', 'distinct:ignore_case'],
            'attributes.*.value' => ['required', 'string', 'max:50'],
        ];
    }

    public function toVariantData(): ProductData
    {
        return $this->toData(ProductType::Variant);
    }

    protected function editedProduct(): ?Product
    {
        $variant = $this->route('variant');

        return $variant instanceof Product ? $variant : null;
    }
}

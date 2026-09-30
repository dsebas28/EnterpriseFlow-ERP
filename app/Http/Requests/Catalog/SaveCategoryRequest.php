<?php

namespace App\Http\Requests\Catalog;

use App\Models\Category;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            ? $this->user()?->can('update', $category) ?? false
            : $this->user()?->can('create', Category::class) ?? false;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', TenantRule::exists('categories')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}

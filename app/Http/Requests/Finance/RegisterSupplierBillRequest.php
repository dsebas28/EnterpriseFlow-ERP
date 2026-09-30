<?php

namespace App\Http\Requests\Finance;

use App\Models\SupplierBill;
use Illuminate\Foundation\Http\FormRequest;

class RegisterSupplierBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SupplierBill::class) ?? false;
    }

    /**
     * Billable quantities are checked in the action under a row lock.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_reference' => ['required', 'string', 'max:60'],
            'bill_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['required', 'date', 'after_or_equal:bill_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function quantities(): array
    {
        $quantities = [];

        foreach ($this->validated('lines') as $line) {
            $quantities[(int) $line['item_id']] = (int) $line['quantity'];
        }

        return $quantities;
    }
}

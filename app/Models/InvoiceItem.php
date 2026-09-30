<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $invoice_id
 * @property string $product_id
 * @property string $description
 * @property int $quantity
 * @property int $unit_price
 * @property int $discount_rate
 * @property int $tax_rate
 * @property int $line_discount
 * @property int $line_subtotal
 * @property int $line_tax
 * @property int $line_total
 * @property int $position
 */
class InvoiceItem extends Model
{
    use BelongsToCompany;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'discount_rate' => 'integer',
            'tax_rate' => 'integer',
            'line_discount' => 'integer',
            'line_subtotal' => 'integer',
            'line_tax' => 'integer',
            'line_total' => 'integer',
        ];
    }
}

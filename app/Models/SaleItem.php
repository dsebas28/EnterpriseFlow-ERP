<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $sale_id
 * @property string $product_id
 * @property string $description
 * @property int $quantity
 * @property int $unit_price
 * @property int $discount_rate
 * @property int $tax_rate
 * @property int|null $unit_cost
 * @property int $line_discount
 * @property int $line_subtotal
 * @property int $line_tax
 * @property int $line_total
 * @property int $position
 */
class SaleItem extends Model
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
            'unit_cost' => 'integer',
            'line_discount' => 'integer',
            'line_subtotal' => 'integer',
            'line_tax' => 'integer',
            'line_total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}

<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $company_id
 * @property string $purchase_order_id
 * @property string $product_id
 * @property string $description
 * @property int $quantity
 * @property int $received_quantity
 * @property int $unit_cost
 * @property int $tax_rate
 * @property int $line_subtotal
 * @property int $line_tax
 * @property int $line_total
 * @property int $position
 */
class PurchaseOrderItem extends Model
{
    use BelongsToCompany;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'received_quantity' => 'integer',
            'unit_cost' => 'integer',
            'tax_rate' => 'integer',
            'line_subtotal' => 'integer',
            'line_tax' => 'integer',
            'line_total' => 'integer',
        ];
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->quantity - $this->received_quantity);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}

<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $supplier_bill_id
 * @property int $purchase_order_item_id
 * @property string $product_id
 * @property string $description
 * @property int $quantity
 * @property int $unit_cost
 * @property int $tax_rate
 * @property int $line_subtotal
 * @property int $line_tax
 * @property int $line_total
 */
class SupplierBillItem extends Model
{
    use BelongsToCompany;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'tax_rate' => 'integer',
            'line_subtotal' => 'integer',
            'line_tax' => 'integer',
            'line_total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }
}

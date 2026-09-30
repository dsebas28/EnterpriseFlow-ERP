<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current quantity of a product in a warehouse: a projection of the stock
 * ledger, maintained by InventoryService in the same transaction as each
 * movement. `inventory:rebuild` can recompute it from the ledger.
 *
 * @property int $id
 * @property string $company_id
 * @property string $product_id
 * @property string $warehouse_id
 * @property int $quantity
 */
class StockLevel extends Model
{
    use BelongsToCompany;

    public const CREATED_AT = null;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}

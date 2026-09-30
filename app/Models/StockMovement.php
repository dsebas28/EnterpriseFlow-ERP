<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One line of the inventory ledger. Append-only: rows are never updated or
 * deleted (also enforced by database triggers). Mistakes are corrected by
 * recording a compensating movement.
 *
 * @property int $id
 * @property string $company_id
 * @property string $product_id
 * @property string $warehouse_id
 * @property StockMovementType $type
 * @property int $quantity
 * @property int $balance_after
 * @property int|null $unit_cost
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string|null $transfer_id
 * @property int|null $user_id
 * @property string|null $notes
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 */
class StockMovement extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    /**
     * Written exclusively by InventoryService.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock movements are append-only.'));
        static::deleting(fn () => throw new LogicException('Stock movements are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'balance_after' => 'integer',
            'unit_cost' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The business document that caused the movement (sale, purchase…).
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}

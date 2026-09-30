<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidStateTransition;
use App\Support\Audit\Auditable;
use App\Support\Money\Money;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $company_id
 * @property string $number
 * @property string $supplier_id
 * @property string $warehouse_id
 * @property PurchaseOrderStatus $status
 * @property Carbon $order_date
 * @property Carbon|null $expected_date
 * @property string $currency
 * @property int $subtotal
 * @property int $tax_total
 * @property int $total
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon|null $received_at
 * @property Carbon|null $created_at
 */
class PurchaseOrder extends Model
{
    use Auditable, BelongsToCompany, HasUlids;

    /**
     * Written exclusively by the purchasing actions (status, totals and
     * audit columns must never come from request input).
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'date',
            'expected_date' => 'date',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * Move to a new status, enforcing the state machine in PurchaseOrderStatus.
     */
    public function transitionTo(PurchaseOrderStatus $status): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw InvalidStateTransition::between('purchase order', $this->status, $status);
        }

        $this->status = $status;
    }

    public function money(int $minor): Money
    {
        return new Money($minor, $this->currency);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<PurchaseReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class)->latest('received_at');
    }

    /**
     * @return HasMany<SupplierBill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(SupplierBill::class)->latest();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

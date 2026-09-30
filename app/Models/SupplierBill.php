<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvalidStateTransition;
use App\Support\Money\Money;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A supplier's invoice registered against a purchase order (accounts payable).
 *
 * @property string $id
 * @property string $company_id
 * @property string $number
 * @property string $supplier_id
 * @property string $purchase_order_id
 * @property string $supplier_reference
 * @property InvoiceStatus $status
 * @property Carbon $bill_date
 * @property Carbon $due_date
 * @property string $currency
 * @property int $subtotal
 * @property int $tax_total
 * @property int $total
 * @property int $amount_paid
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon|null $created_at
 */
class SupplierBill extends Model
{
    use BelongsToCompany, HasUlids;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'bill_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    public function transitionTo(InvoiceStatus $status): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw InvalidStateTransition::between('supplier bill', $this->status, $status);
        }

        $this->status = $status;
    }

    public function balanceDue(): int
    {
        return $this->status->isOpen() ? max(0, $this->total - $this->amount_paid) : 0;
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
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return HasMany<SupplierBillItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierBillItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

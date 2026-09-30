<?php

namespace App\Models;

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Support\Audit\Auditable;
use App\Support\Money\Money;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Money received from a customer or paid to a supplier.
 *
 * Immutable once posted: the only allowed change is voiding it (with a
 * reason). Financial history is never silently rewritten.
 *
 * @property string $id
 * @property string $company_id
 * @property string $number
 * @property PaymentDirection $direction
 * @property string|null $invoice_id
 * @property string|null $supplier_bill_id
 * @property PaymentMethod $method
 * @property int $amount
 * @property string $currency
 * @property Carbon $paid_at
 * @property string|null $reference
 * @property string|null $notes
 * @property PaymentStatus $status
 * @property int|null $created_by
 * @property int|null $voided_by
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property Carbon|null $created_at
 */
class Payment extends Model
{
    use Auditable, BelongsToCompany, HasUlids;

    private const VOID_COLUMNS = ['status', 'voided_by', 'voided_at', 'void_reason', 'updated_at'];

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(function (Payment $payment): void {
            $changed = array_keys($payment->getDirty());

            if (array_diff($changed, self::VOID_COLUMNS) !== [] || $payment->getOriginal('status') === PaymentStatus::Voided) {
                throw new LogicException('Payments are immutable; void the payment and record a new one.');
            }
        });

        static::deleting(fn () => throw new LogicException('Payments cannot be deleted; void them instead.'));
    }

    protected function casts(): array
    {
        return [
            'direction' => PaymentDirection::class,
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'paid_at' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function money(): Money
    {
        return new Money($this->amount, $this->currency);
    }

    public function isVoided(): bool
    {
        return $this->status === PaymentStatus::Voided;
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<SupplierBill, $this>
     */
    public function supplierBill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class);
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
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}

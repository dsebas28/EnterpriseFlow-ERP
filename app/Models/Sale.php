<?php

namespace App\Models;

use App\Enums\SaleStatus;
use App\Exceptions\InvalidStateTransition;
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
 * @property string $customer_id
 * @property string $warehouse_id
 * @property SaleStatus $status
 * @property Carbon $sale_date
 * @property string $currency
 * @property int $discount_total
 * @property int $subtotal
 * @property int $tax_total
 * @property int $total
 * @property int $amount_paid
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon|null $created_at
 */
class Sale extends Model
{
    use BelongsToCompany, HasUlids;

    /**
     * Written exclusively by the sales actions.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'sale_date' => 'date',
            'discount_total' => 'integer',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function transitionTo(SaleStatus $status): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw InvalidStateTransition::between('sale', $this->status, $status);
        }

        $this->status = $status;
    }

    public function balanceDue(): int
    {
        return $this->status->hasDeductedStock() ? max(0, $this->total - $this->amount_paid) : 0;
    }

    public function money(int $minor): Money
    {
        return new Money($minor, $this->currency);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('position');
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
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}

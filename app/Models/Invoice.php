<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PdfStatus;
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
 * A customer invoice (accounts receivable), issued from a confirmed sale.
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $number
 * @property string $sale_id
 * @property string $customer_id
 * @property InvoiceStatus $status
 * @property Carbon|null $issue_date
 * @property Carbon $due_date
 * @property string $currency
 * @property int $discount_total
 * @property int $subtotal
 * @property int $tax_total
 * @property int $total
 * @property int $amount_paid
 * @property string|null $notes
 * @property PdfStatus|null $pdf_status
 * @property string|null $pdf_path
 * @property Carbon|null $pdf_generated_at
 * @property int|null $created_by
 * @property int|null $issued_by
 * @property Carbon|null $issued_at
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon|null $created_at
 */
class Invoice extends Model
{
    use Auditable, BelongsToCompany, HasUlids;

    public const PDF_DISK = 'local';

    /**
     * Written exclusively by the invoicing actions.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'pdf_status' => PdfStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'discount_total' => 'integer',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'pdf_generated_at' => 'datetime',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function transitionTo(InvoiceStatus $status): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw InvalidStateTransition::between('invoice', $this->status, $status);
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
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_at')->orderByDesc('number');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}

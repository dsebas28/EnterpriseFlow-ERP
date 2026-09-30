<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Support\Audit\Auditable;
use App\Support\Money\Money;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $company_id
 * @property string $number
 * @property int $category_id
 * @property string|null $supplier_id
 * @property string $description
 * @property int $amount
 * @property string $currency
 * @property Carbon $expense_date
 * @property PaymentMethod|null $payment_method
 * @property string|null $receipt_path
 * @property string|null $receipt_name
 * @property ExpenseStatus $status
 * @property int|null $created_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 */
class Expense extends Model
{
    use Auditable, BelongsToCompany, HasUlids;

    public const RECEIPT_DISK = 'local';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => ExpenseStatus::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'integer',
            'expense_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function money(): Money
    {
        return new Money($this->amount, $this->currency);
    }

    public function isPending(): bool
    {
        return $this->status === ExpenseStatus::Pending;
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
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
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

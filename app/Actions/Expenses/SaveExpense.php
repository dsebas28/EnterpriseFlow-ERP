<?php

namespace App\Actions\Expenses;

use App\Enums\DocumentType;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Expense;
use App\Models\User;
use App\Services\Documents\DocumentNumberGenerator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Records or edits a pending expense, optionally with a receipt file.
 */
final class SaveExpense
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array{category_id: int, supplier_id?: string|null, description: string, amount: int, expense_date: string, payment_method?: string|null}  $data
     */
    public function handle(?Expense $expense, array $data, ?UploadedFile $receipt, User $user): Expense
    {
        if ($expense !== null && ! $expense->isPending()) {
            throw new BusinessRuleViolation('Only pending expenses can be edited.');
        }

        $newPath = $receipt?->store("companies/{$this->tenant->idOrFail()}/expenses", Expense::RECEIPT_DISK);
        $oldPath = $expense?->receipt_path;

        try {
            $saved = DB::transaction(function () use ($expense, $data, $receipt, $newPath, $user): Expense {
                if ($expense === null) {
                    $expense = new Expense;
                    $expense->forceFill([
                        'number' => $this->numbers->next(DocumentType::Expense),
                        'currency' => $this->tenant->companyOrFail()->currency,
                        'status' => ExpenseStatus::Pending,
                        'created_by' => $user->id,
                    ]);
                }

                $expense->forceFill([
                    'category_id' => $data['category_id'],
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'description' => $data['description'],
                    'amount' => $data['amount'],
                    'expense_date' => $data['expense_date'],
                    'payment_method' => isset($data['payment_method']) ? PaymentMethod::from($data['payment_method']) : null,
                ]);

                if ($receipt !== null && $newPath) {
                    $expense->forceFill(['receipt_path' => $newPath, 'receipt_name' => $receipt->getClientOriginalName()]);
                }

                $expense->save();

                return $expense;
            });
        } catch (Throwable $e) {
            // Do not leave orphaned files when the database write fails.
            if ($newPath) {
                Storage::disk(Expense::RECEIPT_DISK)->delete($newPath);
            }

            throw $e;
        }

        if ($newPath && $oldPath) {
            Storage::disk(Expense::RECEIPT_DISK)->delete($oldPath);
        }

        return $saved;
    }
}

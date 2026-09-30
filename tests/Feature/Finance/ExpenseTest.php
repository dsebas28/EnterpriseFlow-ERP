<?php

use App\Enums\ExpenseStatus;
use App\Enums\SystemRole;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-09-29 10:00:00');

    [$this->accountant, $this->company] = memberWithRole(SystemRole::Accountant);
    [$this->manager] = memberWithRole(SystemRole::Manager, $this->company);
    $this->category = tenant()->run($this->company, fn () => ExpenseCategory::create(['name' => 'Office supplies']));
});

function expensePayload(object $test, array $overrides = []): array
{
    return [
        'category_id' => $test->category->id,
        'description' => 'Printer paper',
        'amount' => '85000.50',
        'expense_date' => '2026-09-28',
        'payment_method' => 'card',
        ...$overrides,
    ];
}

function recordExpense(object $test, array $overrides = []): Expense
{
    $test->actingAs($test->accountant)->post(route('finance.expenses.store'), expensePayload($test, $overrides))->assertSessionHasNoErrors();

    return tenant()->run($test->company, fn () => Expense::latest('number')->first());
}

it('records a pending expense with a private receipt', function () {
    $expense = recordExpense($this, ['receipt' => UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf')]);

    expect($expense->number)->toBe('EXP-000001')
        ->and($expense->status)->toBe(ExpenseStatus::Pending)
        ->and($expense->amount)->toBe(8500050)
        ->and($expense->created_by)->toBe($this->accountant->id)
        ->and($expense->receipt_path)->toStartWith("companies/{$this->company->id}/expenses/");

    Storage::disk('local')->assertExists($expense->receipt_path);
});

it('validates amounts, dates and receipt files by their real content', function () {
    // UploadedFile::fake() infers the MIME type from the file *name*; a real
    // UploadedFile is content-sniffed with finfo, exactly as in production.
    $path = tempnam(sys_get_temp_dir(), 'receipt');
    file_put_contents($path, '<html><script>alert(1)</script></html>');
    $disguisedHtml = new UploadedFile($path, 'receipt.pdf', null, null, true);

    $this->actingAs($this->accountant)
        ->post(route('finance.expenses.store'), expensePayload($this, [
            'amount' => '0',
            'expense_date' => '2026-12-01',
            'receipt' => $disguisedHtml,
        ]))
        ->assertSessionHasErrors(['amount', 'expense_date', 'receipt']);
});

it('does not let the author approve their own expense', function () {
    [$approver] = memberWithRole(SystemRole::Owner, $this->company);
    $this->actingAs($approver)->post(route('finance.expenses.store'), expensePayload($this))->assertSessionHasNoErrors();
    $expense = tenant()->run($this->company, fn () => Expense::sole());

    $this->actingAs($approver)->post(route('finance.expenses.approve', $expense))->assertSessionHasErrors('rule');

    expect(tenant()->run($this->company, fn () => $expense->fresh()->status))->toBe(ExpenseStatus::Pending);
});

it('is approved or rejected by another member with expenses.approve', function () {
    $approved = recordExpense($this);
    $rejected = recordExpense($this, ['description' => 'Team dinner']);

    $this->actingAs($this->manager)->post(route('finance.expenses.approve', $approved))->assertSessionHasNoErrors();
    $this->actingAs($this->manager)->post(route('finance.expenses.reject', $rejected), ['reason' => 'Not a business expense'])->assertSessionHasNoErrors();

    tenant()->run($this->company, function () use ($approved, $rejected) {
        expect($approved->fresh()->status)->toBe(ExpenseStatus::Approved)
            ->and($approved->fresh()->reviewed_by)->toBe($this->manager->id)
            ->and($rejected->fresh()->status)->toBe(ExpenseStatus::Rejected)
            ->and($rejected->fresh()->rejection_reason)->toBe('Not a business expense');
    });

    // Reviewing twice is refused.
    $this->actingAs($this->manager)->post(route('finance.expenses.approve', $approved))->assertSessionHasErrors('rule');
});

it('only edits and deletes pending expenses', function () {
    $expense = recordExpense($this);

    $this->actingAs($this->accountant)
        ->put(route('finance.expenses.update', $expense), expensePayload($this, ['amount' => '100']))
        ->assertSessionHasNoErrors();
    expect(tenant()->run($this->company, fn () => $expense->fresh()->amount))->toBe(10000);

    $this->actingAs($this->manager)->post(route('finance.expenses.approve', $expense));

    $this->actingAs($this->accountant)->put(route('finance.expenses.update', $expense), expensePayload($this))->assertSessionHasErrors('rule');
    $this->actingAs($this->accountant)->delete(route('finance.expenses.destroy', $expense))->assertSessionHasErrors('rule');
});

it('replaces the receipt file and removes the previous one', function () {
    $expense = recordExpense($this, ['receipt' => UploadedFile::fake()->image('first.jpg')]);
    $firstPath = $expense->receipt_path;

    $this->actingAs($this->accountant)
        ->put(route('finance.expenses.update', $expense), expensePayload($this, ['receipt' => UploadedFile::fake()->image('second.png')]))
        ->assertSessionHasNoErrors();

    Storage::disk('local')->assertMissing($firstPath);
    Storage::disk('local')->assertExists(tenant()->run($this->company, fn () => $expense->fresh()->receipt_path));
});

it('downloads receipts only for authorised members of the company', function () {
    $expense = recordExpense($this, ['receipt' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')]);
    [$outsider] = memberWithRole(SystemRole::Owner);
    [$seller] = memberWithRole(SystemRole::Sales, $this->company);

    $this->actingAs($this->manager)->get(route('finance.expenses.receipt', $expense))->assertOk()->assertDownload('EXP-000001.pdf');
    $this->actingAs($seller)->get(route('finance.expenses.receipt', $expense))->assertForbidden();
    $this->actingAs($outsider)->get(route('finance.expenses.receipt', $expense))->assertNotFound();
});

it('lists expenses with approved and pending totals', function () {
    $approved = recordExpense($this, ['amount' => '100']);
    recordExpense($this, ['amount' => '50']);
    $this->actingAs($this->manager)->post(route('finance.expenses.approve', $approved));

    $this->actingAs($this->accountant)
        ->get(route('finance.expenses.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/Expenses')
            ->has('expenses.data', 2)
            ->where('totals.approved.amount', 10000)
            ->where('totals.pending.amount', 5000));
});

it('manages categories unique per company', function () {
    $this->actingAs($this->accountant)->post(route('finance.expenses.categories.store'), ['name' => 'Travel'])->assertSessionHasNoErrors();
    $this->actingAs($this->accountant)->post(route('finance.expenses.categories.store'), ['name' => 'Travel'])->assertSessionHasErrors('name');

    [$outsiderAdmin] = memberWithRole(SystemRole::Owner);
    $this->actingAs($outsiderAdmin)->post(route('finance.expenses.categories.store'), ['name' => 'Travel'])->assertSessionHasNoErrors();
});

it('isolates expenses and categories between companies', function () {
    $expense = recordExpense($this);
    [$outsider] = memberWithRole(SystemRole::Owner);

    $this->actingAs($outsider)->post(route('finance.expenses.approve', $expense))->assertNotFound();
    $this->actingAs($outsider)->delete(route('finance.expenses.destroy', $expense))->assertNotFound();
    $this->actingAs($outsider)
        ->post(route('finance.expenses.store'), expensePayload($this))
        ->assertSessionHasErrors('category_id');
});

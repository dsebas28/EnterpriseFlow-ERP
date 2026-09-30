<?php

use App\Enums\DocumentType;
use App\Enums\SystemRole;
use App\Services\Documents\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;

function nextNumber(DocumentType $type = DocumentType::PurchaseOrder): string
{
    return DB::transaction(fn () => app(DocumentNumberGenerator::class)->next($type));
}

it('issues sequential numbers per company and type', function () {
    [, $company] = memberWithRole(SystemRole::Owner);
    actAsCompany($company);

    expect(nextNumber())->toBe('PO-000001')
        ->and(nextNumber())->toBe('PO-000002')
        ->and(nextNumber(DocumentType::PurchaseReceipt))->toBe('GR-000001');
});

it('keeps independent sequences per company', function () {
    [, $a] = memberWithRole(SystemRole::Owner);
    [, $b] = memberWithRole(SystemRole::Owner);

    tenant()->run($a, fn () => nextNumber());
    tenant()->run($a, fn () => nextNumber());

    expect(tenant()->run($b, fn () => nextNumber()))->toBe('PO-000001');
});

it('does not leave gaps when the surrounding transaction rolls back', function () {
    [, $company] = memberWithRole(SystemRole::Owner);
    actAsCompany($company);
    nextNumber();

    try {
        DB::transaction(function () {
            app(DocumentNumberGenerator::class)->next(DocumentType::PurchaseOrder);
            throw new RuntimeException('document failed');
        });
    } catch (RuntimeException) {
    }

    expect(nextNumber())->toBe('PO-000002');
});

it('refuses to issue numbers outside a transaction', function () {
    [, $company] = memberWithRole(SystemRole::Owner);
    actAsCompany($company);

    // RefreshDatabase wraps each test in a transaction; leave it temporarily.
    DB::rollBack();

    try {
        app(DocumentNumberGenerator::class)->next(DocumentType::PurchaseOrder);
    } finally {
        DB::beginTransaction();
    }
})->throws(LogicException::class);

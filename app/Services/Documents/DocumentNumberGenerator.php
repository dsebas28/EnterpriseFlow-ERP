<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Issues sequential document numbers per company and type (PO-000001…).
 *
 * Must be called inside the transaction that creates the document: the
 * sequence row stays locked until commit, so concurrent requests are
 * serialised, and a rollback also rolls back the counter — no duplicates
 * and no gaps.
 */
final class DocumentNumberGenerator
{
    private const PADDING = 6;

    public function __construct(private readonly TenantContext $tenant) {}

    public function next(DocumentType $type): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Document numbers must be generated inside a database transaction.');
        }

        $companyId = $this->tenant->idOrFail();

        DB::table('document_sequences')->insertOrIgnore([
            'company_id' => $companyId,
            'type' => $type->value,
            'prefix' => $type->prefix(),
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('document_sequences')
            ->where('company_id', $companyId)
            ->where('type', $type->value)
            ->lockForUpdate()
            ->first(['id', 'prefix', 'next_number']);

        if ($sequence === null) {
            throw new LogicException("Document sequence [{$type->value}] could not be initialised.");
        }

        DB::table('document_sequences')
            ->where('id', $sequence->id)
            ->update(['next_number' => $sequence->next_number + 1, 'updated_at' => now()]);

        return $sequence->prefix.'-'.str_pad((string) $sequence->next_number, self::PADDING, '0', STR_PAD_LEFT);
    }
}

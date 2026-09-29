<?php

namespace App\Support\Tenancy;

use App\Exceptions\TenantMismatch;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks an Eloquent model as tenant-owned.
 *
 * - Scopes all queries to the active company (CompanyScope).
 * - Fills company_id on create from the active company.
 * - Rejects writes that target a company other than the active one.
 * - Makes company_id immutable once persisted.
 *
 * @mixin Model
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);
            $companyId = $model->getAttribute('company_id');

            if ($companyId === null) {
                $model->setAttribute('company_id', $context->idOrFail());

                return;
            }

            if ($context->check() && $companyId !== $context->id()) {
                throw TenantMismatch::forModel($model);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('company_id')) {
                throw TenantMismatch::immutable($model);
            }
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

<?php

namespace App\Actions\Warehouses;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SaveWarehouse
{
    /**
     * @param  array{code: string, name: string, address?: string|null, city?: string|null, is_active?: bool, is_default?: bool}  $data
     */
    public function handle(?Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse ??= new Warehouse;

        $makeDefault = (bool) ($data['is_default'] ?? false);
        $active = (bool) ($data['is_active'] ?? true);

        if ($warehouse->is_default && ! $active) {
            throw new BusinessRuleViolation('The default warehouse cannot be deactivated. Choose another default first.');
        }

        return DB::transaction(function () use ($warehouse, $data, $makeDefault, $active): Warehouse {
            $warehouse->fill([
                'code' => Str::upper(trim($data['code'])),
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'is_active' => $active,
            ]);

            // The first warehouse of a company is always its default.
            $isFirst = ! $warehouse->exists && ! Warehouse::query()->exists();

            if (($makeDefault || $isFirst) && ! $warehouse->is_default) {
                // Clear the current default first: the partial unique index
                // allows only one default per company at any moment.
                Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
                $warehouse->is_default = true;
            }

            $warehouse->save();

            return $warehouse;
        });
    }
}

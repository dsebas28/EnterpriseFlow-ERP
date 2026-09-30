<?php

namespace App\Services\Inventory;

use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Proves the stock projection is derivable from the ledger: recomputes
 * every (product, warehouse) quantity from stock_movements and compares it
 * with stock_levels. Runs for the active company.
 */
final class StockLedger
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @return list<array{product_id: string, warehouse_id: string, expected: int, actual: int}>
     */
    public function discrepancies(): array
    {
        $expected = StockMovement::query()
            ->selectRaw('product_id, warehouse_id, SUM(quantity) as total')
            ->groupBy('product_id', 'warehouse_id')
            ->get()
            ->mapWithKeys(fn (StockMovement $row) => [
                "{$row->product_id}|{$row->warehouse_id}" => (int) $row->getAttribute('total'),
            ]);

        $actual = StockLevel::query()
            ->get(['product_id', 'warehouse_id', 'quantity'])
            ->mapWithKeys(fn (StockLevel $level) => ["{$level->product_id}|{$level->warehouse_id}" => $level->quantity]);

        $issues = [];

        foreach ($expected->keys()->merge($actual->keys())->unique() as $key) {
            $should = (int) $expected->get($key, 0);
            $is = (int) $actual->get($key, 0);

            if ($should !== $is) {
                [$productId, $warehouseId] = explode('|', (string) $key);
                $issues[] = ['product_id' => $productId, 'warehouse_id' => $warehouseId, 'expected' => $should, 'actual' => $is];
            }
        }

        return $issues;
    }

    /**
     * Overwrite drifted projection rows with the values derived from the ledger.
     *
     * @return int number of rows corrected
     */
    public function rebuild(): int
    {
        return DB::transaction(function (): int {
            $issues = $this->discrepancies();

            foreach ($issues as $issue) {
                DB::table('stock_levels')->updateOrInsert(
                    [
                        'company_id' => $this->tenant->idOrFail(),
                        'product_id' => $issue['product_id'],
                        'warehouse_id' => $issue['warehouse_id'],
                    ],
                    ['quantity' => $issue['expected'], 'updated_at' => now()],
                );
            }

            return count($issues);
        });
    }
}

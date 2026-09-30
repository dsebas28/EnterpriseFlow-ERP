<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\StockLevel;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A stockable product with its on-hand quantity (from StockIndexQuery).
 *
 * @mixin Product
 */
class StockItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $onHand = (int) $this->getAttribute('on_hand');
        $cost = $this->costMoney();

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'type' => $this->type->value,
            'parent_id' => $this->parent_id,
            'category' => $this->category?->name,
            'min_stock' => $this->min_stock,
            'on_hand' => $onHand,
            'status' => match (true) {
                $onHand <= 0 => 'out',
                $onHand < $this->min_stock => 'low',
                default => 'ok',
            },
            'cost' => MoneyResource::make($cost),
            'value' => MoneyResource::make(new Money(max(0, $onHand) * $cost->minor, $cost->currency)),
            'levels' => $this->stockLevels
                ->mapWithKeys(fn (StockLevel $level) => [$level->warehouse_id => $level->quantity]),
        ];
    }
}

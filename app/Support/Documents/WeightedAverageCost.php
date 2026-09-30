<?php

namespace App\Support\Documents;

/**
 * Moving weighted average cost:
 *
 *   new cost = (on hand × current cost + received × received cost)
 *              / (on hand + received)
 *
 * When there is no positive stock the history carries no weight and the
 * received cost becomes the new cost. Rounded half-up to minor units.
 */
final class WeightedAverageCost
{
    public static function calculate(int $onHand, int $currentCost, int $receivedQuantity, int $receivedCost): int
    {
        if ($receivedQuantity <= 0) {
            return $currentCost;
        }

        if ($onHand <= 0) {
            return $receivedCost;
        }

        $totalValue = $onHand * $currentCost + $receivedQuantity * $receivedCost;
        $totalUnits = $onHand + $receivedQuantity;

        return intdiv($totalValue * 2 + $totalUnits, $totalUnits * 2);
    }
}

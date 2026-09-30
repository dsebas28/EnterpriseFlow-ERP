<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Transfer = 'transfer';
    case ManualIn = 'manual_in';
    case ManualOut = 'manual_out';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Sale => 'Sale',
            self::Return => 'Return',
            self::Adjustment => 'Adjustment',
            self::Transfer => 'Transfer',
            self::ManualIn => 'Manual entry',
            self::ManualOut => 'Manual exit',
        };
    }

    /**
     * Whether a signed quantity is valid for this type: purchases only add
     * stock, sales only remove it, adjustments/returns/transfers go both ways.
     */
    public function allows(int $quantity): bool
    {
        if ($quantity === 0) {
            return false;
        }

        return match ($this) {
            self::Purchase, self::ManualIn => $quantity > 0,
            self::Sale, self::ManualOut => $quantity < 0,
            self::Return, self::Adjustment, self::Transfer => true,
        };
    }
}

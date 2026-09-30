<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * The state machine of a sale. Payment states are reached through the
     * payments module; a sale with payments cannot be cancelled (it needs
     * a refund instead).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending, self::Confirmed, self::Cancelled],
            self::Pending => [self::Confirmed, self::Draft, self::Cancelled],
            self::Confirmed => [self::PartiallyPaid, self::Paid, self::Cancelled],
            self::PartiallyPaid => [self::PartiallyPaid, self::Paid],
            self::Paid, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Stock has left the warehouse for sales in these states.
     */
    public function hasDeductedStock(): bool
    {
        return in_array($this, [self::Confirmed, self::PartiallyPaid, self::Paid], true);
    }

    /**
     * @return list<string>
     */
    public static function receivableValues(): array
    {
        return [self::Confirmed->value, self::PartiallyPaid->value];
    }
}

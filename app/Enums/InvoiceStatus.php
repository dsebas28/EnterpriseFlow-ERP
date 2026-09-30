<?php

namespace App\Enums;

/**
 * Lifecycle shared by customer invoices and supplier bills.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Issued, self::Cancelled],
            self::Issued => [self::PartiallyPaid, self::Paid, self::Overdue, self::Cancelled],
            self::PartiallyPaid => [self::PartiallyPaid, self::Paid, self::Overdue],
            self::Overdue => [self::PartiallyPaid, self::Paid, self::Cancelled],
            self::Paid, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Open documents: money is (still) owed.
     *
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Issued->value, self::PartiallyPaid->value, self::Overdue->value];
    }

    public function isOpen(): bool
    {
        return in_array($this->value, self::openValues(), true);
    }
}

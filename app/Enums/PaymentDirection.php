<?php

namespace App\Enums;

enum PaymentDirection: string
{
    /** Collected from a customer against an invoice. */
    case Incoming = 'incoming';

    /** Paid to a supplier against a bill. */
    case Outgoing = 'outgoing';

    public function label(): string
    {
        return match ($this) {
            self::Incoming => 'Received',
            self::Outgoing => 'Paid',
        };
    }
}

<?php

namespace App\Enums;

enum WebhookEventStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    // Valid, but nothing to do (unsupported type or informational event).
    case Ignored = 'ignored';
    case Failed = 'failed';

    /**
     * A finished event is never handled again.
     */
    public function isFinal(): bool
    {
        return $this === self::Processed || $this === self::Ignored;
    }
}

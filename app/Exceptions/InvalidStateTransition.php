<?php

namespace App\Exceptions;

use BackedEnum;

class InvalidStateTransition extends BusinessRuleViolation
{
    public static function between(string $document, BackedEnum $from, BackedEnum $to): self
    {
        return new self(sprintf('A %s cannot go from "%s" to "%s".', $document, $from->value, $to->value));
    }
}

<?php

namespace App\Reports\Export;

use App\Reports\Column;
use App\Support\Money\Money;

/**
 * Converts a report value into what is written to an exported file.
 */
final class CellValue
{
    /**
     * Characters that make spreadsheet applications interpret a cell as a
     * formula (CSV/formula injection, OWASP). Such text is prefixed with a
     * quote so it is displayed, never executed.
     */
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public static function format(Column $column, string|int|float|null $value, string $currency): string|int|float|null
    {
        if ($value === null) {
            return null;
        }

        return match ($column->type) {
            Column::MONEY => (float) (new Money((int) $value, $currency))->toDecimal(),
            Column::NUMBER, Column::PERCENT => $value,
            default => self::neutraliseFormula((string) $value),
        };
    }

    public static function neutraliseFormula(string $text): string
    {
        if ($text !== '' && in_array($text[0], self::FORMULA_TRIGGERS, true)) {
            return "'".$text;
        }

        return $text;
    }
}

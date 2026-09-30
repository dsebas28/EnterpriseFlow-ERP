<?php

namespace App\Support\Money;

use InvalidArgumentException;

/**
 * An amount in the minor unit of a currency (cents for USD, none for CLP).
 *
 * All arithmetic is integer arithmetic; floats never touch money. Rounding
 * (tax, discounts) is half-up away from zero, applied once per operation.
 */
final readonly class Money
{
    public function __construct(
        public int $minor,
        public string $currency,
    ) {}

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    /**
     * Parse a decimal string such as "1234.5" into minor units.
     *
     * @throws InvalidArgumentException for malformed input or excess precision
     */
    public static function fromDecimal(string $amount, string $currency): self
    {
        $decimals = Currency::minorUnits($currency);
        $amount = trim($amount);

        if (! preg_match('/^(-)?(\d+)(?:\.(\d+))?$/', $amount, $parts)) {
            throw new InvalidArgumentException("Invalid money amount [{$amount}].");
        }

        $fraction = $parts[3] ?? '';

        if (strlen($fraction) > $decimals) {
            throw new InvalidArgumentException("[{$amount}] has more than {$decimals} decimals for {$currency}.");
        }

        $minor = (int) ($parts[2].str_pad($fraction, $decimals, '0'));

        return new self($parts[1] === '-' ? -$minor : $minor, $currency);
    }

    /**
     * Minor units rendered as a plain decimal string ("1234.50"), suitable
     * for form inputs. Display formatting belongs to the frontend (Intl).
     */
    public function toDecimal(): string
    {
        $decimals = Currency::minorUnits($this->currency);
        $sign = $this->minor < 0 ? '-' : '';
        $digits = str_pad((string) abs($this->minor), $decimals + 1, '0', STR_PAD_LEFT);

        if ($decimals === 0) {
            return $sign.$digits;
        }

        return $sign.substr($digits, 0, -$decimals).'.'.substr($digits, -$decimals);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function multiply(int $quantity): self
    {
        return new self($this->minor * $quantity, $this->currency);
    }

    /**
     * Percentage of the amount, with the rate in basis points (1900 = 19%).
     */
    public function percentage(int $basisPoints): self
    {
        return new self(self::divideRounded($this->minor * $basisPoints, 10_000), $this->currency);
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    /**
     * Integer division rounding half away from zero.
     */
    private static function divideRounded(int $dividend, int $divisor): int
    {
        $quotient = intdiv($dividend, $divisor);
        $remainder = $dividend % $divisor;

        if (abs($remainder) * 2 >= $divisor) {
            $quotient += $dividend < 0 ? -1 : 1;
        }

        return $quotient;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Currency mismatch: {$this->currency} vs {$other->currency}.");
        }
    }
}

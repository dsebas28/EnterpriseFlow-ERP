<?php

namespace App\DTOs;

use App\Enums\PaymentMethod;
use App\Support\Money\Money;

final readonly class PaymentData
{
    public function __construct(
        public int $amount,
        public PaymentMethod $method,
        public string $paidAt,
        public ?string $reference = null,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Output of a FormRequest's validated()
     */
    public static function fromArray(array $data, string $currency): self
    {
        return new self(
            amount: Money::fromDecimal((string) $data['amount'], $currency)->minor,
            method: PaymentMethod::from($data['method']),
            paidAt: $data['paid_at'],
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }
}

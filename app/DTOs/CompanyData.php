<?php

namespace App\DTOs;

/**
 * Validated input for creating or updating a company.
 */
final readonly class CompanyData
{
    public function __construct(
        public string $name,
        public string $country,
        public string $currency,
        public string $timezone,
        public ?string $legalName = null,
        public ?string $taxId = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $city = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Output of a FormRequest's validated()
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            country: strtoupper($data['country']),
            currency: strtoupper($data['currency']),
            timezone: $data['timezone'],
            legalName: $data['legal_name'] ?? null,
            taxId: $data['tax_id'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            address: $data['address'] ?? null,
            city: $data['city'] ?? null,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'legal_name' => $this->legalName,
            'tax_id' => $this->taxId,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
        ];
    }
}

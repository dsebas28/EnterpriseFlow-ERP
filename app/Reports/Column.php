<?php

namespace App\Reports;

/**
 * A report column. The type drives formatting in the UI and in exports
 * (money columns hold integer minor units).
 */
final readonly class Column
{
    public const TEXT = 'text';

    public const NUMBER = 'number';

    public const MONEY = 'money';

    public const PERCENT = 'percent';

    public function __construct(
        public string $key,
        public string $label,
        public string $type = self::TEXT,
    ) {}

    public static function text(string $key, string $label): self
    {
        return new self($key, $label, self::TEXT);
    }

    public static function number(string $key, string $label): self
    {
        return new self($key, $label, self::NUMBER);
    }

    public static function money(string $key, string $label): self
    {
        return new self($key, $label, self::MONEY);
    }

    public static function percent(string $key, string $label): self
    {
        return new self($key, $label, self::PERCENT);
    }

    /**
     * @return array{key: string, label: string, type: string}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'type' => $this->type];
    }
}

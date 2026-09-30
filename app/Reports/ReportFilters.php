<?php

namespace App\Reports;

/**
 * Validated, normalised filters shared by all reports. Each report reads
 * only the filters it declares as supported.
 */
final readonly class ReportFilters
{
    public const GROUPINGS = ['day', 'week', 'month'];

    public function __construct(
        public string $from,
        public string $to,
        public string $groupBy = 'day',
        public ?string $warehouseId = null,
        public ?int $categoryId = null,
        public ?string $customerId = null,
        public ?string $supplierId = null,
        public ?int $userId = null,
        public ?int $expenseCategoryId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public static function fromArray(array $data, string $today): self
    {
        $from = $data['from'] ?? substr($today, 0, 8).'01';

        return new self(
            from: $from,
            to: $data['to'] ?? $today,
            groupBy: in_array($data['group_by'] ?? null, self::GROUPINGS, true) ? $data['group_by'] : 'day',
            warehouseId: $data['warehouse_id'] ?? null,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            customerId: $data['customer_id'] ?? null,
            supplierId: $data['supplier_id'] ?? null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            expenseCategoryId: isset($data['expense_category_id']) ? (int) $data['expense_category_id'] : null,
        );
    }

    /**
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'group_by' => $this->groupBy,
            'warehouse_id' => $this->warehouseId,
            'category_id' => $this->categoryId,
            'customer_id' => $this->customerId,
            'supplier_id' => $this->supplierId,
            'user_id' => $this->userId,
            'expense_category_id' => $this->expenseCategoryId,
        ];
    }
}

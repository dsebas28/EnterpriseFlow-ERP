<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Company;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;

/**
 * One notification per run of the overdue job, not one per invoice.
 */
class InvoicesOverdueDigest extends CompanyNotification
{
    /**
     * @param  non-empty-list<string>  $numbers
     */
    public function __construct(Company $company, array $numbers, Money $outstanding)
    {
        $count = count($numbers);
        $listed = implode(', ', array_slice($numbers, 0, 5)).($count > 5 ? ' and '.($count - 5).' more' : '');

        parent::__construct(
            $company,
            title: $count === 1 ? "Invoice {$numbers[0]} is overdue" : "{$count} invoices became overdue",
            body: sprintf('%s. Outstanding: %s.', $listed, MoneyFormatter::format($outstanding)),
            url: route('finance.invoices.index', ['status' => 'overdue']),
            level: 'warning',
        );
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::InvoicesOverdue;
    }
}

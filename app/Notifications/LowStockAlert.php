<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Company;
use App\Models\Product;

class LowStockAlert extends CompanyNotification
{
    public function __construct(Company $company, Product $product, int $stock)
    {
        parent::__construct(
            $company,
            title: "Low stock: {$product->name}",
            body: "{$product->sku} is down to {$stock} units across all warehouses (minimum {$product->min_stock}).",
            url: route('inventory.stock.index', ['search' => $product->sku]),
            level: 'warning',
        );
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::LowStock;
    }
}

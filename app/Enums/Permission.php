<?php

namespace App\Enums;

/**
 * Every permission the application checks. This enum is the source of
 * truth: roles store references to these values, and adding a case here
 * is all it takes to introduce a new permission.
 */
enum Permission: string
{
    // Administration
    case CompanySettings = 'company.settings';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case AuditView = 'audit.view';

    // Catalog
    case ProductsView = 'products.view';
    case ProductsCreate = 'products.create';
    case ProductsUpdate = 'products.update';
    case ProductsDelete = 'products.delete';
    case CategoriesManage = 'categories.manage';

    // Parties
    case CustomersView = 'customers.view';
    case CustomersCreate = 'customers.create';
    case CustomersUpdate = 'customers.update';
    case CustomersDelete = 'customers.delete';
    case SuppliersView = 'suppliers.view';
    case SuppliersCreate = 'suppliers.create';
    case SuppliersUpdate = 'suppliers.update';
    case SuppliersDelete = 'suppliers.delete';

    // Inventory
    case WarehousesView = 'warehouses.view';
    case WarehousesManage = 'warehouses.manage';
    case InventoryView = 'inventory.view';
    case InventoryAdjust = 'inventory.adjust';
    case InventoryTransfer = 'inventory.transfer';

    // Purchasing
    case PurchasesView = 'purchases.view';
    case PurchasesCreate = 'purchases.create';
    case PurchasesApprove = 'purchases.approve';
    case PurchasesReceive = 'purchases.receive';
    case PurchasesCancel = 'purchases.cancel';

    // Sales
    case SalesView = 'sales.view';
    case SalesCreate = 'sales.create';
    case SalesConfirm = 'sales.confirm';
    case SalesCancel = 'sales.cancel';

    // Finance
    case InvoicesView = 'invoices.view';
    case InvoicesCreate = 'invoices.create';
    case InvoicesCancel = 'invoices.cancel';
    case PaymentsView = 'payments.view';
    case PaymentsCreate = 'payments.create';
    case PaymentsVoid = 'payments.void';
    case ExpensesView = 'expenses.view';
    case ExpensesCreate = 'expenses.create';
    case ExpensesApprove = 'expenses.approve';

    // Reporting
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';

    /**
     * Module the permission belongs to (the prefix before the dot).
     */
    public function group(): string
    {
        return strstr($this->value, '.', true) ?: $this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, list<string>> permissions grouped by module
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = $permission->value;
        }

        return $groups;
    }
}

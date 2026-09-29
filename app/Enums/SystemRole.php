<?php

namespace App\Enums;

use App\Enums\Permission as P;

/**
 * Role templates copied into every new company. Companies may later edit
 * the permissions of these roles (except Owner) or create their own.
 */
enum SystemRole: string
{
    case Owner = 'owner';
    case Administrator = 'administrator';
    case Manager = 'manager';
    case Accountant = 'accountant';
    case Sales = 'sales';
    case Warehouse = 'warehouse';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Company Owner',
            self::Administrator => 'Administrator',
            self::Manager => 'Manager',
            self::Accountant => 'Accountant',
            self::Sales => 'Sales',
            self::Warehouse => 'Warehouse',
            self::Employee => 'Employee',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full access, including ownership and billing. Always has every permission.',
            self::Administrator => 'Full operational access and team management.',
            self::Manager => 'Runs day-to-day operations: catalog, purchasing, sales, inventory and reports.',
            self::Accountant => 'Invoices, payments, expenses, reports and audit trail.',
            self::Sales => 'Customers, quotes and sales, invoicing and payment collection.',
            self::Warehouse => 'Stock movements, transfers and receiving purchase orders.',
            self::Employee => 'Read-only access to catalog, customers and stock.',
        };
    }

    /**
     * Permissions granted when the role is provisioned. Owner is resolved
     * dynamically to *all* permissions (see PermissionResolver), so newly
     * added permissions never lock the owner out.
     *
     * @return list<P>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Owner, self::Administrator => P::cases(),

            self::Manager => [
                P::ProductsView, P::ProductsCreate, P::ProductsUpdate, P::ProductsDelete, P::CategoriesManage,
                P::CustomersView, P::CustomersCreate, P::CustomersUpdate,
                P::SuppliersView, P::SuppliersCreate, P::SuppliersUpdate,
                P::WarehousesView, P::WarehousesManage,
                P::InventoryView, P::InventoryAdjust, P::InventoryTransfer,
                P::PurchasesView, P::PurchasesCreate, P::PurchasesApprove, P::PurchasesReceive, P::PurchasesCancel,
                P::SalesView, P::SalesCreate, P::SalesConfirm, P::SalesCancel,
                P::InvoicesView, P::PaymentsView,
                P::ExpensesView, P::ExpensesCreate, P::ExpensesApprove,
                P::ReportsView, P::ReportsExport,
            ],

            self::Accountant => [
                P::ProductsView, P::CustomersView, P::SuppliersView,
                P::PurchasesView, P::SalesView,
                P::InvoicesView, P::InvoicesCreate, P::InvoicesCancel,
                P::PaymentsView, P::PaymentsCreate, P::PaymentsVoid,
                P::ExpensesView, P::ExpensesCreate, P::ExpensesApprove,
                P::ReportsView, P::ReportsExport,
                P::AuditView,
            ],

            self::Sales => [
                P::ProductsView, P::InventoryView,
                P::CustomersView, P::CustomersCreate, P::CustomersUpdate,
                P::SalesView, P::SalesCreate, P::SalesConfirm,
                P::InvoicesView, P::InvoicesCreate,
                P::PaymentsView, P::PaymentsCreate,
            ],

            self::Warehouse => [
                P::ProductsView, P::WarehousesView,
                P::InventoryView, P::InventoryAdjust, P::InventoryTransfer,
                P::PurchasesView, P::PurchasesReceive,
                P::SalesView,
            ],

            self::Employee => [
                P::ProductsView, P::CustomersView, P::InventoryView, P::SalesView,
            ],
        };
    }
}

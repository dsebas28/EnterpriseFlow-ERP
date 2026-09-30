<?php

namespace App\Enums;

/**
 * What a notification is about. Each category decides who receives it (a
 * permission inside the company, or a specific user) and whether email is
 * on by default; users can override the channels per category.
 */
enum NotificationCategory: string
{
    case LowStock = 'low_stock';
    case SaleConfirmed = 'sale_confirmed';
    case PurchaseApprovalRequested = 'purchase_approval_requested';
    case PurchaseOrderApproved = 'purchase_order_approved';
    case InvoicesOverdue = 'invoices_overdue';
    case PaymentReceived = 'payment_received';
    case MemberJoined = 'member_joined';
    case ReportExport = 'report_export';
    case SystemAlert = 'system_alert';
    case Invitation = 'invitation';

    public function label(): string
    {
        return match ($this) {
            self::LowStock => 'Low stock',
            self::SaleConfirmed => 'New sales',
            self::PurchaseApprovalRequested => 'Purchase orders awaiting approval',
            self::PurchaseOrderApproved => 'Approved purchase orders',
            self::InvoicesOverdue => 'Overdue invoices',
            self::PaymentReceived => 'Payments received',
            self::MemberJoined => 'New team members',
            self::ReportExport => 'Report exports',
            self::SystemAlert => 'System alerts',
            self::Invitation => 'Invitations',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::LowStock => 'A product drops below its minimum stock.',
            self::SaleConfirmed => 'A sale is confirmed.',
            self::PurchaseApprovalRequested => 'A purchase order is submitted for approval.',
            self::PurchaseOrderApproved => 'A purchase order is approved and goods are expected.',
            self::InvoicesOverdue => 'Customer invoices pass their due date (daily digest).',
            self::PaymentReceived => 'A customer payment is recorded.',
            self::MemberJoined => 'Someone accepts an invitation to the company.',
            self::ReportExport => 'An export you requested is ready or failed.',
            self::SystemAlert => 'Integrations fail and need attention (e.g. a payment gateway event).',
            self::Invitation => 'You are invited to join a company.',
        };
    }

    /**
     * Company members holding this permission receive the notification;
     * null means it targets specific users (the requester, the invitee).
     */
    public function permission(): ?Permission
    {
        return match ($this) {
            self::LowStock => Permission::PurchasesCreate,
            self::SaleConfirmed => Permission::ReportsView,
            self::PurchaseApprovalRequested => Permission::PurchasesApprove,
            self::PurchaseOrderApproved => Permission::PurchasesReceive,
            self::InvoicesOverdue => Permission::InvoicesView,
            self::PaymentReceived => Permission::PaymentsView,
            self::MemberJoined => Permission::UsersManage,
            self::SystemAlert => Permission::CompanySettings,
            self::ReportExport, self::Invitation => null,
        };
    }

    /**
     * Email is opt-out for things that need action, opt-in for the rest.
     */
    public function mailByDefault(): bool
    {
        return match ($this) {
            self::LowStock, self::PurchaseApprovalRequested, self::InvoicesOverdue, self::SystemAlert, self::Invitation => true,
            default => false,
        };
    }

    /**
     * Invitations are transactional: always delivered on every channel.
     */
    public function isConfigurable(): bool
    {
        return $this !== self::Invitation;
    }

    /**
     * @return list<self>
     */
    public static function configurable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $category) => $category->isConfigurable()));
    }
}

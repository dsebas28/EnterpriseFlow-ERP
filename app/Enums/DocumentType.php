<?php

namespace App\Enums;

enum DocumentType: string
{
    case PurchaseOrder = 'purchase_order';
    case PurchaseReceipt = 'purchase_receipt';
    case Sale = 'sale';
    case Invoice = 'invoice';
    case SupplierBill = 'supplier_bill';
    case Payment = 'payment';
    case Expense = 'expense';

    public function prefix(): string
    {
        return match ($this) {
            self::Expense => 'EXP',
            self::Payment => 'PAY',
            self::PurchaseOrder => 'PO',
            self::PurchaseReceipt => 'GR',
            self::Sale => 'SO',
            self::Invoice => 'INV',
            self::SupplierBill => 'BILL',
        };
    }
}

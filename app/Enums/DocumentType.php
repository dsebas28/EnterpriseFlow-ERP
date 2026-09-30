<?php

namespace App\Enums;

enum DocumentType: string
{
    case PurchaseOrder = 'purchase_order';
    case PurchaseReceipt = 'purchase_receipt';
    case Sale = 'sale';

    public function prefix(): string
    {
        return match ($this) {
            self::PurchaseOrder => 'PO',
            self::PurchaseReceipt => 'GR',
            self::Sale => 'SO',
        };
    }
}

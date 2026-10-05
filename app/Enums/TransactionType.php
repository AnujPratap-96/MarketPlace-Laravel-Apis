<?php

namespace App\Enums;

enum TransactionType: string
{
    case PAYMENT_RECEIVED = 'payment_received';
    case ESCROW_RELEASE = 'escrow_release';
    case COMMISSION_DEDUCTED = 'commission_deducted';
    case VENDOR_PAYOUT = 'vendor_payout';
    case REFUND = 'refund';
}

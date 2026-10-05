<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case VENDOR = 'vendor';
    case CUSTOMER = 'customer';

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Administrator',
            self::VENDOR => 'Vendor Merchant',
            self::CUSTOMER => 'Retail Customer',
        };
    }
}

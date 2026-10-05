<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function isFinal(): bool
    {
        return match($this) {
            self::DELIVERED, self::CANCELLED, self::REFUNDED => true,
            default => false,
        };
    }
}

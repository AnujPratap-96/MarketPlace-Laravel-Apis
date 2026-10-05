<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case HELD_IN_ESCROW = 'held_in_escrow';
    case SETTLED = 'settled';
    case REFUNDED = 'refunded';
}

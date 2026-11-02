<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Expired = 'expired';
}

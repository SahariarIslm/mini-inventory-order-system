<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}

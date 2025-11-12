<?php

namespace App\Entity;


enum OrderStatus: string
{
    case PENDING = 'pending';           // Order created, awaiting processing
    case PROCESSING = 'processing';     // Order in processing
    case SHIPPED = 'shipped';           // Order is sent
    case DELIVERED = 'delivered';       // Order is delivered
    case CANCELLED = 'cancelled';       // Order is canceled


    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }


    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}



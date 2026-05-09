<?php

namespace App\Enum;

enum OrderStatus: string
{
    case Pending   = 'pending';
    case Paid      = 'paid';
    case Shipped   = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'En attente',
            self::Paid      => 'Payée',
            self::Shipped   => 'Expédiée',
            self::Delivered => 'Livrée',
            self::Cancelled => 'Annulée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending   => 'bg-secondary',
            self::Paid      => 'bg-info',
            self::Shipped   => 'bg-primary',
            self::Delivered => 'bg-success',
            self::Cancelled => 'bg-danger',
        };
    }

    /** @return array<string,string> */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }
        return $choices;
    }
}

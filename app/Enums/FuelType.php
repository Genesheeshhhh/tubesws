<?php

namespace App\Enums;

enum FuelType: string
{
    case Bensin = 'Bensin';
    case Diesel = 'Diesel';
    case Listrik = 'Listrik';
    case Hybrid = 'Hybrid';

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Bensin => 'Bensin',
            self::Diesel => 'Diesel',
            self::Listrik => 'Listrik',
            self::Hybrid => 'Hybrid',
        };
    }
}

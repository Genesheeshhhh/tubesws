<?php

namespace App\Enums;

enum CarStatus: string
{
    case Available = 'available';
    case Sold = 'sold';
    case Draft = 'draft';

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
            self::Available => 'Tersedia',
            self::Sold => 'Terjual',
            self::Draft => 'Draf',
        };
    }

    public function schemaUrl(): string
    {
        return match ($this) {
            self::Available => 'https://schema.org/InStock',
            self::Sold => 'https://schema.org/SoldOut',
            self::Draft => 'https://schema.org/PreOrder',
        };
    }
}

<?php

namespace App\Enums;

enum Transmission: string
{
    case Manual = 'Manual';
    case Automatic = 'Automatic';

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
            self::Manual => 'Manual',
            self::Automatic => 'Otomatis',
        };
    }
}

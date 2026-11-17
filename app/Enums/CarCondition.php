<?php

namespace App\Enums;

enum CarCondition: string
{
    case Used = 'Used';
    case New = 'New';

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
            self::Used => 'Bekas',
            self::New => 'Baru',
        };
    }

    /**
     * The matching schema.org condition enumeration member.
     */
    public function schemaUrl(): string
    {
        return match ($this) {
            self::Used => 'https://schema.org/UsedCondition',
            self::New => 'https://schema.org/NewCondition',
        };
    }
}

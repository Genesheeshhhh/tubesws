<?php

namespace App\Enums;

enum Drivetrain: string
{
    case AllWheelDrive = 'AWD';
    case FrontWheelDrive = 'FWD';
    case RearWheelDrive = 'RWD';
    case FourWheelDrive = '4WD';

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return $this->value;
    }

    public function schemaUrl(): string
    {
        return match ($this) {
            self::AllWheelDrive => 'https://schema.org/AllWheelDriveConfiguration',
            self::FrontWheelDrive => 'https://schema.org/FrontWheelDriveConfiguration',
            self::RearWheelDrive => 'https://schema.org/RearWheelDriveConfiguration',
            self::FourWheelDrive => 'https://schema.org/FourWheelDriveConfiguration',
        };
    }
}

<?php

namespace App\Enums;

enum CarBrand: string
{
    case Toyota = 'Toyota';
    case Honda = 'Honda';
    case Mitsubishi = 'Mitsubishi';
    case Daihatsu = 'Daihatsu';
    case Suzuki = 'Suzuki';
    case Nissan = 'Nissan';
    case Mazda = 'Mazda';
    case Wuling = 'Wuling';
    case MercedesBenz = 'Mercedes-Benz';
    case Bmw = 'BMW';
    case Hyundai = 'Hyundai';
    case Lexus = 'Lexus';
    case Ford = 'Ford';
    case Chevrolet = 'Chevrolet';
    case Isuzu = 'Isuzu';
    case Chery = 'Chery';
    case Mini = 'Mini';
    case Porsche = 'Porsche';
    case Kia = 'Kia';
    case Mg = 'MG';
    case LandRover = 'Land Rover';
    case Jeep = 'Jeep';
    case Volkswagen = 'Volkswagen';
    case Byd = 'BYD';
    case Jaecoo = 'Jaecoo';
    case Datsun = 'Datsun';
    case Renault = 'Renault';
    case Dfsk = 'DFSK';
    case Volvo = 'Volvo';
    case Jetour = 'Jetour';
    case Audi = 'Audi';

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The two-word brands must be attempted before their single-word
     * counterparts when splitting a catalogue string such as "Land Rover
     * Range Rover Velar".
     *
     * @return array<string, string>
     */
    public static function lookup(): array
    {
        $lookup = [];

        foreach (self::cases() as $case) {
            $lookup[mb_strtolower($case->value)] = $case->value;
        }

        uksort($lookup, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $lookup;
    }

    /**
     * Split a free-form catalogue name such as "BMW 320i" into the longest
     * matching brand and whatever remains as the model.
     *
     * @return array{0: string, 1: string}
     */
    public static function split(string $catalogueName): array
    {
        $name = preg_replace('/\s+/', ' ', trim($catalogueName)) ?? '';
        $needle = mb_strtolower($name);

        foreach (self::lookup() as $prefix => $brand) {
            if ($needle === $prefix) {
                return [$brand, ''];
            }

            if (str_starts_with($needle, $prefix.' ')) {
                return [$brand, trim(mb_substr($name, mb_strlen($prefix)))];
            }
        }

        $parts = explode(' ', $name, 2);

        return [$parts[0], trim($parts[1] ?? '')];
    }
}

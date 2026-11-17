<?php

namespace App\Services\Import;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Turns the free-form Indonesian text used in the OLX export into the typed
 * values the catalogue stores.
 */
class ListingNormalizer
{
    /**
     * Parse a rupiah figure such as "Rp 278.000.000" or "Rp 2.8 jt".
     */
    public function amount(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $text = Str::lower($value);
        $scale = 1;

        if (Str::contains($text, ['jt', 'juta'])) {
            $scale = 1_000_000;
        }

        $digits = preg_replace('/[^0-9,.]/', '', $text) ?? '';

        if ($digits === '') {
            return null;
        }

        // With a scale marker the decimal separator is meaningful, otherwise
        // dots and commas are thousands separators.
        $normalized = $scale > 1
            ? str_replace(',', '.', $digits)
            : str_replace([',', '.'], '', $digits);

        if (! str_contains($normalized, '.')) {
            $normalized .= '.0';
        }

        $number = (float) $normalized;

        if ($number <= 0) {
            return null;
        }

        return (int) round($number * $scale);
    }

    /**
     * Parse the odometer range "175.000-180.000 km".
     *
     * @return array{0: int|null, 1: int|null}
     */
    public function mileageRange(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [null, null];
        }

        $bounds = [];

        foreach (preg_split('/\s*(?:-|–|—|to|s\/d)\s*/i', Str::lower($value)) ?: [] as $part) {
            if (preg_match('/([0-9][0-9.,]*)/', $part, $matches) === 1) {
                $number = (int) str_replace(['.', ','], '', $matches[1]);
                $bounds[] = $number > 0 ? $number : null;
            }
        }

        $bounds = array_values(array_filter($bounds, fn (?int $bound): bool => $bound !== null));

        if ($bounds === []) {
            return [null, null];
        }

        if (count($bounds) === 1) {
            return [$bounds[0], $bounds[0]];
        }

        return [min($bounds), max($bounds)];
    }

    public function year(?string $value): ?int
    {
        if ($value === null || preg_match('/(19|20)\d{2}/', $value, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches[0];

        return $year >= 1950 && $year <= (int) date('Y') + 1 ? $year : null;
    }

    /**
     * Resolve a posting timestamp written either as a relative phrase
     * ("6 hari yang lalu") or as a day-and-month pair ("23 Sep").
     */
    public function postedAt(?string $value, CarbonImmutable $now): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $text = Str::lower(trim($value));

        if (Str::contains($text, ['hari ini', 'today'])) {
            return $now;
        }

        if (Str::contains($text, ['kemarin', 'yesterday'])) {
            return $now->subDay();
        }

        if (preg_match('/(\d+)\s*(jam|hour)/', $text, $matches) === 1) {
            return $now->subHours((int) $matches[1]);
        }

        if (preg_match('/(\d+)\s*(menit|minute)/', $text, $matches) === 1) {
            return $now->subMinutes((int) $matches[1]);
        }

        if (preg_match('/(\d+)\s*hari/', $text, $matches) === 1) {
            return $now->subDays((int) $matches[1]);
        }

        if (preg_match('/(\d+)\s*(minggu|week)/', $text, $matches) === 1) {
            return $now->subWeeks((int) $matches[1]);
        }

        if (preg_match('/(\d+)\s*(bulan|month)/', $text, $matches) === 1) {
            return $now->subMonths((int) $matches[1]);
        }

        $absolute = $this->absoluteDate($text, $now);

        return $absolute?->isFuture() ? $absolute->subYear() : $absolute;
    }

    /**
     * Split a location such as "Medan Petisah" into its city and district. A
     * single-word value is a city in its own right, leaving no district.
     *
     * @return array{0: string, 1: string}
     */
    public function location(?string $value): array
    {
        $parts = preg_split('/\s+/', trim((string) $value)) ?: [];
        $parts = array_values(array_filter($parts, fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return ['', ''];
        }

        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $city = array_shift($parts);

        return [$city, trim(implode(' ', $parts))];
    }

    /**
     * Read the monthly payment from "Cicilan Rp 2.8 jt/bulan" or the
     * "Rp 2.808.000 x 60 Bulan" term column.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public function installment(?string $value): array
    {
        return [$this->amount($value), null];
    }

    public function installmentTerm(?string $value): ?int
    {
        if ($value === null || preg_match('/(\d+)\s*(bulan|month)/i', $value, $matches) !== 1) {
            return null;
        }

        $term = (int) $matches[1];

        return $term > 0 && $term <= 120 ? $term : null;
    }

    public function discount(?string $value): ?int
    {
        if ($value === null || ! Str::contains(Str::lower($value), 'diskon')) {
            return null;
        }

        return $this->amount($value);
    }

    public function mentions(?string $value, string ...$needles): bool
    {
        if ($value === null) {
            return false;
        }

        $text = Str::lower($value);

        foreach ($needles as $needle) {
            if (Str::contains($text, Str::lower($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a day-and-month pair such as "23 Sep". The export omits the year,
     * so the current year is assumed and rolled back if it would land ahead of
     * the reference date.
     */
    private function absoluteDate(string $text, CarbonImmutable $now): ?CarbonImmutable
    {
        if (preg_match('/^(\d{1,2})\s+([A-Za-z]{3,9})$/', trim($text), $matches) !== 1) {
            return null;
        }

        $day = (int) $matches[1];
        $month = $this->monthNumber($matches[2]);

        if ($month === null || ! checkdate($month, $day, (int) $now->year)) {
            return null;
        }

        return CarbonImmutable::create((int) $now->year, $month, $day, 0, 0, 0, $now->timezone);
    }

    private function monthNumber(string $name): ?int
    {
        $months = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'mei' => 5, 'jun' => 6,
            'jul' => 7, 'agu' => 8, 'aug' => 8, 'sep' => 9, 'okt' => 10, 'oct' => 10,
            'nov' => 11, 'des' => 12, 'dec' => 12,
        ];

        $key = mb_strtolower(substr($name, 0, 3));

        return $months[$key] ?? null;
    }
}

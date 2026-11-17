<?php

namespace App\Services\Catalog;

use Illuminate\Support\Str;

/**
 * Generates branded placeholder artwork for listings that have no photograph
 * yet. The hue stays inside the site's blue range so a placeholder reads as
 * deliberate artwork rather than as a broken image.
 */
class PlaceholderImage
{
    public function svg(string $brand, string $model, string $seed): string
    {
        $hue = 205 + (hexdec(substr(md5($seed), 0, 2)) % 30);
        $label = trim($brand.' '.$model);
        $initial = Str::upper(Str::substr($brand, 0, 1));

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" role="img" aria-label="{$this->escape($label)}">
              <defs>
                <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="hsl({$hue} 72% 18%)"/>
                  <stop offset="100%" stop-color="hsl({$hue} 68% 34%)"/>
                </linearGradient>
              </defs>
              <rect width="800" height="600" fill="url(#bg)"/>
              <g fill="none" stroke="hsl({$hue} 80% 78%)" stroke-width="8" stroke-linejoin="round" stroke-linecap="round" opacity="0.85">
                <path d="M168 392h464"/>
                <path d="M196 392v-56a20 20 0 0 1 20-20h40l44-58a24 24 0 0 1 19-9h132a24 24 0 0 1 19 9l44 58h60a20 20 0 0 1 20 20v56"/>
                <circle cx="268" cy="396" r="42"/>
                <circle cx="532" cy="396" r="42"/>
              </g>
              <text x="400" y="182" text-anchor="middle" font-family="Segoe UI, system-ui, sans-serif" font-size="112" font-weight="700" fill="hsl({$hue} 85% 82%)" opacity="0.5">{$this->escape($initial)}</text>
              <text x="400" y="500" text-anchor="middle" font-family="Segoe UI, system-ui, sans-serif" font-size="42" font-weight="600" fill="#ffffff">{$this->escape($this->truncate($label, 26))}</text>
              <text x="400" y="544" text-anchor="middle" font-family="Segoe UI, system-ui, sans-serif" font-size="22" fill="hsl({$hue} 60% 82%)">Foto belum tersedia</text>
            </svg>
            SVG;
    }

    public function path(string $carSlug, int $position): string
    {
        return 'cars/'.$carSlug.'/'.str_pad((string) $position, 2, '0', STR_PAD_LEFT).'.svg';
    }

    private function truncate(string $value, int $limit): string
    {
        return Str::length($value) <= $limit ? $value : Str::limit($value, $limit, '…');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

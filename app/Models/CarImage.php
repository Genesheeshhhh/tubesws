<?php

namespace App\Models;

use Database\Factories\CarImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'car_id',
    'disk',
    'path',
    'alt_text',
    'is_placeholder',
])]
#[RouteKey('id')]
class CarImage extends Model
{
    /**
     * Disk marker for photography that stays on the host it was exported from,
     * so `path` holds an absolute URL instead of a key on the public disk.
     */
    public const REMOTE_DISK = 'remote';

    /** @use HasFactory<CarImageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_placeholder' => 'boolean',
        ];
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function url(): string
    {
        return $this->isRemote() ? $this->path : Storage::disk($this->disk)->url($this->path);
    }

    public function isRemote(): bool
    {
        return $this->disk === self::REMOTE_DISK;
    }

    /**
     * Whether a source reference is a usable http(s) image link, so a value
     * copied from the export is only linked when it can actually be rendered.
     */
    public static function isRemoteUrl(?string $value): bool
    {
        if ($value === null || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}

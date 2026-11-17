<?php

namespace App\Services\Catalog;

use App\Models\Car;
use App\Models\CarImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores photos a seller uploads, re-encoding them to WebP so the public disk
 * only ever holds one image format at a bounded size.
 */
class ListingPhotoStore
{
    private const MAX_WIDTH = 1_400;

    public function __construct(
        private readonly PlaceholderImage $placeholders,
        private readonly string $disk = 'public',
    ) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @return list<CarImage>
     */
    public function store(Car $car, array $files): array
    {
        $stored = [];
        $position = $car->images()->count();

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $position++;
            $optimized = $this->encode((string) file_get_contents($file->getRealPath()));

            if ($optimized === null) {
                $position--;

                continue;
            }

            $path = 'cars/'.$car->slug.'/'.str_pad((string) $position, 2, '0', STR_PAD_LEFT).'.webp';

            Storage::disk($this->disk)->put($path, $optimized);

            $stored[] = $this->persist($car, $path);
        }

        return $stored;
    }

    /**
     * A real photo always takes over the slot a generated placeholder was
     * holding, so a listing never shows both.
     */
    private function persist(Car $car, string $path): CarImage
    {
        $placeholder = $car->images()->where('is_placeholder', true)->orderBy('id')->first();

        if ($placeholder !== null) {
            Storage::disk($this->disk)->delete($placeholder->path);

            $placeholder->update([
                'path' => $path,
                'is_placeholder' => false,
                'alt_text' => $car->brand.' '.$car->model.' tahun '.$car->year,
            ]);

            return $placeholder;
        }

        return $car->images()->create([
            'disk' => $this->disk,
            'path' => $path,
            'alt_text' => $car->brand.' '.$car->model.' tahun '.$car->year,
            'is_placeholder' => false,
        ]);
    }

    /**
     * Guarantee a listing has artwork even when the seller uploaded nothing.
     */
    public function ensureArtwork(Car $car): void
    {
        if ($car->images()->exists()) {
            return;
        }

        $path = $this->placeholders->path($car->slug, 1);

        Storage::disk($this->disk)->put($path, $this->placeholders->svg($car->brand, $car->model, $car->slug));

        $car->images()->create([
            'disk' => $this->disk,
            'path' => $path,
            'alt_text' => $car->brand.' '.$car->model.' tahun '.$car->year,
            'is_placeholder' => true,
        ]);
    }

    public function delete(CarImage $image): void
    {
        if (! $image->isRemote()) {
            Storage::disk($image->disk)->delete($image->path);
        }

        $image->delete();
    }

    private function encode(string $body): ?string
    {
        $source = @imagecreatefromstring($body);

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width < 1 || $height < 1) {
            imagedestroy($source);

            return null;
        }

        $ratio = min(self::MAX_WIDTH / $width, 1.0);
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $written = imagewebp($canvas, null, 85);
        $encoded = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $written && $encoded !== '' ? $encoded : null;
    }
}

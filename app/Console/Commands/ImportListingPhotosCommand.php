<?php

namespace App\Console\Commands;

use App\Models\Car;
use App\Models\CarImage;
use App\Services\Import\ExcelReader;
use App\Services\Semantic\CarGraphBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportListingPhotosCommand extends Command
{
    protected $signature = 'olx:photos
        {path? : Path to the xlsx export, defaults to the workbook in the project root}
        {--sheet= : Worksheet name, defaults to the first sheet}
        {--force : Re-link photos even when a listing already has one}';

    protected $description = 'Link the photo URLs referenced in the OLX export, replacing generated placeholders';

    /** @var array<string, string> */
    private array $urls = [];

    public function handle(ExcelReader $reader, CarGraphBuilder $graph): int
    {
        $path = $this->argument('path') ?: base_path('olx-gabungan-kategori-rapi-pisah.xlsx');
        $sheet = $this->option('sheet');

        if (! is_readable($path)) {
            $this->components->error("Spreadsheet not found at [{$path}].");

            return self::FAILURE;
        }

        $urls = [];

        foreach ($reader->read($path, is_string($sheet) && $sheet !== '' ? $sheet : null) as $record) {
            $reference = trim($record['order_baru'] ?? '');
            $image = trim($record['image'] ?? '');

            if ($reference !== '' && CarImage::isRemoteUrl($image)) {
                $urls[$reference] = $image;
            }
        }

        $this->components->info(count($urls).' source photo URLs found in the export.');

        $this->urls = $urls;

        $query = Car::query()
            ->where('source', 'olx-import')
            ->whereIn('source_reference', array_keys($urls));

        if (! $this->option('force')) {
            $query->whereHas('images', fn ($images) => $images->where('is_placeholder', true));
        }

        $cars = $query->with('images')->get()->keyBy('source_reference');

        if ($cars->isEmpty()) {
            $this->components->info('Nothing to link; every listing already has a photo.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($cars->count());
        $bar->start();

        $linked = 0;
        $updated = [];

        foreach ($cars as $car) {
            $this->link($car);

            $linked++;
            $updated[] = $car->id;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($updated !== []) {
            $this->components->info('Refreshing graph statements for the updated listings...');

            Car::query()
                ->with(['images', 'seller'])
                ->whereIn('id', $updated)
                ->get()
                ->each(fn (Car $car) => $graph->publish($car));
        }

        $this->components->twoColumnDetail('Photos linked', $linked);

        return self::SUCCESS;
    }

    /**
     * Point a listing at the photo URL from the export, taking over the slot a
     * generated placeholder was holding and removing the local file with it.
     */
    private function link(Car $car): void
    {
        $url = $this->urls[$car->source_reference];
        $attributes = [
            'disk' => CarImage::REMOTE_DISK,
            'path' => $url,
            'alt_text' => $car->brand.' '.$car->model.' tahun '.$car->year,
            'is_placeholder' => false,
        ];

        $placeholder = $car->images->firstWhere('is_placeholder', true);

        if ($placeholder !== null) {
            if (! $placeholder->isRemote()) {
                Storage::disk($placeholder->disk)->delete($placeholder->path);
            }

            $placeholder->update($attributes);

            return;
        }

        $car->images()->create($attributes);
    }
}

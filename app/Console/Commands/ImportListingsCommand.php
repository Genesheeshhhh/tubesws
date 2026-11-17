<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Import\ExcelReader;
use App\Services\Import\ListingImporter;
use App\Services\Semantic\SiteGraphBuilder;
use Illuminate\Console\Command;

class ImportListingsCommand extends Command
{
    protected $signature = 'olx:import
        {path? : Path to the xlsx export, defaults to the workbook in the project root}
        {--sheet= : Worksheet name, defaults to the first sheet}
        {--no-images : Ignore the photo links in the export and only write placeholders}
        {--seller=olx-import@localhost : Email of the account that owns imported listings}';

    protected $description = 'Import second-hand car listings from the OLX xlsx export into the catalogue';

    public function handle(ListingImporter $importer, ExcelReader $reader, SiteGraphBuilder $site): int
    {
        $path = $this->argument('path') ?: base_path('olx-gabungan-kategori-rapi-pisah.xlsx');

        if (! is_readable($path)) {
            $this->components->error("Spreadsheet not found at [{$path}].");

            return self::FAILURE;
        }

        $sheet = $this->option('sheet');

        $this->components->info('Reading '.realpath($path));

        foreach ($reader->sheetNames($path) as $name => $rowCount) {
            $this->components->twoColumnDetail($name, $rowCount.' rows');
        }

        $seller = User::firstOrCreate(
            ['email' => $this->option('seller')],
            [
                'name' => 'Katalog OLX Medan',
                'password' => 'password',
                'phone' => '0812-0000-0000',
                'city' => 'Medan',
            ],
        );

        $this->components->info("Importing as seller [{$seller->email}]...");

        $report = $importer->import(
            $path,
            $seller,
            withImages: ! $this->option('no-images'),
            sheet: is_string($sheet) && $sheet !== '' ? $sheet : null,
        );

        $this->newLine();

        $this->components->twoColumnDetail('<fg=green>Imported</>', $report['imported']);
        $this->components->twoColumnDetail('Already present', $report['skipped']);
        $this->components->twoColumnDetail('Photo links used', $report['photos']);
        $this->components->twoColumnDetail('Placeholders used', $report['placeholders']);
        $this->components->twoColumnDetail('Rows skipped (incomplete)', $report['failed']);
        $this->components->twoColumnDetail('Rows read', $report['total']);

        $this->components->info('Refreshing site-level graph statements...');
        $site->syncAll();

        return self::SUCCESS;
    }
}

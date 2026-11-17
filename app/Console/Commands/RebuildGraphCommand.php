<?php

namespace App\Console\Commands;

use App\Models\Car;
use App\Models\Triple;
use App\Services\Semantic\CarGraphBuilder;
use App\Services\Semantic\SiteGraphBuilder;
use Illuminate\Console\Command;

class RebuildGraphCommand extends Command
{
    protected $signature = 'semantic:rebuild {--chunk=200 : Number of cars per batch}';

    protected $description = 'Rebuild the RDF knowledge graph from the current catalogue state';

    public function handle(CarGraphBuilder $builder, SiteGraphBuilder $site): int
    {
        $chunk = max(1, (int) $this->option('chunk'));

        $this->components->info('Clearing catalogue graph...');
        Triple::where('graph', 'catalog')->delete();

        $total = Car::query()->count();
        $done = 0;

        Car::query()
            ->with(['images', 'seller'])
            ->chunkById($chunk, function ($listings) use ($builder, &$done, $total): void {
                foreach ($listings as $car) {
                    $builder->publish($car);
                    $done++;
                }

                $this->output->write("\r  {$done}/{$total} listings...");
            });

        $this->newLine(2);
        $this->components->info('Refreshing site, brand, place and seller statements...');
        $site->syncAll();

        $this->components->twoColumnDetail('Listings', $done);
        $this->components->twoColumnDetail('Triples', Triple::query()->count());

        return self::SUCCESS;
    }
}

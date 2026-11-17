<?php

namespace App\Console\Commands;

use App\Services\Fuseki\FusekiClient;
use App\Services\Fuseki\FusekiException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Loads the RDF/XML ontology next to this project into the TUBESWS dataset.
 *
 * Run it once after starting Fuseki, and again whenever the XML changes. It is
 * safe to repeat: --replace wipes the default graph first, otherwise the graph
 * is merged with what is already there.
 */
class LoadFusekiCommand extends Command
{
    protected $signature = 'fuseki:load
        {file? : Path to the RDF/XML ontology (defaults to config fuseki.ontology_path)}
        {--replace : Remove the existing default graph before uploading}
        {--check : Only verify that Fuseki and the dataset are reachable}';

    protected $description = 'Load mobil-bekas-medan-owl.xml into Apache Jena Fuseki (dataset TUBESWS)';

    public function handle(FusekiClient $fuseki): int
    {
        try {
            $this->components->info('Menghubungi Apache Jena Fuseki...');
            $fuseki->ensureDataset();

            if ($this->option('check')) {
                $this->components->info("Dataset {$fuseki->datasetName()} siap di {$fuseki->datasetUrl()}");

                return self::SUCCESS;
            }

            $path = (string) ($this->argument('file') ?: config('fuseki.ontology_path'));

            if (! is_file($path)) {
                $this->components->error("Berkas ontology tidak ditemukan: {$path}");

                return self::FAILURE;
            }

            $contents = (string) file_get_contents($path);

            if ($this->option('replace')) {
                $this->components->task('Menghapus default graph lama', fn () => $fuseki->clearDefaultGraph());
            }

            $this->components->task(
                'Mengunggah '.basename($path).' ('.number_format(strlen($contents) / 1024, 0, ',', '.').' KB)',
                fn () => $fuseki->upload($contents, 'application/rdf+xml'),
            );

            Cache::flush();

            $count = $fuseki->select(
                'SELECT (COUNT(*) AS ?n) WHERE { ?s ?p ?o }'
            )[0]['n'] ?? '0';

            $this->components->twoColumnDetail('Dataset', $fuseki->datasetName());
            $this->components->twoColumnDetail('Triples', number_format((int) $count, 0, ',', '.'));
            $this->newLine();
            $this->components->info('Selesai. Katalog sekarang dibaca langsung dari Fuseki.');

            return self::SUCCESS;
        } catch (FusekiException $exception) {
            $this->components->error($exception->getMessage());
            $this->line('  Pastikan Apache Jena Fuseki berjalan di '.config('fuseki.base_url').'.');

            return self::FAILURE;
        }
    }
}

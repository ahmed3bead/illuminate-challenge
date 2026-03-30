<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Neighborhood;
use LaravelZero\Framework\Commands\Command;

class FindDonutIncidentsCommand extends Command
{
    protected $signature = 'illuminate:find-donut
        {neighborhood=NB-7A2F : The neighborhood name}
        {--inner=0.5 : Inner radius in km}
        {--outer=2.0 : Outer radius in km}';

    protected $description = 'Find incidents in a donut around a neighborhood centroid and reveal the flag';

    public function handle(): int
    {
        $name      = (string) $this->argument('neighborhood');
        $innerKm   = (float) $this->option('inner');
        $outerKm   = (float) $this->option('outer');

        $neighborhood = Neighborhood::where('name', $name)->first();

        if (! $neighborhood) {
            $this->error("Neighborhood '{$name}' not found. Run illuminate:import-gis first.");

            return self::FAILURE;
        }

        [$clat, $clng] = $neighborhood->centroid();
        $this->info("Centroid of {$name}: lat={$clat}, lng={$clng}");
        $this->info("Donut: {$innerKm}km – {$outerKm}km");

        $incidents = $neighborhood->incidents($innerKm, $outerKm)->getResults();

        if ($incidents->isEmpty()) {
            $this->warn('No incidents found in the donut.');

            return self::FAILURE;
        }

        $this->info("Found {$incidents->count()} incidents:");
        $this->newLine();

        $headers = ['ID', 'Dist (km)', 'Code', 'Severity'];
        $rows = $incidents->map(fn ($i) => [
            $i->id,
            number_format((float) $i->dist_km, 4),
            $i->code,
            data_get($i->metadata, 'incident.severity', ''),
        ])->toArray();

        $this->table($headers, $rows);

        $flag = $incidents->map(fn ($i) => $i->code)->implode('');

        $this->newLine();
        $this->info('Flag: '.$flag);

        return self::SUCCESS;
    }
}

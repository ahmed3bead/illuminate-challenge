<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Incident;
use App\Models\Neighborhood;
use App\Services\ApiClient;
use App\Services\ConfigStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use LaravelZero\Framework\Commands\Command;
use PDO;
use RuntimeException;

class ImportGisDataCommand extends Command
{
    protected $signature = 'illuminate:import-gis';

    protected $description = 'Import neighborhoods and incidents from the remote PostgreSQL into local SQLite';

    public function handle(ConfigStore $config): int
    {
        $token = $config->getToken();
        if (! $token) {
            $this->error('No token configured. Run: illuminate --token=<your-token>');

            return self::FAILURE;
        }

        // 1. Fetch SSH + DB credentials
        $this->info('Fetching credentials...');
        $credentials = (new ApiClient($token))->getSshCredentials();

        $sshHost = (string) data_get($credentials, 'ssh.host');
        $sshPort = (int) data_get($credentials, 'ssh.port', 22);
        $sshUser = (string) data_get($credentials, 'ssh.username');
        $sshKey  = (string) data_get($credentials, 'ssh.private_key');

        $dbHost = (string) data_get($credentials, 'database.host', 'localhost');
        $dbPort = (int) data_get($credentials, 'database.port', 5432);
        $dbName = (string) data_get($credentials, 'database.name');
        $dbUser = (string) data_get($credentials, 'database.username');
        $dbPass = (string) data_get($credentials, 'database.password');

        // 2. Write SSH key to temp file
        $keyFile = tempnam(sys_get_temp_dir(), 'illuminate_key_');
        if ($keyFile === false) {
            throw new RuntimeException('Failed to create temp file for SSH key.');
        }
        file_put_contents($keyFile, $sshKey);
        chmod($keyFile, 0600);

        // 3. Open SSH tunnel
        $localPort = 15433;
        $this->info("Opening SSH tunnel on port {$localPort}...");

        $tunnelProcess = proc_open(
            sprintf(
                'ssh -i %s -o StrictHostKeyChecking=no -o BatchMode=yes -L %d:%s:%d -N %s@%s -p %d',
                escapeshellarg($keyFile),
                $localPort,
                $dbHost,
                $dbPort,
                escapeshellarg($sshUser),
                escapeshellarg($sshHost),
                $sshPort,
            ),
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
        );

        if (! is_resource($tunnelProcess)) {
            unlink($keyFile);
            $this->error('Failed to open SSH tunnel.');

            return self::FAILURE;
        }

        sleep(2);

        try {
            // 4. Connect to remote PostgreSQL
            $this->info('Connecting to remote PostgreSQL...');
            $pg = new PDO(
                "pgsql:host=127.0.0.1;port={$localPort};dbname={$dbName}",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );

            // 5. Run local migrations to prepare SQLite
            $this->info('Running local migrations...');
            Artisan::call('migrate:fresh', ['--force' => true]);

            // 6. Import neighborhoods
            $this->info('Importing neighborhoods...');
            $rows = $pg->query('SELECT id, name, boundary::text, properties::text FROM gis_data.neighborhoods');
            DB::transaction(function () use ($rows) {
                foreach ($rows as $row) {
                    Neighborhood::create([
                        'id'         => $row['id'],
                        'name'       => $row['name'],
                        'boundary'   => $row['boundary'],
                        'properties' => $row['properties'],
                    ]);
                }
            });
            $this->info('Neighborhoods imported: '.Neighborhood::count());

            // 7. Import incidents
            // location is a PostgreSQL point stored as (lat,lng)
            $this->info('Importing incidents...');
            $rows = $pg->query(
                "SELECT id, (location)[0] AS lat, (location)[1] AS lng, metadata::text, tags::text, occurred_at FROM gis_data.incidents"
            );
            DB::transaction(function () use ($rows) {
                foreach ($rows as $row) {
                    Incident::create([
                        'id'          => $row['id'],
                        'lat'         => $row['lat'],
                        'lng'         => $row['lng'],
                        'metadata'    => $row['metadata'],
                        'tags'        => $row['tags'],
                        'occurred_at' => $row['occurred_at'],
                    ]);
                }
            });
            $this->info('Incidents imported: '.Incident::count());

        } finally {
            proc_terminate($tunnelProcess);
            proc_close($tunnelProcess);
            unlink($keyFile);
        }

        $this->info('Import complete.');

        return self::SUCCESS;
    }
}

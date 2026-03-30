<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\ApiClient;
use App\Services\ConfigStore;
use LaravelZero\Framework\Commands\Command;
use PDO;
use RuntimeException;

class FetchFlagFromDbCommand extends Command
{
    protected $signature = 'illuminate:fetch-flag';

    protected $description = 'Fetch the flag via SSH tunnel to the remote PostgreSQL instance';

    public function handle(ConfigStore $config): int
    {
        $token = $config->getToken();
        if (! $token) {
            $this->error('No token configured. Run: illuminate --token=<your-token>');

            return self::FAILURE;
        }

        // 1. Fetch SSH key and DB credentials from the API
        $this->info('Fetching SSH key and database credentials...');
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

        // 2. Write the private key to a secure temp file
        $keyFile = tempnam(sys_get_temp_dir(), 'illuminate_key_');
        if ($keyFile === false) {
            throw new RuntimeException('Failed to create temp file for SSH key.');
        }
        file_put_contents($keyFile, $sshKey);
        chmod($keyFile, 0600);

        // 3. Open an SSH tunnel forwarding the remote PostgreSQL to a local port
        $localPort = 15432;
        $this->info("Opening SSH tunnel: 127.0.0.1:{$localPort} -> {$dbHost}:{$dbPort} via {$sshUser}@{$sshHost}...");

        $sshCommand = sprintf(
            'ssh -i %s -o StrictHostKeyChecking=no -o BatchMode=yes -L %d:%s:%d -N %s@%s -p %d',
            escapeshellarg($keyFile),
            $localPort,
            $dbHost,
            $dbPort,
            escapeshellarg($sshUser),
            escapeshellarg($sshHost),
            $sshPort,
        );

        $tunnelProcess = proc_open(
            $sshCommand,
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
        );

        if (! is_resource($tunnelProcess)) {
            unlink($keyFile);
            $this->error('Failed to open SSH tunnel process.');

            return self::FAILURE;
        }

        // Give the tunnel a moment to establish
        sleep(2);

        try {
            // 4. Connect to PostgreSQL through the tunnel
            $this->info('Connecting to PostgreSQL...');
            $pdo = new PDO(
                "pgsql:host=127.0.0.1;port={$localPort};dbname={$dbName}",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );

            // 5. Query for the flag
            $this->info('Querying gis_data.config...');
            $stmt = $pdo->query('SELECT key, value FROM gis_data.config');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $this->line($row['key'].': '.$row['value']);
            }
        } finally {
            proc_terminate($tunnelProcess);
            proc_close($tunnelProcess);
            unlink($keyFile);
        }

        return self::SUCCESS;
    }
}

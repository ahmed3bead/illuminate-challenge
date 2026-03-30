<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\ApiClient;
use App\Services\ConfigStore;
use LaravelZero\Framework\Commands\Command;

class SubmitRepoCommand extends Command
{
    protected $signature = 'illuminate:submit-repo
        {repo_url : GitHub repository URL}
        {cv : Path to your CV PDF file}';

    protected $description = 'Submit your GitHub repository and CV to complete the challenge';

    public function handle(ConfigStore $config): int
    {
        $token = $config->getToken();
        if (! $token) {
            $this->error('No token configured. Run: illuminate --token=<your-token>');

            return self::FAILURE;
        }

        $repoUrl = (string) $this->argument('repo_url');
        $cvPath  = (string) $this->argument('cv');

        if (! file_exists($cvPath)) {
            $this->error("CV file not found: {$cvPath}");

            return self::FAILURE;
        }

        if (strtolower(pathinfo($cvPath, PATHINFO_EXTENSION)) !== 'pdf') {
            $this->error('CV must be a PDF file.');

            return self::FAILURE;
        }

        $this->info("Submitting repository: {$repoUrl}");
        $this->info('CV: '.basename($cvPath));

        $result = (new ApiClient($token))->submitRepo($repoUrl, $cvPath);

        $message = (string) data_get($result, 'message', 'Submitted successfully.');
        $this->info($message);

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ApiClient
{
    private string $baseUrl;

    public function __construct(private readonly string $token)
    {
        /** @var string $baseUrl */
        $baseUrl = config('illuminate.api_base_url');
        $this->baseUrl = $baseUrl;
    }

    /** @return array<string, mixed> */
    public function getChallenge(): array
    {
        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/challenge");

        $response->throw();

        /** @var array<string, mixed> */
        return $response->json();
    }

    /** @return array<string, mixed> */
    public function getSshCredentials(): array
    {
        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/challenge/ssh-key");

        $response->throw();

        /** @var array<string, mixed> */
        return $response->json();
    }

    /** @return array<string, mixed> */
    public function submitRepo(string $repoUrl, string $cvPath): array
    {
        $response = Http::withToken($this->token)
            ->attach('cv', fopen($cvPath, 'r'), basename($cvPath))
            ->post("{$this->baseUrl}/challenge/submit-repo", [
                'repo_url' => $repoUrl,
            ]);

        $response->throw();

        /** @var array<string, mixed> */
        return $response->json();
    }

    /** @return array<string, mixed> */
    public function submitAnswer(string $answer): array
    {
        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/challenge/submit", [
                'answer' => $answer,
            ]);

        $response->throw();

        /** @var array<string, mixed> */
        return $response->json();
    }
}

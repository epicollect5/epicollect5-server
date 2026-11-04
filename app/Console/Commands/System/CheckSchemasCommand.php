<?php

namespace ec5\Console\Commands\System;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class CheckSchemasCommand extends Command
{
    protected $signature = 'system:check-schemas';

    protected $description = 'Check local JSON Schema versions against the shared schemas repository';

    public function handle(): int
    {
        $schemaFiles = glob(base_path('public/schemas/*.schema.json')) ?: [];
        $client = new Client([
            'connect_timeout' => 3,
            'timeout' => 5,
            'http_errors' => false,
            'headers' => ['User-Agent' => 'epicollect5-schema-version-check'],
        ]);

        /** @var array<string, PromiseInterface> $requests */
        $requests = [];
        foreach ($schemaFiles as $schemaFile) {
            $fileName = basename($schemaFile);
            $url = 'https://raw.githubusercontent.com/epicollect5/epicollect5-schemas/master/'.$fileName;
            $requests[$fileName] = $client->getAsync($url);
        }

        foreach ($schemaFiles as $schemaFile) {
            $fileName = basename($schemaFile);
            $localSchema = json_decode((string) file_get_contents($schemaFile), true);
            $localId = is_array($localSchema) ? ($localSchema['$id'] ?? null) : null;

            try {
                $response = $requests[$fileName]->wait();
                if ($response->getStatusCode() === 404) {
                    $this->warn("$fileName: not found on canonical branch master (HTTP 404)");
                    continue;
                }

                if ($response->getStatusCode() !== 200) {
                    throw new RuntimeException('HTTP '.$response->getStatusCode());
                }

                $remoteSchema = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
                $remoteId = is_array($remoteSchema) ? ($remoteSchema['$id'] ?? null) : null;
                if (!is_string($localId) || !is_string($remoteId)) {
                    throw new RuntimeException('schema is missing a string $id');
                }

                if ($localId === $remoteId) {
                    $this->info("OK $fileName: current ($localId)");
                    continue;
                }

                $localVersion = $this->schemaVersion($localId);
                $remoteVersion = $this->schemaVersion($remoteId);
                if ($localVersion !== null && $remoteVersion !== null && version_compare($localVersion, $remoteVersion, '<')) {
                    $this->warn("$fileName: outdated; local $localVersion, remote $remoteVersion");
                } elseif ($localVersion !== null && $remoteVersion !== null && version_compare($localVersion, $remoteVersion, '>')) {
                    $this->warn("$fileName: local version $localVersion is ahead of remote $remoteVersion");
                } else {
                    $this->warn("$fileName: version IDs differ; local $localId, remote $remoteId");
                }
            } catch (Throwable $exception) {
                $this->warn($fileName.': schema source is not reachable or invalid ('.$exception->getMessage().')');
            }
        }

        return self::SUCCESS;
    }

    private function schemaVersion(string $schemaId): ?string
    {
        if (preg_match('~/v?(\d+\.\d+\.\d+)$~', $schemaId, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}

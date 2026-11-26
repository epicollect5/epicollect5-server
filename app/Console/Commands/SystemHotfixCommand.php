<?php

namespace ec5\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class SystemHotfixCommand extends Command
{
    protected $signature = 'system:hotfix
        {target : Target to hotfix: pwa|fb|dw}
        {--release= : Release version label for downloaded archive name}
        {--rollback= : Roll back to a stored local release version}
        {--dry-run : Validate and report actions without modifying files}';

    protected $description = 'Hotfix PWA/formbuilder/dataviewer from a versioned release archive';

    /**
     * @var array<string, array{env: string, config: string, dest: string, releases: string, prefix: string, marker: string}>
     */
    private array $targets = [];

    public function handle(): int
    {
        $this->targets = $this->targetMap();
        $target = (string) $this->argument('target');

        if (!isset($this->targets[$target])) {
            $this->error('Invalid target "' . $target . '". Accepted targets: pwa|fb|dw.');
            return 1;
        }

        $config = $this->targets[$target];
        $rollbackVersion = $this->option('rollback');
        $isDryRun = (bool) $this->option('dry-run');

        $releaseVersion = $this->option('release');
        $hasRollback = $rollbackVersion !== null && trim((string) $rollbackVersion) !== '';
        $hasRelease = $releaseVersion !== null && trim((string) $releaseVersion) !== '';

        if ($hasRollback && $hasRelease) {
            $this->error('Use either --rollback or --release, not both.');
            return 1;
        }

        if ($hasRollback) {
            return $this->handleRollback($target, $config, trim((string) $rollbackVersion), $isDryRun);
        }

        return $this->handleDownload($target, $config, $isDryRun);
    }

    /**
     * @param array{env: string, config: string, dest: string, releases: string, prefix: string, marker: string} $config
     */
    private function handleDownload(string $target, array $config, bool $isDryRun): int
    {
        $url = (string) config($config['config'], '');
        if ($url === '') {
            $this->error('Missing required env var ' . $config['env'] . '.');
            return 1;
        }

        $version = $this->resolveVersion($url);
        if ($version === null) {
            $this->error('Unable to infer version from URL. Pass --release={version}.');
            return 1;
        }

        $tempWorkspace = $this->createTempWorkspace($target);
        $archivePath = $tempWorkspace . DIRECTORY_SEPARATOR . $config['prefix'] . '-' . $version . '.tar.gz';

        try {
            $this->info('Mode: download');
            $this->line('Target: ' . $target);
            $this->line('Version: ' . $version);
            $this->line('URL: ' . $url);
            $this->line('Destination: ' . $config['dest']);
            $this->line('Archive: ' . $archivePath);

            $this->downloadArchive($url, $archivePath);
            $sourceDir = $this->extractAndLocateSource($archivePath, $tempWorkspace, $target, $config);
            $this->validateMarker($sourceDir, $config['marker']);

            if ($isDryRun) {
                $this->info('Dry run: validation passed, no files modified.');
                return 0;
            }

            $lock = Cache::lock(
                'system-hotfix-' . $target,
                (int) config('epicollect.setup.locks.duration_system_hotfix_lock', 300)
            );
            if (!$lock->get()) {
                throw new \RuntimeException('Another hotfix operation is already running for target ' . $target . '.');
            }

            try {
                $this->atomicReplace($sourceDir, $config['dest']);
                $releaseArchivePath = $this->storeReleaseArchive($archivePath, $config, $version);
            } finally {
                $lock->release();
            }

            Log::info('system:hotfix applied', [
                'target' => $target,
                'mode' => 'download',
                'version' => $version,
                'url' => $this->safeUrlForLog($url),
                'release_archive' => $releaseArchivePath,
                'destination' => $config['dest'],
            ]);

            $this->info('Hotfix applied successfully for ' . $target . ' version ' . $version . '.');
            $this->line('Stored release archive: ' . $releaseArchivePath);
            return 0;
        } catch (Throwable $e) {
            Log::error('system:hotfix failed', [
                'target' => $target,
                'mode' => 'download',
                'version' => $version,
                'url' => $this->safeUrlForLog($url),
                'error' => $e->getMessage(),
            ]);
            $this->error($e->getMessage());
            return 1;
        } finally {
            File::deleteDirectory($tempWorkspace);
        }
    }

    /**
     * @param array{env: string, config: string, dest: string, releases: string, prefix: string, marker: string} $config
     */
    private function handleRollback(string $target, array $config, string $version, bool $isDryRun): int
    {
        $version = trim($version);
        if ($version === '') {
            $this->error('Rollback version cannot be empty.');
            return 1;
        }

        $archivePath = $config['releases'] . DIRECTORY_SEPARATOR . $config['prefix'] . '-' . $version . '.tar.gz';
        if (!is_file($archivePath)) {
            $this->error('Rollback archive not found: ' . $archivePath);
            return 1;
        }

        $tempWorkspace = $this->createTempWorkspace($target);
        try {
            $this->info('Mode: rollback');
            $this->line('Target: ' . $target);
            $this->line('Version: ' . $version);
            $this->line('Archive: ' . $archivePath);
            $this->line('Destination: ' . $config['dest']);

            $sourceDir = $this->extractAndLocateSource($archivePath, $tempWorkspace, $target, $config);
            $this->validateMarker($sourceDir, $config['marker']);

            if ($isDryRun) {
                $this->info('Dry run: validation passed, no files modified.');
                return 0;
            }

            $lock = Cache::lock(
                'system-hotfix-' . $target,
                (int) config('epicollect.setup.locks.duration_system_hotfix_lock', 300)
            );
            if (!$lock->get()) {
                throw new \RuntimeException('Another hotfix operation is already running for target ' . $target . '.');
            }

            try {
                $this->atomicReplace($sourceDir, $config['dest']);
            } finally {
                $lock->release();
            }

            Log::info('system:hotfix rollback applied', [
                'target' => $target,
                'mode' => 'rollback',
                'version' => $version,
                'archive' => $archivePath,
                'destination' => $config['dest'],
            ]);

            $this->info('Rollback applied successfully for ' . $target . ' version ' . $version . '.');
            return 0;
        } catch (Throwable $e) {
            Log::error('system:hotfix rollback failed', [
                'target' => $target,
                'mode' => 'rollback',
                'version' => $version,
                'archive' => $archivePath,
                'error' => $e->getMessage(),
            ]);
            $this->error($e->getMessage());
            return 1;
        } finally {
            File::deleteDirectory($tempWorkspace);
        }
    }

    /**
     * @return array<string, array{env: string, config: string, dest: string, releases: string, prefix: string, marker: string}>
     */
    private function targetMap(): array
    {
        return [
            'pwa' => [
                'env' => 'HOTFIX_PWA_URL',
                'config' => 'epicollect.setup.hotfix.pwa_url',
                'dest' => public_path('pwa'),
                'releases' => public_path('pwa/releases'),
                'prefix' => 'pwa',
                'marker' => '.vite/manifest.json',
            ],
            'fb' => [
                'env' => 'HOTFIX_FORMBUILDER_URL',
                'config' => 'epicollect.setup.hotfix.formbuilder_url',
                'dest' => public_path('formbuilder'),
                'releases' => public_path('formbuilder/releases'),
                'prefix' => 'fb',
                'marker' => 'js/formbuilder.js',
            ],
            'dw' => [
                'env' => 'HOTFIX_DATAVIEWER_URL',
                'config' => 'epicollect.setup.hotfix.dataviewer_url',
                'dest' => public_path('dataviewer'),
                'releases' => public_path('dataviewer/releases'),
                'prefix' => 'dv',
                'marker' => 'js/dataviewer.js',
            ],
        ];
    }

    private function resolveVersion(string $url): ?string
    {
        $optionVersion = $this->option('release');
        if ($optionVersion !== null && trim((string) $optionVersion) !== '') {
            return trim((string) $optionVersion);
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $filename = basename($path);
        if (preg_match('/^(pwa|fb|dv)-(.+)\.tar\.gz$/', $filename, $matches) === 1) {
            return $matches[2];
        }

        return null;
    }

    private function createTempWorkspace(string $target): string
    {
        $base = storage_path('app/tmp/hotfix');
        File::ensureDirectoryExists($base);
        $workspace = $base . DIRECTORY_SEPARATOR . $target . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        File::ensureDirectoryExists($workspace);
        return $workspace;
    }

    private function downloadArchive(string $url, string $archivePath): void
    {
        $timeout = (int) config('epicollect.setup.hotfix.http_timeout', 60);
        $response = Http::timeout($timeout)
            ->retry(2, 1000)
            ->withOptions(['sink' => $archivePath])
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to download archive. HTTP status: ' . $response->status());
        }

        if (!is_file($archivePath) || filesize($archivePath) === 0) {
            throw new \RuntimeException('Downloaded archive is empty: ' . $archivePath);
        }
    }

    /**
     * @param array{env: string, config: string, dest: string, releases: string, prefix: string, marker: string} $config
     */
    private function extractAndLocateSource(string $archivePath, string $tempWorkspace, string $target, array $config): string
    {
        $extractDir = $tempWorkspace . DIRECTORY_SEPARATOR . 'extract';
        File::ensureDirectoryExists($extractDir);

        $this->extractTarGz($archivePath, $extractDir);
        $sourceDir = $this->locateSourceDir($extractDir, $target);
        $this->validateMarker($sourceDir, $config['marker']);

        return $sourceDir;
    }

    private function extractTarGz(string $archivePath, string $extractDir): void
    {
        $tarPath = $archivePath;
        $tempTar = null;

        if (str_ends_with($archivePath, '.gz')) {
            $tempTar = $extractDir . DIRECTORY_SEPARATOR . 'archive.tar';
            $this->decompressGzip($archivePath, $tempTar);
            $tarPath = $tempTar;
        }

        try {
            $phar = new \PharData($tarPath);
            $phar->extractTo($extractDir, null, true);
        } catch (Throwable $e) {
            if ($this->canUseTarCommand()) {
                $this->extractWithTarCommand($archivePath, $extractDir);
            } else {
                throw new \RuntimeException('Unable to extract archive: ' . $e->getMessage());
            }
        } finally {
            if ($tempTar !== null && is_file($tempTar)) {
                @unlink($tempTar);
            }
        }

        $this->assertNoPathTraversal($extractDir);
    }

    private function decompressGzip(string $archivePath, string $tarPath): void
    {
        $gz = @gzopen($archivePath, 'rb');
        if ($gz === false) {
            throw new \RuntimeException('Unable to open gzip archive: ' . $archivePath);
        }

        $out = @fopen($tarPath, 'wb');
        if ($out === false) {
            gzclose($gz);
            throw new \RuntimeException('Unable to create temporary tar file: ' . $tarPath);
        }

        try {
            while (!gzeof($gz)) {
                $chunk = gzread($gz, 1024 * 1024);
                if ($chunk === false) {
                    throw new \RuntimeException('Unable to read gzip archive: ' . $archivePath);
                }
                if ($chunk !== '') {
                    fwrite($out, $chunk);
                }
            }
        } finally {
            gzclose($gz);
            fclose($out);
        }
    }

    private function canUseTarCommand(): bool
    {
        $result = Process::timeout(5)->run('command -v tar');
        return $result->successful() && trim($result->output()) !== '';
    }

    private function extractWithTarCommand(string $archivePath, string $extractDir): void
    {
        $command = 'tar -xzf ' . escapeshellarg($archivePath) . ' -C ' . escapeshellarg($extractDir);
        $result = Process::timeout((int) config('epicollect.setup.hotfix.http_timeout', 60))->run($command);
        if (!$result->successful()) {
            throw new \RuntimeException('Unable to extract archive with tar: ' . trim($result->errorOutput() . ' ' . $result->output()));
        }
    }

    private function assertNoPathTraversal(string $extractDir): void
    {
        $realExtractDir = realpath($extractDir);
        if ($realExtractDir === false) {
            throw new \RuntimeException('Unable to resolve extraction directory: ' . $extractDir);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $realPath = realpath($path);
            if ($realPath === false) {
                continue;
            }

            if ($item->isLink()) {
                throw new \RuntimeException('Archive contains unsupported symlink: ' . $path);
            }

            if (!str_starts_with($realPath, $realExtractDir . DIRECTORY_SEPARATOR) && $realPath !== $realExtractDir) {
                throw new \RuntimeException('Archive contains path traversal: ' . $path);
            }
        }
    }

    private function locateSourceDir(string $extractDir, string $target): string
    {
        $targetDirName = $this->targets[$target]['dest'];
        $targetBasename = basename($targetDirName);
        $entries = array_values(array_diff(scandir($extractDir) ?: [], ['.', '..']));

        $hasMarkerHere = is_file($extractDir . DIRECTORY_SEPARATOR . $this->targets[$target]['marker']);
        $hasExpectedTopDirs = is_dir($extractDir . DIRECTORY_SEPARATOR . 'assets') || is_dir($extractDir . DIRECTORY_SEPARATOR . '.vite');
        if ($hasMarkerHere || $hasExpectedTopDirs) {
            return $extractDir;
        }

        $candidates = [];
        $candidates[] = $extractDir . DIRECTORY_SEPARATOR . $targetBasename;
        $candidates[] = $extractDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $targetBasename;

        foreach ($entries as $entry) {
            $entryPath = $extractDir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($entryPath)) {
                $candidates[] = $entryPath;
                $candidates[] = $entryPath . DIRECTORY_SEPARATOR . $targetBasename;
                $candidates[] = $entryPath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $targetBasename;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_dir($candidate) && is_file($candidate . DIRECTORY_SEPARATOR . $this->targets[$target]['marker'])) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_dir($candidate) && (is_dir($candidate . DIRECTORY_SEPARATOR . 'assets') || is_dir($candidate . DIRECTORY_SEPARATOR . '.vite'))) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to locate extracted source directory for target ' . $target . '.');
    }

    private function validateMarker(string $sourceDir, string $marker): void
    {
        $markerPath = $sourceDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $marker);
        if (!is_file($markerPath)) {
            throw new \RuntimeException('Required marker file missing in archive: ' . $marker);
        }
    }

    /**
     * @param array{env: string, config: string, dest: string, releases: string, prefix: string, marker: string} $config
     */
    private function storeReleaseArchive(string $archivePath, array $config, string $version): string
    {
        File::ensureDirectoryExists($config['releases']);
        $destination = $config['releases'] . DIRECTORY_SEPARATOR . $config['prefix'] . '-' . $version . '.tar.gz';
        if (realpath($archivePath) !== realpath($destination)) {
            File::copy($archivePath, $destination);
        }
        return $destination;
    }

    private function atomicReplace(string $sourceDir, string $destination): void
    {
        $parentDir = dirname($destination);
        File::ensureDirectoryExists($parentDir);

        $this->removeStaleWorkDirs($parentDir, basename($destination));

        $stagedDir = $parentDir . DIRECTORY_SEPARATOR . '.hotfix-stage-' . basename($destination) . '-' . bin2hex(random_bytes(4));
        $backupDir = $parentDir . DIRECTORY_SEPARATOR . '.hotfix-backup-' . basename($destination) . '-' . bin2hex(random_bytes(4));

        $releasesDirName = 'releases';
        $hasDestination = is_dir($destination);

        try {
            if (!File::copyDirectory($sourceDir, $stagedDir)) {
                throw new \RuntimeException('Unable to stage hotfix files: ' . $destination);
            }

            if ($hasDestination) {
                if (!@rename($destination, $backupDir)) {
                    throw new \RuntimeException('Unable to move current destination to backup: ' . $destination);
                }
            }

            if (!@rename($stagedDir, $destination)) {
                throw new \RuntimeException('Unable to move staged hotfix into place: ' . $destination);
            }

            $this->restoreReleases(
                $backupDir . DIRECTORY_SEPARATOR . $releasesDirName,
                $destination . DIRECTORY_SEPARATOR . $releasesDirName
            );

            if (is_dir($backupDir)) {
                File::deleteDirectory($backupDir);
            }

            if (is_dir($stagedDir)) {
                File::deleteDirectory($stagedDir);
            }
        } catch (Throwable $e) {
            if (is_dir($backupDir) && !is_dir($destination)) {
                @rename($backupDir, $destination);
            } elseif (is_dir($backupDir) && is_dir($destination)) {
                File::deleteDirectory($destination);
                @rename($backupDir, $destination);
            }

            if (is_dir($stagedDir)) {
                File::deleteDirectory($stagedDir);
            }

            throw $e;
        }
    }

    private function removeStaleWorkDirs(string $parentDir, string $basename): void
    {
        $entries = scandir($parentDir) ?: [];
        foreach ($entries as $entry) {
            if (!str_starts_with($entry, '.hotfix-stage-' . $basename . '-') &&
                !str_starts_with($entry, '.hotfix-backup-' . $basename . '-')) {
                continue;
            }

            $path = $parentDir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path) && !is_link($path)) {
                File::deleteDirectory($path);
            }
        }
    }

    private function restoreReleases(string $backupReleases, string $destinationReleases): void
    {
        if (!is_dir($backupReleases)) {
            return;
        }

        File::ensureDirectoryExists($destinationReleases);
        $items = array_diff(scandir($backupReleases) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $from = $backupReleases . DIRECTORY_SEPARATOR . $item;
            $to = $destinationReleases . DIRECTORY_SEPARATOR . $item;
            if (file_exists($to)) {
                continue;
            }

            if (is_dir($from)) {
                File::copyDirectory($from, $to);
            } else {
                File::copy($from, $to);
            }
        }
    }

    private function safeUrlForLog(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return '[invalid-url]';
        }

        $safe = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        if (isset($parts['path'])) {
            $safe .= $parts['path'];
        }
        return $safe;
    }
}

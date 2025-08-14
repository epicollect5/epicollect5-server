<?php

namespace ec5\Traits\Eloquent;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use ec5\Libraries\Utilities\Common;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use Exception;
use File;
use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Storage;
use Throwable;

trait Remover
{
    public function removeProject($projectId, $projectSlug): bool
    {
        try {
            $project = Project::where('id', $projectId)
                ->where('slug', $projectSlug)
                ->first();
            $project->delete();
            return true;
        } catch (Throwable $e) {
            Log::error('Error removeProject()', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * @throws Throwable
     */
    public function removeEntriesChunk($projectId): bool
    {
        $initialMemoryUsage = memory_get_usage();
        $peakMemoryUsage = memory_get_peak_usage();
        $projectStats = new ProjectStats();

        try {
            DB::beginTransaction();

            //imp: branch entries are removed by FK constraint ON DELETE CASCADE
            Entry::where('project_id', $projectId)
                ->limit(config('epicollect.setup.bulk_deletion.chunk_size_entries'))
                ->delete();
            if (!$projectStats->updateProjectStats($projectId)) {
                throw new Exception('Failed to count entries after archive');
            }

            // Check and update peak memory usage
            $peakMemoryUsage = max($peakMemoryUsage, memory_get_peak_usage());

            $finalMemoryUsage = memory_get_usage();
            $memoryUsed = $finalMemoryUsage - $initialMemoryUsage;

            $initialMemoryUsage = Common::formatBytes($initialMemoryUsage);
            $finalMemoryUsage = Common::formatBytes($finalMemoryUsage);
            $memoryUsed = Common::formatBytes($memoryUsed);
            $peakMemoryUsage = Common::formatBytes($peakMemoryUsage);

            // Log memory usage details
            Log::info("Memory Usage for Deleting Entries");
            Log::info("Initial Memory Usage: " . $initialMemoryUsage);
            Log::info("Final Memory Usage: " . $finalMemoryUsage);
            Log::info("Memory Used: " . $memoryUsed);
            Log::info("Peak Memory Usage: " . $peakMemoryUsage);

            //commit after each batch to release resources
            DB::commit();
            // Pause for a few seconds to avoid overloading/locking the database
            sleep(3);
            return true;
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            DB::rollBack();
            return false;
        }
    }

    public function removeAllTheEntriesMediaFoldersLocal($projectRef): void
    {
        //remove all the entries media folders
        $drivers = config('epicollect.media.entries_deletable');
        foreach ($drivers as $driver) {
            // Get disk, path prefix and all directories for this driver
            $diskRoot = config('filesystems.disks.' . $driver . '.root').'/';
            // Note: need to use File facade here, as Storage doesn't delete
            File::deleteDirectory($diskRoot . $projectRef);
        }
    }


    /**
     * Create S3 client from Laravel filesystem disk configuration
     */
    protected function createS3Client(array $config): S3Client
    {
        $clientConfig = [
            'version' => 'latest',
            'region' => $config['region'],
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ];

        // Add endpoint for DigitalOcean Spaces or other S3-compatible services
        if (!empty($config['endpoint'])) {
            $clientConfig['endpoint'] = $config['endpoint'];
        }

        // Set path style endpoint if specified
        if (isset($config['use_path_style_endpoint'])) {
            $clientConfig['use_path_style_endpoint'] = $config['use_path_style_endpoint'];
        }

        return new S3Client($clientConfig);
    }


    /**
     * @throws Exception
     */
    public function removeMediaChunk(string $projectRef): int
    {
        $drivers = config('epicollect.media.entries_deletable');
        $totalDeleted = 0;
        $maxFiles = config('epicollect.setup.bulk_deletion.chunk_size_media');

        //if S3, max files must be 1000 due to S3 listObjects operations limit
        if (config("filesystems.default") === 's3') {
            $maxFiles = 1000;
        }

        foreach ($drivers as $driver) {
            if ($totalDeleted >= $maxFiles) {
                break; // We've hit our batch limit
            }

            try {
                $config = config("filesystems.disks.$driver");
                $remainingCapacity = $maxFiles - $totalDeleted;

                // Check if this is S3 or local storage
                if ($config['driver'] === 's3') {
                    $diskRoot = config('filesystems.disks.' . $driver . '.root').'/';
                    $s3Client = $this->createS3Client($config);
                    $bucket = $config['bucket'];
                    $prefix = $diskRoot . $projectRef;
                    $deletedCount = $this->deleteOneBatchByPrefixS3($s3Client, $bucket, $prefix, $remainingCapacity);
                } else {
                    // Handle local storage using Laravel Storage facade
                    $deletedCount = $this->deleteOneBatchByPrefixLocal($driver, $projectRef, $remainingCapacity);
                }

                if ($deletedCount > 0) {
                    $totalDeleted += $deletedCount;
                    Log::info("Deleted $deletedCount entries for $projectRef on disk $driver. Total so far: $totalDeleted");
                }

            } catch (Exception $e) {
                Log::error("Failed batch delete for $projectRef on disk $driver: " . $e->getMessage());
                throw $e;
            }
        }

        if ($totalDeleted > 0) {
            Log::info("Total deleted $totalDeleted entries for $projectRef across all drivers.");
        }

        return $totalDeleted;
    }

    private function deleteOneBatchByPrefixS3(S3Client $s3Client, string $bucket, string $prefix, int $maxFiles = 1000): int
    {
        $maxRetries = 3;
        $retryDelay = 1; // seconds

        $listParams = [
            'Bucket' => $bucket,
            'Prefix' => $prefix,
            'MaxKeys' => $maxFiles,
        ];

        $result = $s3Client->listObjectsV2($listParams);

        if (empty($result['Contents'])) {
            return 0;
        }

        // Ensure we don't exceed the maxFiles limit, even if S3 returns more
        $objects = array_slice(
            array_map(fn ($obj) => ['Key' => $obj['Key']], $result['Contents']),
            0,
            $maxFiles
        );

        $deleteResult = null;
        for ($retry = 0; $retry <= $maxRetries; $retry++) {
            try {
                $deleteResult = $s3Client->deleteObjects([
                    'Bucket' => $bucket,
                    'Delete' => [
                        'Objects' => $objects,
                        'Quiet' => true,
                    ],
                ]);
                break;
            } catch (S3Exception $e) {
                if ($retry === $maxRetries || !$this->isRetryableError($e)) {
                    throw $e;
                }
                sleep($retryDelay * pow(2, $retry));
            }
        }

        if (!empty($deleteResult['Errors'])) {
            Log::error("Errors deleting S3 objects with prefix $prefix:", $deleteResult['Errors']);
        }

        return count($objects);
    }

    /**
     * @throws Exception
     */
    private function deleteOneBatchByPrefixLocal(string $disk, string $projectRef, int $maxFiles = 1000): int
    {
        $deletedCount = 0;

        $storage = Storage::disk($disk);

        if (!$storage->exists($projectRef)) {
            return 0;
        }

        $fullPath = $storage->path($projectRef);

        // Use iterator to avoid loading all files into memory
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($deletedCount >= $maxFiles) {
                break; // Stop before exceeding the limit
            }

            if ($file->isFile()) {
                try {
                    // Get relative path from the project root
                    $relativePath = str_replace($fullPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath); // Normalize path separators

                    // Use Laravel Storage to delete (maintains consistency with Laravel's file handling)
                    $fullRelativePath = $projectRef . '/' . $relativePath;

                    if ($storage->delete($fullRelativePath)) {
                        $deletedCount++;
                    } else {
                        // This would be a real error (permissions, etc.)
                        Log::error("Failed to delete file: $fullRelativePath");
                        throw new Exception("Failed to delete file: $fullRelativePath");
                    }
                } catch (Exception $e) {
                    Log::error("Error deleting file {$file->getPathname()}: " . $e->getMessage());
                    throw $e;
                }
            }
        }

        // Try to remove the directory if it's now empty
        try {
            if ($storage->exists($projectRef) && empty($storage->files($projectRef))) {
                $storage->deleteDirectory($projectRef);
            }
        } catch (Exception $e) {
            Log::warning("Failed to remove directory: $projectRef - " . $e->getMessage());
        }

        return $deletedCount;
    }

    public function isRetryableError(S3Exception $e): bool
    {
        $statusCode = $e->getStatusCode();

        // Check HTTP status codes first (more reliable)
        $retryableStatusCodes = [
            429, // Too Many Requests
            500, // Internal Server Error
            502, // Bad Gateway
            503, // Service Unavailable
            504, // Gateway Timeout
        ];

        if (in_array($statusCode, $retryableStatusCodes)) {
            return true;
        }

        // Fallback to AWS-specific error codes for additional cases
        $awsErrorCode = $e->getAwsErrorCode();
        $retryableAwsCodes = [
            'RequestTimeout',
            'ServiceUnavailable',
            'SlowDown',
            'RequestLimitExceeded',
            'InternalError'
        ];

        return in_array($awsErrorCode, $retryableAwsCodes);
    }
}

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
     * @throws Exception
     */
    protected function createS3Client(array $config): S3Client
    {
        //check config values are defined
        $requiredKeys = ['region', 'bucket', 'key', 'secret'];
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key])) {
                throw new Exception("Missing required config key $key for S3 disk");
            }
        }

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
    public function removeMediaChunk(string $projectRef, int $projectId): int
    {
        $diskNames = config('epicollect.media.entries_deletable');
        $totalDeleted = 0;
        $maxFiles = config('epicollect.setup.bulk_deletion.chunk_size_media');

        //if S3, max files must be 1000 due to S3 listObjects operations limit
        if (config("filesystems.default") === 's3') {
            $maxFiles = 1000;
        }

        foreach ($diskNames as $diskName) {
            if ($totalDeleted >= $maxFiles) {
                break; // We've hit our batch limit
            }

            try {
                $config = config("filesystems.disks.$diskName");
                $remainingCapacity = $maxFiles - $totalDeleted;

                // Check if this is S3 or local storage
                if ($config['driver'] === 's3') {
                    $diskRoot = config('filesystems.disks.' . $diskName . '.root').'/';
                    $s3Client = $this->createS3Client($config);
                    $bucket = $config['bucket'];
                    $prefix = $diskRoot . $projectRef;
                    $deletionResult = $this->deleteOneBatchByPrefixS3($s3Client, $bucket, $prefix, $remainingCapacity);
                } else {
                    // Handle local storage using Laravel Storage facade
                    $deletionResult = $this->deleteOneBatchByPrefixLocal($diskName, $projectRef, $remainingCapacity);
                }

                if ($deletionResult['deletedCount'] > 0) {
                    $totalDeleted += $deletionResult['deletedCount'];
                    //adjust total bytes (negative delta)
                    ProjectStats::where('project_id', $projectId)
                        ->first()
                        ->decrementMediaStorageUsage(
                            $deletionResult['deletedBytes']['photo'],
                            $deletionResult['deletedFiles']['photo'],
                            $deletionResult['deletedBytes']['audio'],
                            $deletionResult['deletedFiles']['audio'],
                            $deletionResult['deletedBytes']['video'],
                            $deletionResult['deletedFiles']['video']
                        );
                    Log::info("Deleted {{$deletionResult['deletedCount']}} entries for $projectRef on disk $diskName. Total so far: $totalDeleted");
                }

            } catch (Exception $e) {
                Log::error("Failed batch delete for $projectRef on disk $diskName: " . $e->getMessage());
                throw $e;
            }
        }

        if ($totalDeleted > 0) {
            Log::info("Total deleted $totalDeleted entries for $projectRef across all drivers.");
        }

        return $totalDeleted;
    }

    private function deleteOneBatchByPrefixS3(S3Client $s3Client, string $bucket, string $prefix, int $maxFiles = 1000): array
    {
        $maxRetries = 3;
        $retryDelay = 1; // seconds

        $listParams = [
            'Bucket' => $bucket,
            'Prefix' => $prefix,
            'MaxKeys' => $maxFiles,
        ];

        $result = null;
        for ($retry = 0; $retry <= $maxRetries; $retry++) {
            try {
                $result = $s3Client->listObjectsV2($listParams);
                break;
            } catch (S3Exception $e) {
                if ($retry === $maxRetries || !Common::isRetryableError($e)) {
                    throw $e;
                }
                sleep($retryDelay * pow(2, $retry));
            }
        }

        if (empty($result['Contents'])) {
            return [
                'deletedCount' => 0,
                'deletedFiles' => [
                    'photo' => 0,
                    'audio' => 0,
                    'video' => 0,
                ],
                'deletedBytes' => [
                    'photo' => 0,
                    'audio' => 0,
                    'video' => 0,
                ]
            ];
        }

        // Ensure we don't exceed the maxFiles limit, even if S3 returns more
        // Also preserve the Size information for byte counting
        $objects = array_slice(
            array_map(fn ($obj) => [
                'Key' => $obj['Key'],
                'Size' => $obj['Size'] ?? 0
            ], $result['Contents']),
            0,
            $maxFiles
        );

        $deleteResult = null;
        for ($retry = 0; $retry <= $maxRetries; $retry++) {
            try {
                $deleteResult = $s3Client->deleteObjects([
                    'Bucket' => $bucket,
                    'Delete' => [
                        'Objects' => array_map(fn ($obj) => ['Key' => $obj['Key']], $objects),
                        'Quiet' => true,
                    ],
                ]);
                break;
            } catch (S3Exception $e) {
                if ($retry === $maxRetries || !Common::isRetryableError($e)) {
                    throw $e;
                }
                sleep($retryDelay * pow(2, $retry));
            }
        }

        if (!empty($deleteResult['Errors'])) {
            Log::error("Errors deleting S3 objects with prefix $prefix:", $deleteResult['Errors']);
        }

        // Calculate deleted bytes by media type
        $deletedBytes = [
            'photo' => 0,
            'audio' => 0,
            'video' => 0,
        ];
        $deletedFiles = [
            'photo' => 0,
            'audio' => 0,
            'video' => 0,
        ];

        foreach ($objects as $object) {
            $mediaType = $this->getMediaTypeFromPath($object['Key']);
            $deletedBytes[$mediaType] += $object['Size'];
            $deletedFiles[$mediaType]++;
        }

        return [
            'deletedCount' => count($objects),
            'deletedFiles' => $deletedFiles,
            'deletedBytes' => $deletedBytes,
        ];
    }


    /**
     * @throws Exception
     */
    private function deleteOneBatchByPrefixLocal(string $disk, string $projectRef, int $maxFiles = 1000): array
    {
        $deletedCount = 0;
        $deletedFiles = [
            'photo' => 0,
            'audio' => 0,
            'video' => 0,
        ];
        $deletedBytes = [
            'photo' => 0,
            'audio' => 0,
            'video' => 0,
        ];

        $storage = Storage::disk($disk);

        if (!$storage->exists($projectRef)) {
            return [
                'deletedCount' => $deletedCount,
                'deletedFiles' => $deletedFiles,
                'deletedBytes' => $deletedBytes,
            ];
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

                    $fileSize = $file->getSize();

                    if ($storage->delete($fullRelativePath)) {
                        $deletedCount++;

                        // Determine media type based on path
                        $mediaType = $this->getMediaTypeFromPath($relativePath);
                        Log::info('$mediaType, $disk', ['mediaType' => $mediaType, 'disk' => $disk]);
                        $deletedBytes[$mediaType] += $fileSize;
                        $deletedFiles[$mediaType]++;
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

        return [
            'deletedCount' => $deletedCount,
            'deletedFiles' => $deletedFiles,
            'deletedBytes' => $deletedBytes
        ];
    }

    /**
     * Determine media type based on file path
     */
    private function getMediaTypeFromPath(string $relativePath): string
    {
        $normalizedPath = '/' . trim($relativePath, '/') . '/';

        if (str_contains($normalizedPath, '/photo/')) {
            return 'photo';
        }

        if (str_contains($normalizedPath, '/audio/')) {
            return 'audio';
        }

        if (str_contains($normalizedPath, '/video/')) {
            return 'video';
        }

        // Default to photo for unmatched files
        return 'photo';
    }
}

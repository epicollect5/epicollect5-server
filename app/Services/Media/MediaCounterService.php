<?php

namespace ec5\Services\Media;

use Aws\S3\S3Client;
use FilesystemIterator;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class MediaCounterService
{
    public function countersMedia($projectRef): array
    {
        $totalCount = 0;
        $photoCount = 0;
        $audioCount = 0;
        $videoCount = 0;

        $drivers = config('epicollect.media.entries_deletable');

        foreach ($drivers as $driver) {
            $disk = Storage::disk($driver);
            $diskRoot = rtrim(config("filesystems.disks.$driver.root"), '/') . '/';
            $prefix = $diskRoot . $projectRef;

            if (config("filesystems.default") === 's3') {
                $config = config("filesystems.disks.$driver");

                $s3Client = new S3Client([
                    'version'     => 'latest',
                    'region'      => $config['region'],
                    'endpoint'    => $config['endpoint'] ?? null,
                    'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
                    'credentials' => [
                        'key'    => $config['key'],
                        'secret' => $config['secret'],
                    ],
                ]);

                $bucket = $config['bucket'];
                $continuationToken = null;

                do {
                    $params = [
                        'Bucket' => $bucket,
                        'Prefix' => $prefix . '/',
                        'MaxKeys' => 1000,
                    ];

                    if ($continuationToken) {
                        $params['ContinuationToken'] = $continuationToken;
                    }

                    $result = $s3Client->listObjectsV2($params);

                    if (!empty($result['Contents'])) {
                        foreach ($result['Contents'] as $object) {
                            $key = $object['Key'];

                            $totalCount++;

                            if (str_contains($key, '/photo/')) {
                                $photoCount++;
                            } elseif (str_contains($key, '/audio/')) {
                                $audioCount++;
                            } elseif (str_contains($key, '/video/')) {
                                $videoCount++;
                            }
                        }
                    }

                    $continuationToken = $result['IsTruncated'] ? $result['NextContinuationToken'] ?? null : null;
                } while ($continuationToken);

            } else {
                // Initialize counters
                $totalCount = 0;
                $photoCount = 0;
                $audioCount = 0;
                $videoCount = 0;

                // Get the absolute local path for the given project reference
                $fullPath = $disk->path($projectRef);

                /**
                 * Using RecursiveDirectoryIterator + RecursiveIteratorIterator here is the
                 * most memory-efficient way to traverse a local filesystem in PHP.
                 *
                 * Why it's better than Laravel's Storage::allFiles():
                 * ---------------------------------------------------
                 * - Storage::allFiles() / Storage::files() first collects ALL file paths into an array,
                 *   so memory usage grows with the number of files (e.g., 250K files = huge memory spike).
                 * - SPL Iterators yield files one-by-one as we loop, keeping memory usage constant (~KBs).
                 * - Startup time is faster because files are processed immediately rather than after a full scan.
                 * - Works efficiently even for hundreds of thousands of files without exhausting memory.
                 *
                 * Notes:
                 * - This approach works for LOCAL storage only. For S3 or other drivers, Storage methods are still required.
                 * - We normalize path separators for consistent matching across OSes.
                 */
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($fullPath, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $totalCount++;

                        // Get relative path from project root (more efficient than calling SplFileInfo methods repeatedly)
                        $relativePath = substr($file->getPathname(), strlen($fullPath) + 1);
                        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath); // Normalize for cross-platform

                        // Micro-optimization: use strpos() instead of str_contains() for speed
                        if (str_contains($relativePath, '/photo/')) {
                            $photoCount++;
                        } elseif (str_contains($relativePath, '/audio/')) {
                            $audioCount++;
                        } elseif (str_contains($relativePath, '/video/')) {
                            $videoCount++;
                        }
                    }
                }
            }
        }

        return [
            'type' => 'counters-project-media',
            'id' => $projectRef,
            'counters' => [
                'total' => $totalCount,
                'photo' => $photoCount,
                'audio' => $audioCount,
                'video' => $videoCount,
            ]
        ];
    }

}

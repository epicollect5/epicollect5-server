<?php

namespace ec5\Services\Media;

use Aws\S3\S3Client;
use FilesystemIterator;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class MediaCounterService
{
    private int $totalCount = 0;
    private int $photoCount = 0;
    private int $audioCount = 0;
    private int $videoCount = 0;

    private int $totalSize = 0;
    private int $photoSize = 0;
    private int $audioSize = 0;
    private int $videoSize = 0;

    public function countersMedia(string $projectRef): array
    {
        $this->resetCounters();

        $drivers = config('epicollect.media.entries_deletable');

        foreach ($drivers as $driver) {
            $disk = Storage::disk($driver);
            $diskRoot = rtrim(config("filesystems.disks.$driver.root"), '/') . '/';
            $prefix = $diskRoot . $projectRef;

            if (config("filesystems.default") === 's3') {
                $this->countMediaS3($driver, $prefix);
            } else {
                $this->countMediaLocal($disk, $projectRef);
            }
        }

        return [
            'type' => 'counters-project-media',
            'id' => $projectRef,
            'counters' => [
                'total' => $this->totalCount,
                'photo' => $this->photoCount,
                'audio' => $this->audioCount,
                'video' => $this->videoCount
            ],
            'sizes' => [
                'total_bytes' => $this->totalSize,
                'photo_bytes' => $this->photoSize,
                'audio_bytes' => $this->audioSize,
                'video_bytes' => $this->videoSize
            ]
        ];
    }

    private function resetCounters(): void
    {
        $this->totalCount = $this->photoCount = $this->audioCount = $this->videoCount = 0;
        $this->totalSize = $this->photoSize = $this->audioSize = $this->videoSize = 0;
    }

    private function countMediaLocal($disk, string $projectRef): void
    {
        $fullPath = $disk->path($projectRef);

        if (!is_dir($fullPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size = $file->getSize(); // already available from SplFileInfo
                $this->totalCount++;
                $this->totalSize += $size;

                $path = $file->getPathname();

                if (str_contains($path, '/' . config('epicollect.strings.media_input_types.photo') . '/')) {
                    $this->photoCount++;
                    $this->photoSize += $size;
                } elseif (str_contains($path, '/' . config('epicollect.strings.media_input_types.audio') . '/')) {
                    $this->audioCount++;
                    $this->audioSize += $size;
                } elseif (str_contains($path, '/' . config('epicollect.strings.media_input_types.video') . '/')) {
                    $this->videoCount++;
                    $this->videoSize += $size;
                }
            }
        }
    }

    private function countMediaS3(string $driver, string $prefix): void
    {
        $config = config("filesystems.disks.$driver");

        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => $config['region'],
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
            'credentials' => [
                'key' => $config['key'],
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
                    $size = $object['Size'];
                    $key = $object['Key'];

                    $this->totalCount++;
                    $this->totalSize += $size;

                    if (str_contains($key, '/photo/')) {
                        $this->photoCount++;
                        $this->photoSize += $size;
                    } elseif (str_contains($key, '/audio/')) {
                        $this->audioCount++;
                        $this->audioSize += $size;
                    } elseif (str_contains($key, '/video/')) {
                        $this->videoCount++;
                        $this->videoSize += $size;
                    }
                }
            }

            $continuationToken = $result['IsTruncated']
                ? ($result['NextContinuationToken'] ?? null)
                : null;
        } while ($continuationToken);
    }
}

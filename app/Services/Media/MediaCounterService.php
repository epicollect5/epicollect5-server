<?php

namespace ec5\Services\Media;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;

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
                // Local filesystem
                $allFiles = $disk->allFiles($projectRef); // relative to root

                foreach ($allFiles as $path) {
                    $totalCount++;

                    if (str_contains($path, '/photo/')) {
                        $photoCount++;
                    } elseif (str_contains($path, '/audio/')) {
                        $audioCount++;
                    } elseif (str_contains($path, '/video/')) {
                        $videoCount++;
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

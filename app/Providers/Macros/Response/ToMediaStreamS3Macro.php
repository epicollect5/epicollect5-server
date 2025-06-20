<?php

namespace ec5\Providers\Macros\Response;

use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ToMediaStreamS3Macro extends ServiceProvider
{
    /**
     * Registers the 'toMediaStreamS3' macro to enable streaming of media files with HTTP byte-range support.
     *
     * The macro allows clients to request either the entire media file or a specific byte range using the 'Range' header.
     * It sets appropriate HTTP headers for content type, content length, and byte ranges, and returns a streamed response.
     * If the file cannot be streamed, a JSON API error response with status 404 is returned.
     */
    public function boot(): void
    {
        Response::macro('toMediaStreamS3', function (Request $request, string $filepath, string $inputType) {
            try {
                $s3 = new S3Client([
                    'region' => config('filesystems.disks.s3.region'),
                    'endpoint' => config('filesystems.disks.s3.endpoint'), // <- add this
                    'use_path_style_endpoint' => false, // usually required for S3-compatible services
                    'credentials' => [
                        'key' => config('filesystems.disks.s3.key'),
                        'secret' => config('filesystems.disks.s3.secret'),
                    ],
                ]);

                $bucket = config('filesystems.disks.s3.bucket');

                try {
                    $head = $s3->headObject([
                        'Bucket' => $bucket,
                        'Key' => $filepath,
                    ]);
                } catch (Throwable $e) {
                    Log::error('Cannot get S3 file head', ['exception' => $e->getMessage()]);
                    $error['api-media-controller'] = ['ec5_103'];
                    return Response::apiErrorCode(404, $error);
                }

                $filesize = $head['ContentLength'];
                $contentType = config('epicollect.media.content_type.' . $inputType, 'application/octet-stream');

                $start = 0;
                $end = $filesize - 1;
                $status = 200;

                if ($request->headers->has('Range')) {
                    if (preg_match('/bytes=(\d*)-(\d*)/', $request->header('Range'), $matches)) {
                        $start = $matches[1] !== '' ? intval($matches[1]) : 0;
                        $end = $matches[2] !== '' ? intval($matches[2]) : $filesize - 1;
                        $status = 206;
                    }
                }

                $length = $end - $start + 1;

                $response = new StreamedResponse(function () use ($s3, $bucket, $filepath, $start, $end) {
                    $result = $s3->getObject([
                        'Bucket' => $bucket,
                        'Key' => $filepath,
                        'Range' => "bytes=$start-$end",
                    ]);
                    $phpStream = $result['Body']->detach();
                    fpassthru($phpStream);
                    fclose($phpStream);
                }, $status);

                $response->headers->set('Content-Type', $contentType);
                $response->headers->set('Content-Length', $length);
                $response->headers->set('Accept-Ranges', 'bytes');

                if ($status === 206) {
                    $response->headers->set('Content-Range', "bytes $start-$end/$filesize");
                }

                return $response;
            } catch (Throwable $e) {
                Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
                $error['api-media-controller'] = ['ec5_103'];
                return Response::apiErrorCode(404, $error);
            }
        });
    }
}

<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ToMediaStreamLocalMacro extends ServiceProvider
{
    /**
     * Registers the 'toMediaStreamLocal' macro to enable streaming of media files with HTTP byte-range support.
     *
     * The macro allows clients to request either the entire media file or a specific byte range using the 'Range' header.
     * It sets appropriate HTTP headers for content type, content length, and byte ranges, and returns a streamed response.
     * If the file cannot be streamed, a JSON API error response with status 404 is returned.
     */
    public function boot(): void
    {
        Response::macro('toMediaStreamLocal', function (Request $request, $filepath, $inputType) {
            try {
                $contentType = config('epicollect.media.content_type.' . $inputType);
                $filesize = filesize($filepath);
                $start = 0;
                $end = $filesize - 1;
                $status = 200;

                $callback = function () use ($filepath) {
                    $stream = fopen($filepath, 'rb');
                    fpassthru($stream);
                    fclose($stream);
                };

                // Check for a valid Range request
                if ($request->headers->has('Range')) {
                    $rangeHeader = $request->header('Range');
                    if (preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $matches)) {
                        $start = $matches[1] !== '' ? intval($matches[1]) : 0;
                        $end = $matches[2] !== '' ? intval($matches[2]) : ($filesize - 1);

                        // Sanity check range values
                        if ($start <= $end && $end < $filesize) {
                            $length = $end - $start + 1;
                            $status = 206;

                            $callback = function () use ($filepath, $start, $length) {
                                $stream = fopen($filepath, 'rb');
                                fseek($stream, $start);
                                echo fread($stream, $length);
                                fclose($stream);
                            };
                        }
                    }
                }

                $length = ($end - $start + 1);

                $response = new StreamedResponse($callback, $status);
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

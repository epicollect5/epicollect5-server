<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ToMediaStreamMacro extends ServiceProvider
{
    public function boot(): void
    {
        Response::macro('toMediaStream', function (Request $request, $realFilepath, $inputType) {
            try {
                // Get the content type based on input type (audio or video question)
                $contentType = config('epicollect.media.content_type.' . $inputType);

                $response = new StreamedResponse(function () use ($realFilepath) {
                    $stream = fopen($realFilepath, 'rb');
                    fpassthru($stream);
                    fclose($stream);
                });

                $response->headers->set('Content-Type', $contentType);
                $response->headers->set('Content-Length', filesize($realFilepath));
                $response->headers->set('Accept-Ranges', 'bytes');


                $start = 0;
                $end = filesize($realFilepath) - 1;
                $length = $end - $start + 1;

                if ($request->headers->has('Range')) {
                    $range = $request->header('Range');
                    list($unit, $range) = explode('=', $range, 2);
                    list($start, $end) = explode('-', $range);

                    $start = intval($start);
                    $end = $end === '' ? filesize($realFilepath) - 1 : intval($end);
                    $length = $end - $start + 1;
                }

                $response->setStatusCode(206);
                $response->headers->set('Content-Range', "bytes $start-$end/" . filesize($realFilepath));
                $response->headers->set('Content-Length', $length);

                $response->setCallback(function () use ($realFilepath, $start, $length) {
                    $stream = fopen($realFilepath, 'rb');
                    fseek($stream, $start);
                    echo fread($stream, $length);
                    fclose($stream);
                });

                return $response;

            } catch (Throwable $e) {
                Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
                $error['api-media-controller'] = ['ec5_103'];
                return Response::apiErrorCode(404, $error);
            }
        });
    }
}

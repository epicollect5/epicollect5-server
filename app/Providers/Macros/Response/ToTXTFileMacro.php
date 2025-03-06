<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ToTXTFileMacro extends ServiceProvider
{
    /**
     * Registers the "toTXTFile" macro on the Response facade.
     *
     * This macro provides a convenient way to generate a downloadable plain text file response.
     * It assembles headers to set the content type as UTF-8 encoded plain text and configures the
     * Content-Disposition header to prompt a file download with the specified filename.
     */
    public function boot(): void
    {
        Response::macro('toTXTFile', function ($content, $filename) {
            $headers = [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
            return Response::make($content, 200, $headers);
        });
    }
}

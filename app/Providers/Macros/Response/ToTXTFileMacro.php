<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ToTXTFileMacro extends ServiceProvider
{
    public function boot(): void
    {
        Response::macro('toTXTFile', function ($content, $filename) {
            $headers = [
                'Content-type' => 'text/plain; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
            return Response::make($content, 200, $headers);
        });
    }
}

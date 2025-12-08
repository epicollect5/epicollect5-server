<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ToCSVFileMacro extends ServiceProvider
{
    public function boot(): void
    {
        Response::macro('toCSVFile', function ($content, $filename) {
            $headers = [
                'Content-type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
            return Response::make($content, 200, $headers);
        });
    }
}

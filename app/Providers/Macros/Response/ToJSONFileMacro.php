<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ToJSONFileMacro extends ServiceProvider
{
    public function boot()
    {
        Response::macro('toJSONFile', function ($content, $filename) {
            $headers = [
                'Content-type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
            return Response::make($content, 200, $headers);
        });
    }
}
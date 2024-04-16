<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ToCSVStreamMacro extends ServiceProvider
{
    public function boot()
    {
        Response::macro('toCSVStream', function ($data) {
            // fpassthru($data); // Not necessary in this context

            // Get the CSV content from the output buffer and clean it
            $csvContent = ob_get_clean();

            // Return a response with CSV data
            return response($csvContent);
        });
    }
}
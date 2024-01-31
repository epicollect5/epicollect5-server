<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ToCSVStreamMacro extends ServiceProvider
{
    public function boot()
    {
        Response::macro('toCSVStream', function ($data) {
            // Set headers for CSV content
            $headers = [
                'Content-Type' => 'text/csv',
            ];
            // Return a response instance with streaming CSV content
            return response()->stream(function () use ($data) {
                $output = fopen('php://output', 'w');
                // Output CSV rows
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }
                fclose($output);
            }, 200, $headers);
        });
    }
}
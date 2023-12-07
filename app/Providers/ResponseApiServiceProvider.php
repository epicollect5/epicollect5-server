<?php

namespace ec5\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;

class ResponseApiServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //create a macro to wrap the response in "data" root property and set custom header according to jsonapi.org
        Response::macro('apiResponse', function ($content) {
            $apiContentTypeHeaderKey = config('epicollect.setup.api.responseContentTypeHeaderKey');
            $apiContentTypeHeaderValue = config('epicollect.setup.api.responseContentTypeHeaderValue');
            return response()
                ->json([
                    'data' => $content
                ])
                ->header(
                    $apiContentTypeHeaderKey, $apiContentTypeHeaderValue
                );
        });

        Response::macro('attachment', function ($content, $filename) {
            $headers = [
                'Content-type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
            return Response::make($content, 200, $headers);
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}

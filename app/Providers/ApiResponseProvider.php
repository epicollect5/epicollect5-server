<?php

namespace ec5\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;

class ApiResponseProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
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
                    $apiContentTypeHeaderKey,
                    $apiContentTypeHeaderValue
                );
        });
    }
}

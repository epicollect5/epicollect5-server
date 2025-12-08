<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ApiSuccessCodeMacro extends ServiceProvider
{
    public function boot(): void
    {
        Response::macro('apiSuccessCode', function ($code) {
            $apiContentTypeHeaderKey = config('epicollect.setup.api.responseContentTypeHeaderKey');
            $apiContentTypeHeaderValue = config('epicollect.setup.api.responseContentTypeHeaderValue');
            return response()
                ->json([
                    'data' => [
                        'code' => $code,
                        'title' => config('epicollect.codes.' . $code)
                    ]
                ])
                ->header(
                    $apiContentTypeHeaderKey,
                    $apiContentTypeHeaderValue
                );
        });
    }
}

<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ApiDataMacro extends ServiceProvider
{
    public function boot(): void
    {
        Response::macro('apiData', function ($data, $meta = null, $links = null) {
            $apiContentTypeHeaderKey = config('epicollect.setup.api.responseContentTypeHeaderKey');
            $apiContentTypeHeaderValue = config('epicollect.setup.api.responseContentTypeHeaderValue');

            $content = [];
            if ($meta) {
                $content['meta'] = $meta;
            }
            $content['data'] = $data;
            if ($links) {
                $content['links'] = $links;
            }

            return new JsonResponse(
                $content,
                200,
                [$apiContentTypeHeaderKey => $apiContentTypeHeaderValue],
                JSON_UNESCAPED_SLASHES
            );
        });
    }
}

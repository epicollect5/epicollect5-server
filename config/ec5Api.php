<?php


return [
    'responseContentTypeHeaderKey' => 'Content-Type',
    'responseContentTypeHeaderValue' => 'application/vnd.api+json; charset=utf-8',
    'response_delay' => [
        'media' => env('RESPONSE_DELAY_MEDIA_REQUEST', 250000000),
        'upload' => env('RESPONSE_DELAY_UPLOAD_REQUEST', 500000000)
    ]
];

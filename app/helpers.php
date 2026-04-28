<?php

use Illuminate\Support\Str;

if (! function_exists('static_asset')) {
    function static_asset(string $path): string
    {
        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        $driver = config('epicollect.setup.static_assets.driver');
        $cdnEndpoint = config('epicollect.setup.static_assets.cdn_endpoint');

        if ($driver === 'cdn' && filled($cdnEndpoint)) {
            return rtrim($cdnEndpoint, '/') . '/' . ltrim($path, '/');
        }

        return asset($path);
    }
}

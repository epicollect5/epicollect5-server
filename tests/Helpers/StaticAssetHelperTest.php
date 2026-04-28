<?php

namespace Tests\Helpers;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StaticAssetHelperTest extends TestCase
{
    public function test_static_asset_returns_local_asset_url_when_driver_is_local(): void
    {
        Config::set('epicollect.setup.static_assets.driver', 'local');
        Config::set('epicollect.setup.static_assets.cdn_endpoint', 'https://cdn.example.com');

        $path = '/images/brand.png';

        $this->assertSame(asset($path), static_asset($path));
    }

    public function test_static_asset_returns_cdn_url_when_driver_is_cdn_and_endpoint_is_set(): void
    {
        Config::set('epicollect.setup.static_assets.driver', 'cdn');
        Config::set('epicollect.setup.static_assets.cdn_endpoint', 'https://cdn.example.com/static/');

        $this->assertSame(
            'https://cdn.example.com/static/images/brand.png',
            static_asset('/images/brand.png')
        );
    }

    public function test_static_asset_falls_back_to_local_asset_when_cdn_endpoint_is_missing(): void
    {
        Config::set('epicollect.setup.static_assets.driver', 'cdn');
        Config::set('epicollect.setup.static_assets.cdn_endpoint', null);

        $path = '/images/brand.png';

        $this->assertSame(asset($path), static_asset($path));
    }
}

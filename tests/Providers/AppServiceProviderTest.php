<?php

namespace Tests\Providers;

use Illuminate\Http\Request;
use ReflectionClass;
use Tests\TestCase;
use ec5\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AppServiceProviderTest extends TestCase
{
    /**
     * Helper to get the private production bucket constant via reflection.
     */
    private function getProductionBucket(): string
    {
        $reflection = new ReflectionClass(AppServiceProvider::class);
        return $reflection->getConstant('EPICOLLECT_MEDIA_BUCKET_PRODUCTION');
    }

    /**
     * Helper to bind a fake request host and set bucket/config, then boot provider.
     * @throws Throwable
     */
    private function bootProvider(string $host, string $bucket, string $driver = 's3'): void
    {
        // Mock request host
        $this->app->instance('request', Request::create("https://$host", 'GET'));

        // Mock config values
        Config::set('filesystems.disks.s3.bucket', $bucket);
        Config::set('epicollect.setup.system.storage_driver', $driver);

        $provider = new AppServiceProvider($this->app);
        $provider->boot();
    }

    /**
     * @throws Throwable
     */
    public function test_production_bucket_on_production_host_passes()
    {
        $host = 'five.epicollect.net';
        $bucket = $this->getProductionBucket();

        // Should not throw
        $this->bootProvider($host, $bucket);

        $this->assertTrue(true);
    }

    /**
     * @throws Throwable
     */
    public function test_production_bucket_on_non_production_host_throws()
    {
        $host = 'dev.epicollect.net';
        $bucket = $this->getProductionBucket();

        $this->expectException(HttpException::class);
        $this->bootProvider($host, $bucket);
    }

    /**
     * @throws Throwable
     */
    public function test_non_production_bucket_on_non_production_host_passes()
    {
        $host = 'localhost.staging';
        $bucket = 'epicollect5-media-bucket-staging';

        // Should not throw
        $this->bootProvider($host, $bucket);

        $this->assertTrue(true);
    }

    /**
     * @throws Throwable
     */
    public function test_non_s3_storage_driver_skips_bucket_check()
    {
        $host = 'staging.local';
        $bucket = $this->getProductionBucket();

        // Using 'local' driver instead of 's3'
        $this->bootProvider($host, $bucket, 'local');

        $this->assertTrue(true);
    }
    /**
     * @throws Throwable
     */
    public function test_non_s3_storage_driver_skips_bucket_check_production()
    {
        $host = 'five.epicollect.net';
        $bucket = $this->getProductionBucket();

        // Using 'local' driver instead of 's3'
        $this->bootProvider($host, $bucket, 'local');

        $this->assertTrue(true);
    }
    /**
     * @throws Throwable
     */
    public function test_production_bucket_with_spoofed_domain_suffix_throws()
    {
        $host = 'five.epicollect.net.attacker.com';
        $bucket = $this->getProductionBucket();

        $this->expectException(HttpException::class);
        $this->bootProvider($host, $bucket);
    }

    /**
     * @throws Throwable
     */
    public function test_production_bucket_with_spoofed_domain_prefix_throws()
    {
        $host = 'malicious-five.epicollect.net';
        $bucket = $this->getProductionBucket();

        $this->expectException(HttpException::class);
        $this->bootProvider($host, $bucket);
    }

    /**
     * @throws Throwable
     */
    public function test_production_bucket_on_subdomain_behavior()
    {
        $host = 'api.five.epicollect.net';
        $bucket = $this->getProductionBucket();

        $this->expectException(HttpException::class);
        $this->bootProvider($host, $bucket);
    }
}

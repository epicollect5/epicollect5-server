<?php

namespace Tests\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SystemHotfixCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = storage_path('framework/testing/hotfix-' . bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_it_rejects_invalid_target(): void
    {
        $this->artisan('system:hotfix', ['target' => 'bad'])
            ->expectsOutputToContain('Accepted targets: pwa|fb|dw')
            ->assertExitCode(1);
    }

    public function test_download_mode_requires_env_url(): void
    {
        config()->set('app.env', 'testing');
        putenv('HOTFIX_PWA_URL=');

        $this->artisan('system:hotfix', ['target' => 'pwa', '--release' => '1.0.0'])
            ->expectsOutputToContain('Missing required env var HOTFIX_PWA_URL')
            ->assertExitCode(1);
    }

    public function test_dry_run_download_does_not_modify_destination(): void
    {
        $dest = $this->tempDir . '/public/pwa';
        File::ensureDirectoryExists($dest . '/assets');
        File::ensureDirectoryExists($dest . '/.vite');
        File::put($dest . '/.vite/manifest.json', '{"old":true}');
        File::put($dest . '/assets/old.js', 'old');

        $archivePath = $this->makeArchive('pwa', '99.0.0', [
            '.vite/manifest.json' => '{"new":true}',
            'assets/new.js' => 'new',
        ]);

        $url = 'https://example.test/pwa-99.0.0.tar.gz';
        Http::fake([
            $url => Http::response(File::get($archivePath), 200),
        ]);
        putenv('HOTFIX_PWA_URL=' . $url);

        $this->app->usePublicPath($this->tempDir . '/public');

        $this->artisan('system:hotfix', ['target' => 'pwa', '--dry-run' => true, '--release' => '99.0.0'])
            ->expectsOutputToContain('Dry run: validation passed')
            ->assertExitCode(0);

        $this->assertSame('{"old":true}', File::get($dest . '/.vite/manifest.json'));
        $this->assertTrue(File::exists($dest . '/assets/old.js'));
        $this->assertFalse(File::exists($dest . '/assets/new.js'));
    }

    public function test_download_stores_archive_and_replaces_target_preserving_releases(): void
    {
        $public = $this->tempDir . '/public';
        $dest = $public . '/pwa';
        File::ensureDirectoryExists($dest . '/assets');
        File::ensureDirectoryExists($dest . '/releases');
        File::ensureDirectoryExists($dest . '/.vite');
        File::put($dest . '/.vite/manifest.json', '{"old":true}');
        File::put($dest . '/assets/old.js', 'old');
        File::put($dest . '/releases/pwa-98.7.0.tar.gz', 'old-archive');

        $archivePath = $this->makeArchive('pwa', '99.0.0', [
            '.vite/manifest.json' => '{"new":true}',
            'assets/new.js' => 'new',
        ]);

        $url = 'https://example.test/pwa-99.0.0.tar.gz';
        Http::fake([$url => Http::response(File::get($archivePath), 200)]);
        putenv('HOTFIX_PWA_URL=' . $url);
        $this->app->usePublicPath($public);

        $this->artisan('system:hotfix', ['target' => 'pwa', '--release' => '99.0.0'])
            ->expectsOutputToContain('Hotfix applied successfully')
            ->assertExitCode(0);

        $this->assertSame('{"new":true}', File::get($dest . '/.vite/manifest.json'));
        $this->assertTrue(File::exists($dest . '/assets/new.js'));
        $this->assertFalse(File::exists($dest . '/assets/old.js'));
        $this->assertTrue(File::exists($dest . '/releases/pwa-98.7.0.tar.gz'));
        $this->assertTrue(File::exists($dest . '/releases/pwa-99.0.0.tar.gz'));
    }

    public function test_rollback_uses_local_release_archive(): void
    {
        $public = $this->tempDir . '/public';
        $dest = $public . '/pwa';
        File::ensureDirectoryExists($dest . '/assets');
        File::ensureDirectoryExists($dest . '/releases');
        File::ensureDirectoryExists($dest . '/.vite');
        File::put($dest . '/.vite/manifest.json', '{"current":true}');
        File::put($dest . '/assets/current.js', 'current');

        $rollbackArchive = $this->makeArchive('pwa', '98.7.0', [
            '.vite/manifest.json' => '{"rollback":true}',
            'assets/rollback.js' => 'rollback',
        ]);
        File::copy($rollbackArchive, $dest . '/releases/pwa-98.7.0.tar.gz');

        $this->app->usePublicPath($public);

        $this->artisan('system:hotfix', ['target' => 'pwa', '--rollback' => '98.7.0'])
            ->expectsOutputToContain('Rollback applied successfully')
            ->assertExitCode(0);

        $this->assertSame('{"rollback":true}', File::get($dest . '/.vite/manifest.json'));
        $this->assertTrue(File::exists($dest . '/assets/rollback.js'));
        $this->assertTrue(File::exists($dest . '/releases/pwa-98.7.0.tar.gz'));
    }

    public function test_rollback_fails_when_archive_missing(): void
    {
        $public = $this->tempDir . '/public';
        File::ensureDirectoryExists($public . '/pwa/releases');
        $this->app->usePublicPath($public);

        $this->artisan('system:hotfix', ['target' => 'pwa', '--rollback' => '9.9.9'])
            ->expectsOutputToContain('Rollback archive not found')
            ->assertExitCode(1);
    }

    public function test_missing_marker_fails(): void
    {
        $public = $this->tempDir . '/public';
        File::ensureDirectoryExists($public . '/pwa');
        $archivePath = $this->makeArchive('pwa', '99.0.0', [
            'assets/new.js' => 'new',
        ]);
        $url = 'https://example.test/pwa-99.0.0.tar.gz';
        Http::fake([$url => Http::response(File::get($archivePath), 200)]);
        putenv('HOTFIX_PWA_URL=' . $url);
        $this->app->usePublicPath($public);

        $this->artisan('system:hotfix', ['target' => 'pwa', '--release' => '99.0.0'])
            ->expectsOutputToContain('Required marker file missing')
            ->assertExitCode(1);
    }

    /**
     * @param array<string, string> $files
     */
    private function makeArchive(string $prefix, string $version, array $files): string
    {
        $root = $this->tempDir . '/build-' . $prefix . '-' . $version . '-' . bin2hex(random_bytes(3));
        File::ensureDirectoryExists($root);
        foreach ($files as $path => $content) {
            $full = $root . '/' . $path;
            File::ensureDirectoryExists(dirname($full));
            File::put($full, $content);
        }

        $tarPath = $this->tempDir . '/' . $prefix . '-' . $version . '.tar';
        $gzPath = $tarPath . '.gz';

        $phar = new \PharData($tarPath);
        $phar->buildFromDirectory($root);

        $gz = gzopen($gzPath, 'wb9');
        $in = fopen($tarPath, 'rb');
        while (!feof($in)) {
            gzwrite($gz, fread($in, 1024 * 1024));
        }
        fclose($in);
        gzclose($gz);
        @unlink($tarPath);

        return $gzPath;
    }
}

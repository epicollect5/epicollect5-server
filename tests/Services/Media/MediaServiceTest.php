<?php

namespace Tests\Services\Media;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use ec5\DTO\ProjectDefinitionDTO;
use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectExtraDTO;
use ec5\DTO\ProjectMappingDTO;
use ec5\DTO\ProjectStatsDTO;
use ec5\Libraries\Utilities\Generators;
use ec5\Services\Mapping\ProjectMappingService;
use ec5\Services\Media\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class MediaServiceTest extends TestCase
{
    private MediaService $mediaService;
    private ProjectDTO $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mediaService = new MediaService();

        $this->project = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
        $this->project->ref = Generators::projectRef();

        Storage::fake('photo');
        Storage::fake('audio');
        Storage::fake('projects');
    }

    public function test_it_serves_placeholder_when_name_is_missing()
    {
        $response = $this->mediaService->serveLocalFile(
            config('epicollect.strings.inputs_type.photo'),
            'entry_original',
            $this->project->ref,
            null,
        );

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->headers->has('Content-Type'));
    }

    public function test_it_returns_error_for_invalid_format()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mediaService->serveLocalFile(
            'photo',
            'invalid_format',
            $this->project->ref,
            'file.jpg'
        );
    }

    public function test_it_redirects_s3_export_entry_original_to_temporary_url_when_enabled()
    {
        config()->set('filesystems.default', 's3');
        config()->set('epicollect.setup.api.export_media_s3_redirect_enabled', true);
        config()->set('epicollect.setup.api.export_media_s3_redirect_ttl_entry_original', 10);

        $disk = Mockery::mock();
        $disk->shouldReceive('exists')
            ->once()
            ->with($this->project->ref . '/file.jpg')
            ->andReturn(true);
        $disk->shouldReceive('temporaryUrl')
            ->once()
            ->withArgs(function ($path, $expiresAt) {
                return $path === $this->project->ref . '/file.jpg'
                    && $expiresAt instanceof DateTimeInterface
                    && abs(
                        $expiresAt->getTimestamp() - Carbon::now()->addMinutes(10)->getTimestamp()
                    ) <= 5;
            })
            ->andReturn('https://example.com/signed-url');

        Storage::shouldReceive('disk')
            ->twice()
            ->with('photo')
            ->andReturn($disk);

        $response = $this->mediaService->serve([
            'type' => config('epicollect.strings.inputs_type.photo'),
            'format' => config('epicollect.strings.media_formats.entry_original'),
            'name' => 'file.jpg',
        ], $this->project, true);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://example.com/signed-url', $response->getTargetUrl());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_it_serves_local_photo_with_24h_cache_when_version_is_missing()
    {
        $this->app->instance('request', Request::create('/'));

        $filename = 'test-photo.jpg';
        Storage::disk('photo')->put(
            $this->project->ref . '/' . $filename,
            'image-bytes'
        );

        $response = $this->mediaService->serveLocalFile(
            config('epicollect.strings.inputs_type.photo'),
            'entry_original',
            $this->project->ref,
            $filename
        );

        $this->assertEquals(200, $response->status());
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=86400', $cacheControl);
    }

    public function test_it_serves_local_photo_with_immutable_cache_when_version_is_present()
    {
        $request = Request::create('/', 'GET', [
            'v' => '1234567890',
        ]);
        $this->app->instance('request', $request);

        $filename = 'test-photo.jpg';
        Storage::disk('photo')->put(
            $this->project->ref . '/' . $filename,
            'image-bytes'
        );

        $response = $this->mediaService->serveLocalFile(
            config('epicollect.strings.inputs_type.photo'),
            'entry_original',
            $this->project->ref,
            $filename
        );

        $this->assertEquals(200, $response->status());
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('immutable', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
    }
}

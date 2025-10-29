<?php

namespace Tests\Providers\Macro;

use ec5\Libraries\Utilities\Generators;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ToProjectMobileLogoS3MacroTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function test_returns_project_mobile_logo_webp_as_jpeg_from_s3_when_available()
    {
        $projectRef = Generators::projectRef();
        $filename = config('epicollect.media.project_avatar.filename');
        $fakeProjectLogo = 'FAKE_PROJECT_LOGO_BYTES';
        $fakeProjectMobileLogo = 'FAKE_PROJECT_MOBILE_LOGO_BYTES';

        $photoRenderer = Mockery::mock('ec5\Services\Media\PhotoRendererService');
        $photoRenderer->shouldReceive('resolvePhotoPath')
            ->once()
            ->andReturn("$projectRef/$filename");

        $photoRenderer->shouldReceive('getAsJpeg')
            ->once()
            ->andReturn($fakeProjectLogo);

        $photoRenderer->shouldReceive('createThumbnail')
            ->with(
                $fakeProjectLogo,
                config('epicollect.media.project_mobile_logo')[0],
                config('epicollect.media.project_mobile_logo')[1],
                config('epicollect.media.quality.jpg')
            )
            ->once()
            ->andReturn($fakeProjectMobileLogo);

        $this->app->instance('ec5\Services\Media\PhotoRendererService', $photoRenderer);

        $fakeDisk = Mockery::mock(Filesystem::class);
        Storage::shouldReceive('disk')
            ->with('project')
            ->andReturn($fakeDisk);


        $response = Response::toProjectMobileLogoS3($projectRef, $filename);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertEquals($fakeProjectMobileLogo, $response->getContent());
    }

    public function test_returns_project_mobile_logo_legacy_jpeg_from_s3_when_available()
    {
        $projectRef = Generators::projectRef();
        $filename = config('epicollect.media.project_avatar.filename');
        //replace webp with jpg
        $filename = str_replace('.webp', '.jpg', $filename);

        $fakeProjectLogo = 'FAKE_PROJECT_LOGO_BYTES';
        $fakeProjectMobileLogo = 'FAKE_PROJECT_MOBILE_LOGO_BYTES';

        $photoRenderer = Mockery::mock('ec5\Services\Media\PhotoRendererService');
        $photoRenderer->shouldReceive('resolvePhotoPath')
            ->once()
            ->andReturn("$projectRef/$filename");

        $photoRenderer->shouldReceive('getAsJpeg')
            ->once()
            ->andReturn($fakeProjectLogo);

        $photoRenderer->shouldReceive('createThumbnail')
            ->with(
                $fakeProjectLogo,
                config('epicollect.media.project_mobile_logo')[0],
                config('epicollect.media.project_mobile_logo')[1],
                config('epicollect.media.quality.jpg')
            )
            ->once()
            ->andReturn($fakeProjectMobileLogo);

        $this->app->instance('ec5\Services\Media\PhotoRendererService', $photoRenderer);

        $fakeDisk = Mockery::mock(Filesystem::class);
        Storage::shouldReceive('disk')
            ->with('project')
            ->andReturn($fakeDisk);


        $response = Response::toProjectMobileLogoS3($projectRef, $filename);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertEquals($fakeProjectMobileLogo, $response->getContent());
    }

    public function test_returns_placeholder_when_file_not_found()
    {
        $projectRef = Generators::projectRef();
        $filename = 'missing.webp';
        $photoPlaceholderFilename = config('epicollect.media.generic_placeholder.filename');
        $photoPlaceholderBytes = file_get_contents(Storage::disk('public')->path($photoPlaceholderFilename));


        // Mock storage
        Storage::shouldReceive('disk')->with('project')->andReturnSelf();
        Storage::shouldReceive('disk')->with('public')->andReturnSelf();
        Storage::shouldReceive('get')->with(config('epicollect.media.generic_placeholder.filename'))->andReturn($photoPlaceholderBytes);

        // Mock PhotoRendererService
        $photoRenderer = Mockery::mock('ec5\Services\Media\PhotoRendererService');
        $photoRenderer->shouldReceive('resolvePhotoPath')->andReturn(false);
        $this->app->instance('ec5\Services\Media\PhotoRendererService', $photoRenderer);

        Log::shouldReceive('error')->once();

        $response = Response::toProjectMobileLogoS3($projectRef, $filename);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
        //assert response is smaller than the original placeholder (mobile logo size is smaller)
        $this->assertLessThan(strlen($photoPlaceholderBytes), strlen($response->getContent()));

        // Check content is not empty
        $this->assertNotEmpty($response->getContent());
    }
}

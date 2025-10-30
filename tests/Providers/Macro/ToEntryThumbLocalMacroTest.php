<?php

namespace Tests\Providers\Macro;

use ec5\Libraries\Utilities\Generators;
use Tests\TestCase;
use Mockery;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response as HttpResponse;

class ToEntryThumbLocalMacroTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_returns_entry_thumb_as_jpeg_when_file_exists()
    {
        $projectRef = 'project123';
        $filename = 'image.jpg';
        $resolvedPath = 'resolved/path/image.jpg';
        $fakeJpeg = 'fake_jpeg_bytes';
        $thumbnailBytes = 'thumbnail_bytes';

        // Mock PhotoRendererService completely
        $mockPhotoService = Mockery::mock()
            ->shouldReceive('resolvePhotoPath')
            ->withAnyArgs()
            ->andReturn($resolvedPath)
            ->shouldReceive('getAsJpeg')
            ->withAnyArgs()
            ->andReturn($fakeJpeg)
            ->shouldReceive('createThumbnail')
            ->withAnyArgs()
            ->andReturn($thumbnailBytes)
            ->getMock();

        $this->app->instance('ec5\Services\Media\PhotoRendererService', $mockPhotoService);

        // Mock Storage disk for 'photo'
        Storage::shouldReceive('disk')
            ->with('photo')
            ->andReturnSelf();

        // Call macro
        $response = Response::toEntryThumbLocal($projectRef, $filename);

        // Assert the macro returns the mocked thumbnail bytes with 200 status
        $this->assertInstanceOf(HttpResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($thumbnailBytes, $response->getContent());
        $this->assertEquals(
            config('epicollect.media.content_type.photo'),
            $response->headers->get('Content-Type')
        );
    }

    public function test_returns_placeholder_when_file_not_found()
    {
        $projectRef = Generators::projectRef();
        $filename = 'missing.jpg';
        $placeholderBytes = 'placeholder_bytes';

        $photoNotSyncedFilename = config('epicollect.media.photo_not_synced_placeholder.filename');

        // Mock PhotoRendererService to return null path to simulate missing file
        $mockPhotoService = Mockery::mock()
            ->shouldReceive('resolvePhotoPath')
            ->withAnyArgs()
            ->andReturn(null)
            ->getMock();

        $this->app->instance('ec5\Services\Media\PhotoRendererService', $mockPhotoService);

        Storage::shouldReceive('disk')
            ->with('photo')
            ->andReturnSelf();

        // Mock Storage disk for 'public' to return placeholder bytes
        Storage::shouldReceive('disk')
            ->with('public')
            ->andReturnSelf();

        Storage::shouldReceive('get')
            ->with($photoNotSyncedFilename)
            ->andReturn($placeholderBytes);

        // Call macro
        $response = Response::toEntryThumbLocal($projectRef, $filename);

        // Assert it returns placeholder bytes with 200 status
        $this->assertInstanceOf(HttpResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($placeholderBytes, $response->getContent());
        $this->assertEquals(config('epicollect.media.content_type.photo'), $response->headers->get('Content-Type'));
    }

    public function test_returns_generic_placeholder_when_filename_empty_or_avatar()
    {
        // Arrange
        $photoPlaceholderFilename = config('epicollect.media.generic_placeholder.filename');
        $fileContent = 'fake-image-bytes';

        // Mock Storage for the public disk
        Storage::shouldReceive('disk')
            ->with('public')
            ->andReturnSelf();

        Storage::shouldReceive('get')
            ->with($photoPlaceholderFilename)
            ->andReturn($fileContent);

        // Act - empty filename
        $responseEmpty = Response::toEntryThumbLocal('dummy-project', '');

        // Act - project avatar filename forces generic placeholder
        $avatarFilename = config('epicollect.media.project_avatar.filename');
        $responseAvatar = Response::toEntryThumbLocal('dummy-project', $avatarFilename);

        // Assert
        $this->assertEquals(200, $responseEmpty->getStatusCode());
        $this->assertEquals(config('epicollect.media.content_type.photo'), $responseEmpty->headers->get('Content-Type'));
        $this->assertSame($fileContent, $responseEmpty->getContent());

        $this->assertEquals(200, $responseAvatar->getStatusCode());
        $this->assertEquals(config('epicollect.media.content_type.photo'), $responseAvatar->headers->get('Content-Type'));
        $this->assertSame($fileContent, $responseAvatar->getContent());
    }
}

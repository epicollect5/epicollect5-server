<?php

namespace Tests\Services\Project;

use ec5\Libraries\Utilities\Generators;
use ec5\Services\Project\ProjectLogoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Intervention\Image\Laravel\Facades\Image;
use Storage;
use Tests\TestCase;

class ProjectLogoServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ProjectLogoService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new ProjectLogoService();
        Storage::fake('project');
    }

    public function test_returns_null_for_empty_ref(): void
    {
        $this->assertNull($this->service->generate(''));
    }

    public function test_returns_null_when_file_missing(): void
    {
        $this->assertNull($this->service->generate('non-existent-ref'));
    }

    public function test_returns_data_uri_when_file_exists(): void
    {
        $ref = Generators::projectRef();

        $img = Image::create(100, 100)->fill('#ff0000');
        $jpeg = $img->toJpeg();
        Storage::disk('project')->put($ref . '/logo.jpg', $jpeg);

        $result = $this->service->generate($ref);

        $this->assertNotNull($result);
        $this->assertStringStartsWith('data:image/webp;base64,', $result);

        $decoded = base64_decode(substr($result, 23));
        $decodedImg = Image::read($decoded);
        $this->assertEquals(128, $decodedImg->width());
        $this->assertEquals(128, $decodedImg->height());
    }

    public function test_respects_custom_dimensions(): void
    {
        $ref = Generators::projectRef();

        $img = Image::create(200, 100)->fill('#00ff00');
        $jpeg = $img->toJpeg();
        Storage::disk('project')->put($ref . '/logo.jpg', $jpeg);

        $result = $this->service->generate($ref, 32, 32);

        $this->assertNotNull($result);
        $decoded = base64_decode(substr($result, 23));
        $decodedImg = Image::read($decoded);
        $this->assertEquals(32, $decodedImg->width());
        $this->assertEquals(32, $decodedImg->height());
    }

    public function test_returns_null_when_stream_unreadable(): void
    {
        $ref = Generators::projectRef();

        Storage::disk('project')->put($ref . '/logo.jpg', 'not-an-image');

        $result = $this->service->generate($ref);

        $this->assertNull($result);
    }
}

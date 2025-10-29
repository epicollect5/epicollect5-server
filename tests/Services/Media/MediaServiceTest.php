<?php

namespace Tests\Services\Media;

use ec5\DTO\ProjectDefinitionDTO;
use ec5\DTO\ProjectExtraDTO;
use ec5\DTO\ProjectMappingDTO;
use ec5\DTO\ProjectStatsDTO;
use ec5\Libraries\Utilities\Generators;
use ec5\Services\Mapping\ProjectMappingService;
use ec5\Services\Media\MediaService;
use ec5\DTO\ProjectDTO;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class MediaServiceTest extends TestCase
{
    private MediaService $mediaService;
    private ProjectDTO $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mediaService = new MediaService(
            app('ec5\Services\Media\PhotoRendererService')
        );

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
}

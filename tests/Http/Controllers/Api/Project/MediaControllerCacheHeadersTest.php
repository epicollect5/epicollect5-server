<?php

namespace Tests\Http\Controllers\Api\Project;

use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Image;
use Intervention\Image\Drivers\Imagick\Encoders\JpegEncoder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MediaControllerCacheHeadersTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private User $user;
    private Project $project;

    public function setUp(): void
    {
        parent::setUp();

        $user = factory(User::class)->create();
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'name' => array_get($projectDefinition, 'data.project.name'),
            'slug' => array_get($projectDefinition, 'data.project.slug'),
            'ref' => array_get($projectDefinition, 'data.project.ref'),
            'access' => config('epicollect.strings.project_access.public')
        ]);
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);
        factory(ProjectStructure::class)->create([
            'project_id' => $project->id,
            'project_definition' => json_encode($projectDefinition['data'])
        ]);
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0
        ]);

        $this->user = $user;
        $this->project = $project;
        $this->overrideStorageDriver('local');
    }

    #[DataProvider('multipleRunProvider')]
    public function test_audio_file_has_no_cache_directive()
    {
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.mp4';
        Storage::disk('audio')->put($this->project->ref . '/' . $filename, str_repeat('a', 1000000));

        $response = $this->withHeaders(['Range' => 'bytes=0-10'])->get('api/internal/media/' . $this->project->slug . '?type=audio&name=' . $filename . '&format=audio');

        $response->assertStatus(206);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.audio'));
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);

        Storage::disk('audio')->deleteDirectory($this->project->ref);
    }

    #[DataProvider('multipleRunProvider')]
    public function test_video_file_has_no_cache_directive()
    {
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.mp4';
        Storage::disk('video')->put($this->project->ref . '/' . $filename, str_repeat('a', 1000000));

        $response = $this->withHeaders(['Range' => 'bytes=0-10'])->get('api/internal/media/' . $this->project->slug . '?type=video&name=' . $filename . '&format=video');

        $response->assertStatus(206);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.video'));
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);

        Storage::disk('video')->deleteDirectory($this->project->ref);
    }

    #[DataProvider('multipleRunProvider')]
    public function test_entry_original_without_v_param_has_24h_cache_directive()
    {
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.jpg';
        $image = Image::create(config('epicollect.media.entry_original_landscape')[0], config('epicollect.media.entry_original_landscape')[1]);
        $imageData = (string)$image->encode(new JpegEncoder(50));
        Storage::disk('photo')->put($this->project->ref . '/' . $filename, $imageData);

        $response = $this->get('api/internal/media/' . $this->project->slug . '?type=photo&name=' . $filename . '&format=entry_original');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=86400', $cacheControl);

        Storage::disk('photo')->deleteDirectory($this->project->ref);
    }

    #[DataProvider('multipleRunProvider')]
    public function test_entry_original_with_v_param_has_immutable_directive()
    {
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.jpg';
        $image = Image::create(config('epicollect.media.entry_original_landscape')[0], config('epicollect.media.entry_original_landscape')[1]);
        $imageData = (string)$image->encode(new JpegEncoder(50));
        Storage::disk('photo')->put($this->project->ref . '/' . $filename, $imageData);

        $response = $this->get('api/internal/media/' . $this->project->slug . '?type=photo&name=' . $filename . '&format=entry_original&v=1234567890');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('immutable', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);

        Storage::disk('photo')->deleteDirectory($this->project->ref);
    }

    #[DataProvider('multipleRunProvider')]
    public function test_entry_thumb_without_v_param_has_24h_cache_directive()
    {
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.jpg';
        $image = Image::create(config('epicollect.media.entry_original_landscape')[0], config('epicollect.media.entry_original_landscape')[1]);
        $imageData = (string)$image->encode(new JpegEncoder(50));
        Storage::disk('photo')->put($this->project->ref . '/' . $filename, $imageData);

        $response = $this->get('api/internal/media/' . $this->project->slug . '?type=photo&name=' . $filename . '&format=entry_thumb');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=86400', $cacheControl);

        Storage::disk('photo')->deleteDirectory($this->project->ref);
    }

    #[DataProvider('multipleRunProvider')]
    public function test_entry_thumb_with_v_param_has_immutable_directive()
    {
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.jpg';
        $image = Image::create(config('epicollect.media.entry_original_landscape')[0], config('epicollect.media.entry_original_landscape')[1]);
        $imageData = (string)$image->encode(new JpegEncoder(50));
        Storage::disk('photo')->put($this->project->ref . '/' . $filename, $imageData);

        $response = $this->get('api/internal/media/' . $this->project->slug . '?type=photo&name=' . $filename . '&format=entry_thumb&v=1234567890');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('immutable', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);

        Storage::disk('photo')->deleteDirectory($this->project->ref);
    }

    #[DataProvider('multipleRunProvider')]
    public function test_project_thumb_without_v_param_has_24h_cache_directive()
    {
        $image = Image::create(config('epicollect.media.project_thumb')[0], config('epicollect.media.project_thumb')[1])->fill('#673C90');
        $imageData = $image->toJpeg(70);
        Storage::disk('project')->put($this->project->ref . '/logo.jpg', $imageData);

        $response = $this->get('api/internal/media/' . $this->project->slug . '?type=photo&name=logo.jpg&format=project_thumb');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=86400', $cacheControl);
    }

    #[DataProvider('multipleRunProvider')]
    public function test_project_thumb_with_v_param_has_immutable_directive()
    {
        $image = Image::create(config('epicollect.media.project_thumb')[0], config('epicollect.media.project_thumb')[1])->fill('#673C90');
        $imageData = $image->toJpeg(70);
        Storage::disk('project')->put($this->project->ref . '/logo.jpg', $imageData);

        $response = $this->get('api/internal/media/' . $this->project->slug . '?type=photo&name=logo.jpg&format=project_thumb&v=1234567890');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('immutable', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
    }

    #[DataProvider('multipleRunProvider')]
    public function test_project_mobile_logo_without_v_param_has_24h_cache_directive()
    {
        $image = Image::create(config('epicollect.media.project_thumb')[0], config('epicollect.media.project_thumb')[1])->fill('#673C90');
        $imageData = $image->toJpeg(70);
        Storage::disk('project')->put($this->project->ref . '/logo.jpg', $imageData);

        $response = $this->get('api/internal/media/' . $this->project->slug . '?type=photo&name=logo.jpg&format=project_mobile_logo');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=86400', $cacheControl);
    }

    #[DataProvider('multipleRunProvider')]
    public function test_project_mobile_logo_with_v_param_has_immutable_directive()
    {
        $image = Image::create(config('epicollect.media.project_thumb')[0], config('epicollect.media.project_thumb')[1])->fill('#673C90');
        $imageData = $image->toJpeg(70);
        Storage::disk('project')->put($this->project->ref . '/logo.jpg', $imageData);

        $response = $this->get('api/internal/media/' . $this->project->slug . '?type=photo&name=logo.jpg&format=project_mobile_logo&v=1234567890');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('immutable', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
    }
}

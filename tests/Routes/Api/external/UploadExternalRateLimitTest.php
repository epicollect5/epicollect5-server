<?php

namespace Tests\Routes\Api\external;

use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UploadExternalRateLimitTest extends TestCase
{
    use DatabaseTransactions;

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

        factory(ProjectStructure::class)->create([
            'project_id' => $project->id,
            'project_definition' => json_encode($projectDefinition['data'])
        ]);

        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0
        ]);

        $this->project = $project;
    }

    #[DataProvider('uploadRouteProvider')]
    public function test_external_upload_routes_share_mobile_upload_rate_limiter(
        string $firstRoute,
        string $secondRoute
    ): void {
        config()->set('epicollect.setup.api.rate_limit_per_minute.mobile_upload', 1);

        $headers = ['REMOTE_ADDR' => '10.0.0.' . random_int(2, 254)];

        $this->json('POST', $firstRoute . $this->project->slug, [], $headers)
            ->assertStatus(400);

        $response = $this->json('POST', $secondRoute . $this->project->slug, [], $headers);

        $response->assertStatus(429);
        $this->assertEquals('Too Many Attempts.', $response->exception->getMessage());
        $this->assertEquals(429, $response->exception->getStatusCode());
    }

    public static function uploadRouteProvider(): array
    {
        return [
            'new then legacy' => ['api/upload/', 'api/json/upload/'],
            'legacy then new' => ['api/json/upload/', 'api/upload/'],
        ];
    }
}

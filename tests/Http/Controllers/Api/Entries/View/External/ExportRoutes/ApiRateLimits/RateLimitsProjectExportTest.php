<?php

namespace Tests\Http\Controllers\Api\Entries\View\External\ExportRoutes\ApiRateLimits;

use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Models\Entries\Entry;
use ec5\Models\OAuth\OAuthClientProject;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Services\Mapping\ProjectMappingService;
use ec5\Services\Project\ProjectExtraService;
use ec5\Traits\Assertions;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;
use Throwable;

/**
 * This approach isn't ideal from a "best practices" standpoint, as it involves making multiple requests to the framework
 * in the same test, which is generally discouraged. The framework does not fully reinitialize between requests in
 * the testing environment, unlike how it behaves in the real world.
 *
 * Additionally, we are relying on the rate limit to reset naturally by introducing a delay. Ideally, we would mock or
 * programmatically reset the rate limiter to avoid waiting for the reset.
 *
 * While this approach works, it is not the most efficient or elegant solution. It serves the purpose of these tests
 * but is not recommended for more complex or performance-sensitive scenarios.
 */
class RateLimitsProjectExportTest extends TestCase
{
    use Assertions;

    private User $user;
    private Project $project;
    private array $projectDefinition;
    private string $slug;
    private string $email;

    public function setUp(): void
    {
        parent::setUp();

        $name = config('testing.API_RATE_LIMITS_PROJECT.name');
        $this->slug = config('testing.API_RATE_LIMITS_PROJECT.slug');
        $this->email = config('testing.UNIT_TEST_RANDOM_EMAIL');

        $this->clearDatabase(
            [
                'user' => User::where('email', $this->email)->first(),
                'project' => Project::where('slug', $this->slug)->first()
            ]
        );

        //create fake user for testing
        $this->user = factory(User::class)->create(
            [
                'email' => config('testing.UNIT_TEST_RANDOM_EMAIL')
            ]
        );

        //create a project with custom project definition
        $this->projectDefinition = ProjectDefinitionGenerator::createProject(1);
        $this->projectDefinition['data']['project']['name'] = $name;
        $this->projectDefinition['data']['project']['slug'] = $this->slug;
        $this->project = factory(Project::class)->create(
            [
                'created_by' => $this->user->id,
                'name' => array_get($this->projectDefinition, 'data.project.name'),
                'slug' => array_get($this->projectDefinition, 'data.project.slug'),
                'ref' => array_get($this->projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.public')
            ]
        );
        //add role
        factory(ProjectRole::class)->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //create project structures
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);
        $projectMappingService = new ProjectMappingService();
        $projectMapping = [$projectMappingService->createEC5AUTOMapping($projectExtra)];

        factory(ProjectStructure::class)->create(
            [
                'project_id' => $this->project->id,
                'project_definition' => json_encode($this->projectDefinition['data']),
                'project_extra' => json_encode($projectExtra),
                'project_mapping' => json_encode($projectMapping)
            ]
        );
        factory(ProjectStats::class)->create(
            [
                'project_id' => $this->project->id,
                'total_entries' => 0
            ]
        );

        //add the project and client
        $clientRepository = new ClientRepository();
        $client = $clientRepository->create(
            $this->user->id,
            'Test App',
            ''
        )->makeVisible('secret');

        factory(OAuthClientProject::class)->create([
            'project_id' => $this->project->id,
            'client_id' => $client->id
        ]);
    }

    /**
     * @throws Throwable
     */
    public function test_rate_limit_api_project()
    {
        $apiProjectRateLimit = config('epicollect.limits.api_export.project');

        //generate entries
        $entryGenerator = new EntryGenerator($this->projectDefinition);

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        $numOfEntries = $apiProjectRateLimit + 10;
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $entryGenerator->createParentEntryPayload($formRef);


            $entryRowBundle = $entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                config('epicollect.strings.project_roles.creator'),
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert rows are created
        $this->assertCount(
            $numOfEntries,
            Entry::where('project_id', $this->project->id)->get()
        );

        $entriesURL = config('testing.LOCAL_SERVER') . '/api/export/project/';
        $entriesClient = new Client([
            'headers' => [
                //imp: without this, does not work
                'Content-Type' => 'application/vnd.api+json'
            ]
        ]);

        $this->travel(210);
        $successfulRequests = 0;
        try {
            for ($i = 1; $i <= $apiProjectRateLimit; $i++) {
                $response = $entriesClient->request('GET', $entriesURL . $this->project->slug);
                // Verify we get a successful response
                $this->assertEquals(200, $response->getStatusCode());
                $successfulRequests++;
            }

            $this->assertEquals($apiProjectRateLimit, $successfulRequests);

            //when the limit is hit, should return status 429
            try {
                $entriesClient->request('GET', $entriesURL . $this->project->slug);
                // Optionally, check response for other status codes or data
            } catch (Throwable $e) {
                if ($e->hasResponse()) {
                    $response = $e->getResponse();
                    // Assert the status code
                    $this->assertEquals(429, $response->getStatusCode());
                }
            }
        } catch (GuzzleException $e) {
            $this->logTestError($e, []);
            return false;
        } finally {
            $this->travelBack();
            $this->cleanUp();
        }
        return true;
    }

    private function cleanUp()
    {
        //clean up
        Storage::disk('video')->deleteDirectory($this->project->ref);

        $this->clearDatabase(
            [
                'user' => User::where('email', $this->email)->first(),
                'project' => Project::where('slug', $this->slug)->first()
            ]
        );
    }
}

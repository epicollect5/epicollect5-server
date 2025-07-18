<?php

namespace Tests\Http\Controllers\Api\Entries\View\External\ExportRoutes\ApiRateLimits;

use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\MediaGenerator;
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
class RateLimitsMediaExportTest extends TestCase
{
    use Assertions;

    private User $user;
    private Project $project;
    private array $projectDefinition;
    private String $slug;
    private String $email;

    public function setUp(): void
    {
        parent::setUp();
        //to clear limits counter, wait 1 minute and 1 second

        $name = config('testing.API_RATE_LIMITS_MEDIA.name');
        $this->slug = config('testing.API_RATE_LIMITS_MEDIA.slug');
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
    public function test_rate_limit_api_media()
    {
        // Get the configured per-minute rate limit for media exports
        $apiMediaRateLimit = config('epicollect.setup.api.rate_limit_per_minute.media');

        $entryGenerator = new EntryGenerator($this->projectDefinition);

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        // Extract the refs of all video-type inputs for use in entries
        $videoRefs = array_values(array_map(function ($input) {
            return $input['ref'];
        }, array_filter($inputs, function ($input) {
            return $input['type'] === config('epicollect.strings.inputs_type.video');
        })));

        $entryPayloads = [];
        $videoAnswers = [];

        // Generate more entries than the allowed rate limit to test the overflow
        $numOfEntries = $apiMediaRateLimit + 10;

        for ($i = 0; $i < $numOfEntries; $i++) {
            // Create entry payload
            $entryPayloads[$i] = $entryGenerator->createParentEntryPayload($formRef);

            // Extract the video answer reference for media testing
            $videoAnswers[] = $entryPayloads[$i]['data']['entry']['answers'][$videoRefs[0]];

            // Persist entry row to database
            $entryRowBundle = $entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                config('epicollect.strings.project_roles.creator'),
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            // Validate entry row content against payload
            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );

            // Create a fake video file associated with the entry
            $filename = $videoAnswers[$i]['answer'];
            $videoContent = MediaGenerator::getFakeVideoContentBase64();
            Storage::disk('video')->put($this->project->ref . '/' . $filename, $videoContent);
        }

        // Confirm entries are stored in the database
        $this->assertCount(
            $numOfEntries,
            Entry::where('project_id', $this->project->id)->get()
        );

        // Confirm video files are saved on disk
        $videos = Storage::disk('video')->files($this->project->ref);
        $this->assertCount($numOfEntries, $videos);

        // API endpoint for exporting media
        $entriesURL = config('testing.LOCAL_SERVER') . '/api/export/media/';

        // Guzzle client for simulating real external HTTP requests
        $entriesClient = new Client([
            'headers' => [
                // Override disk for test to simulate real-world storage (e.g., S3)
                'X-Disk-Override' => 's3',
                'Content-Type' => 'application/vnd.api+json'
            ]
        ]);

        // Reset the rate limiter on the Laravel server before making requests
        $this->actingAs($this->user)->post(config('testing.LOCAL_SERVER') . '/test/reset-api-rate-limit/media');
        try {
            // Send up to the limit number of media export requests
            for ($i = 1; $i <= $apiMediaRateLimit; $i++) {
                $filename = $videoAnswers[$i]['answer'];
                $queryString = '?type=video&name=' . $filename . '&format=video' . '&XDEBUG_SESSION_START=phpstorm';

                $response = $entriesClient->request('GET', $entriesURL . $this->project->slug . $queryString);

                $headers = $response->getHeaders();

                // Validate rate limit headers exist and match expected values
                $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
                $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);

                $this->assertEquals($apiMediaRateLimit, $headers['X-RateLimit-Limit'][0]);
                $this->assertEquals($apiMediaRateLimit - $i, $headers['X-RateLimit-Remaining'][0]);
            }

            // One additional request should exceed the rate limit and return a 429 response
            try {
                $filename = $videoAnswers[0]['answer'];
                $queryString = '?type=video&name=' . $filename . '&format=video';
                $entriesClient->request('GET', $entriesURL . $this->project->slug . $queryString);
            } catch (Throwable $e) {
                if ($e->hasResponse()) {
                    $response = $e->getResponse();
                    $this->assertEquals(429, $response->getStatusCode());
                }
            }

            $this->cleanUp();
        } catch (GuzzleException $e) {
            // On exception, log and clean up
            $this->cleanUp();
            $this->logTestError($e, []);
            return false;
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

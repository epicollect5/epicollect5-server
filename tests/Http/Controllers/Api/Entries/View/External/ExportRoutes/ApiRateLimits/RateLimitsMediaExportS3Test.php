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
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Log;
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
class RateLimitsMediaExportS3Test extends TestCase
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

        $this->overrideStorageDriver('s3');
    }

    /**
     * @throws Throwable
     */

    public function test_rate_limit_api_media()
    {
        // Get the configured per-minute rate limit for media exports
        $apiMediaRateLimit = config('epicollect.limits.api_export.media');

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
        $numOfEntries = $apiMediaRateLimit + 5;

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
        $mediaURL = config('testing.LOCAL_SERVER') . '/api/export/media/';

        // Guzzle client for simulating real external HTTP requests
        $entriesClient = new Client([
            'headers' => [
                // Override disk for test to simulate real-world storage (e.g., S3)
                'X-Disk-Override' => 's3',
                'Content-Type' => 'application/vnd.api+json'
            ]
        ]);

        try {
            // Time travel: Jump forward to ensure we start with a clean rate limit window
            // travel() expects seconds, so we travel forward 2 minutes (120 seconds)
            $this->travel(120);

            // Send requests up to the rate limit
            $successfulRequests = 0;
            for ($i = 0; $i < $apiMediaRateLimit; $i++) {
                $filename = $videoAnswers[$i]['answer'];
                $queryString = '?type=video&name=' . $filename . '&format=video';

                try {
                    $response = $entriesClient->request('GET', $mediaURL . $this->project->slug . $queryString);

                    // Verify we get a successful response
                    $this->assertEquals(200, $response->getStatusCode());
                    $successfulRequests++;

                    $headers = $response->getHeaders();

                    // Log headers for debugging
                    Log::info("Request $i headers", $headers);

                    // Validate rate limit headers exist and match expected values
                    $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
                    $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);

                    // Check that remaining count decreases
                    $remaining = (int) $headers['X-RateLimit-Remaining'][0];
                    $expected = $apiMediaRateLimit - $successfulRequests;

                    Log::info("Rate limit check", [
                        'request' => $i,
                        'remaining' => $remaining,
                        'expected' => $expected,
                        'successful_requests' => $successfulRequests
                    ]);

                    $this->assertEquals($expected, $remaining);

                } catch (GuzzleException $e) {
                    $this->cleanUp();
                    $this->fail("Unexpected error on request $i: " . $e->getMessage());
                }
            }

            // Verify we made all allowed requests successfully
            $this->assertEquals($apiMediaRateLimit, $successfulRequests);

            // Now the next request should exceed the rate limit and return 429
            $filename = $videoAnswers[$apiMediaRateLimit]['answer'];
            $queryString = '?type=video&name=' . $filename . '&format=video';

            try {
                $response = $entriesClient->request('GET', $mediaURL . $this->project->slug . $queryString);
                // If we get here, the rate limit wasn't enforced properly
                $this->fail('Expected rate limit to be exceeded, but request succeeded with status: ' . $response->getStatusCode());
            } catch (ClientException $e) {
                if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 429) {
                    // Test passed - we got the expected 429 response
                    $headers = $e->getResponse()->getHeaders();

                    // Log the 429 response headers for debugging
                    Log::info('429 Response headers', $headers);

                    // Some rate limiters may not include headers in 429 responses
                    // Just verify we got the 429 - that's the main goal
                    $this->assertTrue(true, 'Rate limit correctly enforced with 429 response');

                    // Optional: Check headers if they exist
                    if (isset($headers['X-RateLimit-Remaining'])) {
                        $this->assertEquals('0', $headers['X-RateLimit-Remaining'][0]);
                    }
                } else {
                    $this->cleanUp();
                    $this->fail('Expected 429 status code, got: ' . ($e->hasResponse() ? $e->getResponse()->getStatusCode() : 'no response'));
                }
            } catch (GuzzleException $e) {
                $this->cleanUp();
                $this->fail('Unexpected Guzzle exception: ' . $e->getMessage());
            }

            // Test complete - rate limiting is working correctly
            Log::info('Rate limit test completed successfully', [
                'successful_requests' => $successfulRequests,
                'rate_limit_enforced' => true
            ]);

        } finally {
            // Always restore original time and clean up
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

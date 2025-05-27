<?php

namespace Tests\Http\Controllers\Api\Entries\View\External\ExportRoutes;

use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\MediaGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Services\Mapping\ProjectMappingService;
use ec5\Services\Project\ProjectExtraService;
use ec5\Traits\Assertions;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

class MediaExportPrivateVideoDeniedTest extends TestCase
{
    use Assertions;

    /**
     * @throws Throwable
     */
    public function test_videos_export_endpoint_denied_without_token()
    {
        parent::setUp();
        //remove leftovers
        User::where(
            'email',
            'like',
            '%example.net%'
        )
            ->delete();


        //create fake user for testing
        $user = factory(User::class)->create();
        $role = config('epicollect.strings.project_roles.creator');

        //create a project with custom project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'name' => array_get($projectDefinition, 'data.project.name'),
                'slug' => array_get($projectDefinition, 'data.project.slug'),
                'ref' => array_get($projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.private')
            ]
        );
        //add role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //create project structures
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($projectDefinition['data']);
        $projectMappingService = new ProjectMappingService();
        $projectMapping = [$projectMappingService->createEC5AUTOMapping($projectExtra)];


        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data']),
                'project_extra' => json_encode($projectExtra),
                'project_mapping' => json_encode($projectMapping)
            ]
        );
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );

        $entryGenerator = new EntryGenerator($projectDefinition);

        //generate entries
        $formRef = array_get($projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($projectDefinition, 'data.project.forms.0.inputs');

        $videoRefs = array_values(array_map(function ($input) {
            return $input['ref'];
        }, array_filter($inputs, function ($input) {
            return $input['type'] === config('epicollect.strings.inputs_type.audio');
        })));

        $entryPayloads = [];
        $videoAnswers = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $entryGenerator->createParentEntryPayload($formRef);

            $videoAnswers[] = $entryPayloads[0]['data']['entry']['answers'][$videoRefs[0]];

            $entryRowBundle = $entryGenerator->createParentEntryRow(
                $user,
                $project,
                $role,
                $projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //add the fake audio
        $filename = $videoAnswers[0]['answer'];

        //create a fake audio for the entry
        $videoContent = MediaGenerator::getFakeVideoContentBase64();
        Storage::disk('video')->put($project->ref . '/' . $filename, $videoContent);

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $mediaURL = config('testing.LOCAL_SERVER') . '/api/export/media/';
        $queryString = '?type=video&name=' . $filename . '&format=video';

        $response = [];
        try {
            //imp: using Guzzle to impersonate a third-party client using the api_external guard
            $client = new Client(['http_errors' => false]);
            $response[] = $client->get(
                $mediaURL . $project->slug . $queryString,
                [
                    'headers' => [
                        'X-Requested-With' => 'XMLHttpRequest'
                    ]
                ]
            );

            $content = $response[0]->getBody()->getContents();

            // Assert the status code
            $this->assertEquals(404, $response[0]->getStatusCode());
            // Assert the JSON content
            $expectedJson = json_encode([
                'errors' => [
                    [
                        'code' => 'ec5_256',
                        'title' => 'Access denied.',
                        'source' => 'middleware'
                    ]
                ]
            ]);
            $this->assertJsonStringEqualsJsonString($expectedJson, $content);

            Storage::disk('audio')->deleteDirectory($project->ref);
            $this->clearDatabase(
                [
                    'token' => null,
                    'user' => $user,
                    'project' => $project,
                ]
            );
        } catch (Throwable $e) {
            Storage::disk('audio')->deleteDirectory($project->ref);
            $this->clearDatabase(
                [
                    'token' => null,
                    'user' => $user,
                    'project' => $project,
                ]
            );
            $this->logTestError($e, $response);
        }
    }
}

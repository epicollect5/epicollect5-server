<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Http\Controllers\Api\Entries\Upload\External\PublicRoutes\Version;

use Carbon\Carbon;
use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Libraries\Utilities\Common;
use ec5\Libraries\Utilities\DateFormatConverter;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Services\Mapping\ProjectMappingService;
use ec5\Services\Project\ProjectExtraService;
use ec5\Traits\Assertions;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Throwable;

class InvalidCreatedAtEntriesTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private string $endpoint = 'api/upload/';

    public function setUp(): void
    {
        parent::setUp();
        //remove leftovers
        User::where(
            'email',
            'like',
            '%example.net%'
        )
            ->delete();

        $this->faker = Faker::create();

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
                'access' => config('epicollect.strings.project_access.public')
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

        $this->entryGenerator = new EntryGenerator($projectDefinition);
        $this->user = $user;
        $this->role = $role;
        $this->project = $project;
        $this->projectDefinition = $projectDefinition;
        $this->projectExtra = $projectExtra;
        $this->deviceId = Common::generateRandomHex();

    }

    public function test_catch_epoch_time_created_at_in_payload_entry()
    {
        //create entry payload
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);

        //set invalid date to payload
        $payload['data']['entry']['created_at'] = '1970-01-01T16:39:35.345Z';
        $response = [];
        try {
            //perform an app upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );

            //assert payload stored vs. payload uploaded
            $entryFromDB = Entry::where('uuid', $payload['data']['id'])->first();
            $entryFromPayload = $payload['data']['entry'];

            $this->assertNotEquals($entryFromDB->created_at, $entryFromPayload['created_at']);
            $this->assertGreaterThan(1970, $entryFromDB->created_at->year);
            //compare the date portion (dd-mm-yyyy)
            $this->assertEquals(
                Carbon::today()->toDateString(),
                Carbon::parse($entryFromDB->created_at)->toDateString()
            );

            //assert created_at against entry_data
            $entryData = json_decode($entryFromDB->entry_data, true);
            $geoJsonData = json_decode($entryFromDB->geo_json_data, true);

            $this->assertEquals(
                Carbon::today()->toDateString(),
                Carbon::parse($entryData['entry']['created_at'])->toDateString()
            );

            foreach ($geoJsonData as $geoJson) {
                $this->assertEquals(
                    Carbon::today()->toDateString(),
                    Carbon::parse($geoJson['properties']['created_at'])->toDateString()
                );
            }
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_valid_created_at_in_payload_entry_is_not_touched()
    {
        //create entry payload
        $randomValidCreatedAt = $this->getRandomValidCreatedAt();
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);

        //set invalid date to payload
        $payload['data']['entry']['created_at'] = $randomValidCreatedAt;
        $response = [];
        try {
            //perform an app upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );

            //assert payload stored vs. payload uploaded
            $entryFromDB = Entry::where('uuid', $payload['data']['id'])->first();
            $entryFromPayload = $payload['data']['entry'];


            $this->assertEquals(
                $entryFromDB->created_at->format(DateFormatConverter::getEntryPayloadCreatedAtFormat()),
                $entryFromPayload['created_at']
            );
            $this->assertGreaterThan(1970, $entryFromDB->created_at->year);
            //assert created_at against entry_data
            $entryData = json_decode($entryFromDB->entry_data, true);
            $geoJsonData = json_decode($entryFromDB->geo_json_data, true);

            $this->assertEquals(
                $randomValidCreatedAt,
                $entryData['entry']['created_at']
            );

            foreach ($geoJsonData as $geoJson) {
                $this->assertEquals(
                    Carbon::parse($randomValidCreatedAt)->toDateString(),
                    Carbon::parse($geoJson['properties']['created_at'])->toDateString()
                );
            }
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_catch_zero_date_in_payload_entry()
    {
        //create entry payload
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);

        //set invalid date to payload
        $payload['data']['entry']['created_at'] = '0000-00-00T00:00:00.000Z';
        $response = [];
        try {
            //perform an app upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                            "code" => "ec5_79",
                            "title" => "Date/time format incorrect",
                            "source" => "entry.created_at"
                             ]
                        ]
                    ]
                );
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_catch_epoch_time_created_at_in_payload_branch_entry()
    {
        //create owner entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //create branch entry payload
        $inputs = $this->projectDefinition['data']['project']['forms'][0]['inputs'];
        $branches = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.branch')) {
                $branches[] = $input;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branches[0]['branch'],
            $entryPayloads[0]['data']['id'],
            $branches[0]['ref']
        );

        //set invalid date to branch entry payload
        $payload['data']['branch_entry']['created_at'] = '1970-01-01T06:22:11.234Z';
        $response = [];
        try {
            //perform an app upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );

            //assert payload stored vs. payload uploaded
            $branchEntryFromDB = BranchEntry::where('uuid', $payload['data']['id'])->first();
            $branchEntryFromPayload = $payload['data']['branch_entry'];

            $this->assertNotEquals($branchEntryFromDB->created_at, $branchEntryFromPayload['created_at']);
            $this->assertGreaterThan(1970, $branchEntryFromDB->created_at->year);
            //compare the date portion (dd-mm-yyyy)
            $this->assertEquals(
                Carbon::today()->toDateString(),
                Carbon::parse($branchEntryFromDB->created_at)->toDateString()
            );

            //assert created_at against entry_data
            $branchEntryData = json_decode($branchEntryFromDB->entry_data, true);
            $branchGeoJsonData = json_decode($branchEntryFromDB->geo_json_data, true);
            $this->assertEquals(
                Carbon::today()->toDateString(),
                Carbon::parse($branchEntryData['branch_entry']['created_at'])->toDateString()
            );

            foreach ($branchGeoJsonData as $branchGeoJson) {
                $this->assertEquals(
                    Carbon::today()->toDateString(),
                    Carbon::parse($branchGeoJson['properties']['created_at'])->toDateString()
                );
            }
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_valid_date_in_payload_branch_entry_is_not_touched()
    {
        //create owner entry
        $randomValidCreatedAt = $this->getRandomValidCreatedAt();
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //create branch entry payload
        $inputs = $this->projectDefinition['data']['project']['forms'][0]['inputs'];
        $branches = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.branch')) {
                $branches[] = $input;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branches[0]['branch'],
            $entryPayloads[0]['data']['id'],
            $branches[0]['ref']
        );

        //set invalid date to branch entry payload
        $payload['data']['branch_entry']['created_at'] = $randomValidCreatedAt;
        $response = [];
        try {
            //perform an app upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );

            //assert payload stored vs. payload uploaded
            $branchEntryFromDB = BranchEntry::where('uuid', $payload['data']['id'])->first();
            $branchEntryFromPayload = $payload['data']['branch_entry'];

            $this->assertEquals(
                $branchEntryFromDB->created_at->format(DateFormatConverter::getEntryPayloadCreatedAtFormat()),
                $branchEntryFromPayload['created_at']
            );
            $this->assertGreaterThan(1970, $branchEntryFromDB->created_at->year);
            //assert created_at against entry_data
            $branchEntryData = json_decode($branchEntryFromDB->entry_data, true);
            $branchGeoJsonData = json_decode($branchEntryFromDB->geo_json_data, true);

            $this->assertEquals(
                $randomValidCreatedAt,
                $branchEntryData['branch_entry']['created_at']
            );

            foreach ($branchGeoJsonData as $geoJson) {
                $this->assertEquals(
                    Carbon::parse($randomValidCreatedAt)->toDateString(),
                    Carbon::parse($geoJson['properties']['created_at'])->toDateString()
                );
            }
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_catch_zero_date_in_payload_branch_entry()
    {
        //create owner entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //create branch entry payload
        $inputs = $this->projectDefinition['data']['project']['forms'][0]['inputs'];
        $branches = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.branch')) {
                $branches[] = $input;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branches[0]['branch'],
            $entryPayloads[0]['data']['id'],
            $branches[0]['ref']
        );

        //set invalid date to branch entry payload
        $payload['data']['branch_entry']['created_at'] = '0000-00-00T00:00:00.000Z';
        $response = [];
        try {
            //perform an app upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_79",
                                "title" => "Date/time format incorrect",
                                "source" => "branch_entry.created_at"
                            ]
                        ]
                    ]
                );
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    private function getRandomValidCreatedAt()
    {
        // Define the start and end dates
        $start = Carbon::createFromDate(2016, 1, 1);
        $end = Carbon::now();

        // Get a random timestamp between the start and end dates
        $randomTimestamp = mt_rand($start->timestamp, $end->timestamp);

        // Create a Carbon instance from the random timestamp
        $randomDate = Carbon::createFromTimestamp($randomTimestamp);

        return $randomDate->format(DateFormatConverter::getEntryPayloadCreatedAtFormat());
    }
}

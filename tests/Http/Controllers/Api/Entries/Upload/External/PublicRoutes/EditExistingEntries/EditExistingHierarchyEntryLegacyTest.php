<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\External\PublicRoutes\EditExistingEntries;

use Auth;
use DB;
use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Models\Entries\Entry;
use ec5\Models\Entries\EntryJson;
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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

class EditExistingHierarchyEntryLegacyTest extends TestCase
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
        )->delete();

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
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_text_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.text')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload text answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);


            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_integer_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first integer question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.integer')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload integer answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);


            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }


    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_decimal_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first decimal question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.decimal')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload decimal answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);


            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_phone_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first phone question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.phone')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload phone answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);


            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_date_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first date question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.date')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload date answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);


            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_time_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first time question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.time')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload time answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);

            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_dropdown_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first dropdown question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.dropdown')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload dropdown answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);

            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_radio_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first radio question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.radio')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload radio answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);

            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_checkbox_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first checkbox question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.checkbox')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload checkbox answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);

            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_searchsingle_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first searchsingle question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.searchsingle')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload searchsingle answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);

            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_searchmultiple_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first searchmultiple question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.searchmultiple')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload searchmultiple answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);

            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_textbox_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first textbox question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.textarea')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload textbox answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);

            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_location_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first location question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.location')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload location answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);

            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

            //assert geo json answer was edited
            $editedGeoJsonAnswers = json_decode($editedEntryJsonFromDB->geo_json_data, true);
            foreach ($editedGeoJsonAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {

                    //get latitude and longitude from edited answer (entry_data)
                    $editedLatitude = $editedAnswers[$inputRef]['answer']['latitude'];
                    $editedLongitude = $editedAnswers[$inputRef]['answer']['longitude'];

                    //assert latitude and longitude match the geometry in geo_json_data
                    $this->assertEquals($editedLatitude, $editedAnswer['geometry']['coordinates'][1]);
                    $this->assertEquals($editedLongitude, $editedAnswer['geometry']['coordinates'][0]);
                    break;
                }
            }

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }


    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_photo_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first photo question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.photo')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload photo answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);

            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_audio_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first audio question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.audio')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload audio answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);

            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_video_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first video question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.video')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload video answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);

            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')] public function test_edit_legacy_existing_entry_barcode_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first barcode question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.barcode')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDB->id)->first();

        //now build a legacy entry and remove the entries_json row
        $entryData = json_decode($entryJson->entry_data, true);
        $geoJsonData = json_decode($entryJson->geo_json_data, true);
        $entryJson->delete();

        $entryFromDB->entry_data = json_encode($entryData);
        $entryFromDB->geo_json_data = json_encode($geoJsonData);
        $entryFromDB->save();

        //get edited entry from db and assert entry json is null
        $entryFromDBLegacy = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $entryJson = EntryJson::where('entry_id', $entryFromDBLegacy->id)->first();
        $this->assertNull($entryJson);
        $this->assertNotNull($entryFromDBLegacy->entry_data);
        $this->assertNotNull($entryFromDBLegacy->geo_json_data);

        //try to upload payload barcode answer edited
        $editedAnswers = json_decode($entryFromDBLegacy->entry_data, true)['entry']['answers'];

        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDBLegacy->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert the json was saved to entries_json table
            $editedEntryJsonFromDB = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($editedEntryJsonFromDB);
            $this->assertNotNull($editedEntryJsonFromDB->entry_data);
            $this->assertNotNull($editedEntryJsonFromDB->geo_json_data);

            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryJsonFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($entryFromDBLegacy->user_id, $editedEntryFromDB->user_id);

            // Check the actual database column values, bypassing Eloquent's accessor
            // ⚠️ IMP: We use DB::table() instead of Entry::where() because
            //         the Entry model has a getEntryDataAttribute() accessor that transparently
            //         falls back to the entries_json table when entry_data is null.
            //         We need to verify the actual column value, not the accessor result.
            $editedEntryFromDB = DB::table('entries')
                ->where('uuid', $entryPayloads[0]['data']['id'])
                ->first();
            $this->assertNull($editedEntryFromDB->entry_data);
            $this->assertNull($editedEntryFromDB->geo_json_data);

            //assert json column exists in entries_json table
            $entryJson = EntryJson::where('entry_id', $editedEntryFromDB->id)->first();
            $this->assertNotNull($entryJson);
            $this->assertNotNull($entryJson->entry_data);
            $this->assertNotNull($entryJson->geo_json_data);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }


}

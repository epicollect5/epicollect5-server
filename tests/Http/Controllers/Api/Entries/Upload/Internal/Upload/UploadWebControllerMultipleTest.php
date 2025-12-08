<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\Internal\Upload;

use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Throwable;

/* This class does not follow optimal testing practices
   since the application does not get rebooted before each request
   like a production environment,
   but these tests are still useful to find bugs when uploading entries

   be aware __construct() are called only the first time,
   so it might have some false positives or not detect
   some errors

   imp: project stats are not updated per each upload
   since that is expensive, we update them when the
   project home page is requested, or the dataviewer

*/

class UploadWebControllerMultipleTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private array $projectDefinition;
    private Project $project;
    private EntryGenerator $entryGenerator;

    public function setUp(): void
    {
        parent::setUp();
        $user = factory(User::class)->create();
        $projectDefinition = ProjectDefinitionGenerator::createProject(5);
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
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //create basic project definition
        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data'], JSON_UNESCAPED_SLASHES)
            ]
        );
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        $entryGenerator = new EntryGenerator($projectDefinition);

        //upload the project definition via the formbuilder controller
        // Convert data array to JSON
        $jsonData = json_encode($projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData);
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = [];
        try {
            $response[] = $this->actingAs($user)
                ->call(
                    'POST',
                    'api/internal/formbuilder/' . $project->slug,
                    [],
                    [],
                    [],
                    [],
                    $base64EncodedData
                );

            /// dd($response[0]);


            $response[0]->assertStatus(200);
            $this->assertSame(json_decode($response[0]->getContent(), true), $projectDefinition);
            //assert there are no entries or branch entries
            $this->assertCount(0, Entry::where('project_id', $project->id)->get());
            $this->assertCount(0, BranchEntry::where('project_id', $project->id)->get());

            $this->user = $user;
            $this->project = $project;
            $this->projectDefinition = $projectDefinition;
            $this->entryGenerator = $entryGenerator;

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_should_upload_top_hierarchy_entry()
    {
        $response = [];
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //generate a fake entry for the top parent form
            $entry = $this->entryGenerator->createParentEntryPayload($formRef);
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $entry);
            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertEquals(0, Entry::where('project_id', $this->project->id)->value('child_counts'));
            $this->assertEquals(0, Entry::where('project_id', $this->project->id)->value('branch_counts'));
            $this->assertCount(
                1,
                Entry::where('project_id', $this->project->id)
                    ->where('uuid', $entry['data']['id'])
                    ->get()
            );
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_should_catch_emoji_in_hierarchy_entry()
    {
        $response = [];
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //generate a fake entry for the top parent form
            $entry = $this->entryGenerator->createParentEntryPayload($formRef);


            //get a text input ref
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            $ref = '';
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.text')) {
                    $ref = $input['ref'];
                    break;
                }
            }

            //use emoji in the text answer (imp: need json_encode to convert to unicode)
            $entry['data']['entry']['answers'][$ref]['answer'] = '😇'; // Emoji represented as Unicode escape sequence

            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $entry);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_323",
                                "title" => "No Emoji allowed.",
                                "source" => "validation"
                            ]
                        ]
                    ]
                );
            $this->assertCount(
                0,
                Entry::where('project_id', $this->project->id)
                    ->where('uuid', $entry['data']['id'])
                    ->get()
            );

            $this->assertCount(
                0,
                Entry::where('project_id', $this->project->id)
                    ->get()
            );
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_should_catch_html_in_hierarchy_entry()
    {
        $response = [];
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //generate a fake entry for the top parent form
            $entry = $this->entryGenerator->createParentEntryPayload($formRef);

            //get a text input ref
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            $ref = '';
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.text')) {
                    $ref = $input['ref'];
                    break;
                }
            }

            //use emoji in the text answer (imp: need json_encode to convert to unicode)
            $entry['data']['entry']['answers'][$ref]['answer'] = '<a href="#">Ciao</a>'; // Emoji represented as Unicode escape sequence

            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $entry);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_220",
                                "title" => "No < or > chars allowed.",
                                "source" => "validation"
                            ]
                        ]
                    ]
                );
            $this->assertCount(
                0,
                Entry::where('project_id', $this->project->id)
                    ->where('uuid', $entry['data']['id'])
                    ->get()
            );

            $this->assertCount(
                0,
                Entry::where('project_id', $this->project->id)
                    ->get()
            );
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_should_upload_multiple_top_hierarchy_entries()
    {
        $entriesCount = rand(50, 100);
        for ($i = 0; $i < $entriesCount; $i++) {
            $this->test_should_upload_top_hierarchy_entry();
        }
    }
}

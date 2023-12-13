<?php

namespace Tests\Http\Controllers\Api\Entries\Upload;

use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\ProjectStats;
use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Eloquent\User;
use Exception;
use Faker\Factory as Faker;
use Tests\Generators\EntryGenerator;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;


/* This class does not follow optimal testing practices
   since the application does not get rebooted before each request
   like a production environment,
   but these tests are still useful to find bugs when uploading entries

   be aware __construct() are called only the first time,
   so it might have some false positives or not detect
   some errors
*/

class WebUploadControllerTestMultiple extends TestCase
{
    use DatabaseTransactions;

    private $user;
    private $projectDefinition;
    private $project;
    private $faker;
    private $entryGenerator;

    public function setUp()
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
                'access' => config('ec5Strings.project_access.private')
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
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = [];
        try {
            $response[] = $this->actingAs($user)
                ->call('POST', 'api/internal/formbuilder/' . $project->slug,
                    [],
                    [],
                    [],
                    [], $base64EncodedData);

            $response[0]->assertStatus(200);
            $this->assertSame(json_decode($response[0]->getContent(), true), $projectDefinition);
            //assert there are no entries or branch entries
            $this->assertCount(0, Entry::where('project_id', $project->id)->get());
            $this->assertCount(0, BranchEntry::where('project_id', $project->id)->get());

            $this->user = $user;
            $this->project = $project;
            $this->projectDefinition = $projectDefinition;
            $this->entryGenerator = $entryGenerator;
        } catch (Exception $e) {
            dd($e->getMessage(), $response[0]->getContent());
        }
    }

    public function test_should_upload_top_hierarchy_entry()
    {
        $response = [];
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //generate a fake entry for the top parent form
            $entry = $this->entryGenerator->createParentEntry($formRef);
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $entry);
            $response[0]->assertStatus(200)
                ->assertExactJson([
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertCount(1, Entry::where('project_id', $this->project->id)->get());
        } catch (Exception $e) {
            //dd($e->getMessage(), $response, json_encode($entry), json_encode($projectDefinition));
            dd($e->getMessage(), $response[0]->getContent());
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
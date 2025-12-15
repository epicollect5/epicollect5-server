<?php

namespace Tests\Http\Validation\Upload;

use ec5\Http\Validation\Entries\Upload\FileRules\RuleAudio;
use ec5\Http\Validation\Entries\Upload\FileRules\RulePhotoApp;
use ec5\Http\Validation\Entries\Upload\FileRules\RulePhotoWeb;
use ec5\Http\Validation\Entries\Upload\FileRules\RuleVideo;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleAudioInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleBranchInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleCheckboxInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleDateInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleDecimalInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleDropdownInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleGroupInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleIntegerInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleLocationInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RulePhoneInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RulePhotoInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleRadioInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleSearchMultipleInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleSearchSingleInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleTextareaInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleTextInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleTimeInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleVideoInput;
use ec5\Http\Validation\Entries\Upload\RuleAnswers;
use ec5\Http\Validation\Entries\Upload\RuleBranchEntry;
use ec5\Http\Validation\Entries\Upload\RuleEntry;
use ec5\Http\Validation\Entries\Upload\RuleFileEntry;
use ec5\Http\Validation\Entries\Upload\RuleUpload;
use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Tests\TestCase;

class RuleUploadTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        //create fake user for testing
        $user = factory(User::class)->create();
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
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //create basic project definition
        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data'])
            ]
        );
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );

        $this->user = $user;
        $this->project = $project;
        $this->projectDefinition = $projectDefinition;
        $this->entryGenerator = new EntryGenerator($projectDefinition);

        $ruleAnswers = new RuleAnswers(
            new RuleIntegerInput(),
            new RuleDecimalInput(),
            new RuleRadioInput(),
            new RuleTextInput(),
            new RuleTextareaInput(),
            new RuleDateInput(),
            new RuleTimeInput(),
            new RuleLocationInput(),
            new RuleCheckboxInput(),
            new RulePhotoInput(),
            new RuleVideoInput(),
            new RuleAudioInput(),
            new RuleBranchInput(),
            new RuleGroupInput(),
            new RuleDropdownInput(),
            new RulePhoneInput(),
            new RuleSearchSingleInput(),
            new RuleSearchMultipleInput()
        );

        $ruleFileEntry = new RuleFileEntry(
            new RulePhotoApp(),
            new RulePhotoWeb(),
            new RuleVideo(),
            new RuleAudio(),
            $ruleAnswers
        );

        $this->ruleUpload = new RuleUpload(
            new RuleEntry($ruleAnswers),
            new RuleBranchEntry($ruleAnswers),
            $ruleAnswers,
            $ruleFileEntry
        );
    }


    public function test_should_be_valid_parent_entry()
    {
        //create entry based on the project
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        //generate a fake entry for the top parent form
        $entry = $this->entryGenerator->createParentEntryPayload($formRef);

        $this->ruleUpload->validate($entry['data']);
        $this->assertFalse($this->ruleUpload->hasErrors());
    }

    public function test_should_be_valid_child_entry()
    {
        $this->assertTrue(true);
        //todo
    }

    public function test_should_be_valid_branch_entry()
    {
        $this->assertTrue(true);
        //todo
    }

    public function test_should_catch_entry_null()
    {
        $entry = null;
        $this->ruleUpload->validate($entry);
        $this->assertTrue($this->ruleUpload->hasErrors());
        $this->assertEquals(
            ["validation" => ["ec5_269"]],
            $this->ruleUpload->errors()
        );
    }

    public function test_should_missing_entry_key()
    {
        //create entry based on the project
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        //generate a fake entry for the top parent form
        $entry = $this->entryGenerator->createParentEntryPayload($formRef);

        unset($entry['data']['entry']);

        $this->ruleUpload->validate($entry['data']);
        $this->assertTrue($this->ruleUpload->hasErrors());
        $this->assertEquals(
            [
                "entry" => [
                    "ec5_21"
                ],
                "entry.entry_uuid" => [
                    "ec5_21"
                ],
                "entry.created_at" => [
                    "ec5_21"
                ],
                "entry.project_version" => [
                    "ec5_21"
                ]
            ],
            $this->ruleUpload->errors()
        );
    }

    public function test_multiple_valid_parent_entries()
    {
        //brute force!
        $count = rand(1000, 5000);
        for ($i = 0; $i < $count; $i++) {
            $this->test_should_be_valid_parent_entry();
        }
    }
}

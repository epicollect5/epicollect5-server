<?php

namespace Tests\Http\Controllers\Web\Project\ProjectCreateController;

use ec5\Http\Validation\Project\RuleCreateRequest;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Users\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

class CreateMethodTest extends TestCase
{
    use DatabaseTransactions;

    const DRIVER = 'web';

    protected $request;
    protected $validator;
    protected $access;
    protected $projectNameMaxLength;

    public function setUp()
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();

        $this->validator = new RuleCreateRequest();

        $this->projectNameMaxLength = config('ec5Limits.project.name.max');
        $this->access = config('ec5Enums.projects_access');

        //to have a user logged in as superadmin
        $user = User::find(1);
        $this->be($user);

        $this->reset();
    }

    public function tearDown()
    {
        // Clear fake storage after each test
        Storage::fake('local');

        parent::tearDown();
    }

    public function reset()
    {

        $this->request = [
            'name' => 'Test Project 000001',
            'slug' => 'test-project-000001',
            'form_name' => 'Form One',
            'small_description' => 'Just a test project to test the validation of the project request',
            'access' => $this->access[array_rand($this->access)]
        ];
    }

    public function test_name()
    {
        $this->validator->validate($this->request);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //not alpha numeric
        $this->request['name'] = '---';
        $this->request['slug'] = '---';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //empty
        $this->request['name'] = '';
        $this->request['slug'] = '';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //symbols
        $this->request['name'] = 'ha ha ha $%';
        $this->request['slug'] = 'ha-ha-ha-$%';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //too long
        $this->request['name'] = 'dRJTyYVxAz4hYfBOKdrkUmzuQhdTDIB33MqjiA4Lz4tYmlxDl8R';
        $this->request['slug'] = 'dRJTyYVxAz4hYfBOKdrkUmzuQhdTDIB33MqjiA4Lz4tYmlxDl8R';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //not unique
        $this->request['name'] = 'Bestpint';
        $this->request['slug'] = 'bestpint';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //can use ec5 prefix
        $this->request['name'] = 'EC5 Bestpint';
        $this->request['slug'] = 'ec5-bestpint';

        $this->validator->validate($this->request);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();


        //to have a user logged in as basic
        $user = User::find(10);
        $this->be($user);

        //canNOT use ec5 prefix
        $this->request['name'] = 'EC5 Bestpint';
        $this->request['slug'] = 'ec5-bestpint';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //canNOT use 'create' as project name
        $this->request['name'] = 'Create';
        $this->request['slug'] = 'create';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();


    }

    public function test_project_name_should_not_have_extra_spaces()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $projectName = 'Multiple    Spaces      between   words   ';
        $projectSlug = 'multiple-spaces-between-words';

        $response = $this->actingAs($user, SELF::DRIVER)
            ->post('myprojects/create', [
                '_token' => csrf_token(),
                'name' => 'Multiple    Spaces      between   words   ',
                'form_name' => 'Form One',
                'small_description' => 'Just a test project to test the removal of multiple spaces',
                'access' => 'private'
            ]);

        //Check if the redirect is successful
        $response->assertRedirect('myprojects/' . $projectSlug)
            ->assertSessionHas('projectCreated', true)
            ->assertSessionHas('tab', 'create');
        //Check if the project is created
        $this->assertDatabaseHas('projects', ['slug' => $projectSlug]);
        //check name is sanitised with extra spaces removed
        $this->assertDatabaseHas('projects', ['name' => 'Multiple Spaces between words']);
        //check original name with extra spaces was not saved
        $this->assertDatabaseMissing('projects', ['name' => $projectName]);
    }

    public function test_form_name_should_not_have_extra_spaces()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $projectName = 'Multiple    Spaces      between   words   ';
        $projectSlug = 'multiple-spaces-between-words';

        $response = $this->actingAs($user, SELF::DRIVER)
            ->post('myprojects/create', [
                '_token' => csrf_token(),
                'name' => 'Multiple    Spaces      between   words   ',
                'form_name' => 'Form      One      ',
                'small_description' => 'Just a test project to test the removal of multiple spaces',
                'access' => 'private'
            ]);

        //Check if the redirect is successful
        $response->assertRedirect('myprojects/' . $projectSlug)
            ->assertSessionHas('projectCreated', true)
            ->assertSessionHas('tab', 'create');

        $this->assertDatabaseHas('projects', ['slug' => $projectSlug]);
        //check name is sanitised with extra spaces removed
        $this->assertDatabaseHas('projects', ['name' => 'Multiple Spaces between words']);
        //check original name with extra spaces was not saved
        $this->assertDatabaseMissing('projects', ['name' => $projectName]);

        $project = Project::where('slug', $projectSlug)->first();
        $projectDefinition = json_decode(ProjectStructure::where('project_id', $project->id)
            ->value('project_definition'));

        $formName = $projectDefinition->project->forms[0]->name;
        $this->assertEquals('Form One', $formName);

    }

    public function test_small_description_should_not_have_extra_spaces()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $projectName = 'Multiple    Spaces      between   words   ';
        $projectSlug = 'multiple-spaces-between-words';

        $response = $this->actingAs($user, SELF::DRIVER)
            ->post('myprojects/create', [
                '_token' => csrf_token(),
                'name' => 'Multiple    Spaces      between   words   ',
                'form_name' => 'Form      One      ',
                'small_description' => 'Just   a    test   project to test the    removal   of multiple    spaces  ',
                'access' => 'private'
            ]);

        //Check if the redirect is successful
        $response->assertRedirect('myprojects/' . $projectSlug)
            ->assertSessionHas('projectCreated', true)
            ->assertSessionHas('tab', 'create');

        $this->assertDatabaseHas('projects', ['slug' => $projectSlug]);
        //check name is sanitised with extra spaces removed
        $this->assertDatabaseHas('projects', ['name' => 'Multiple Spaces between words']);
        //check original name with extra spaces was not saved
        $this->assertDatabaseMissing('projects', ['name' => $projectName]);

        $project = Project::where('slug', $projectSlug)->first();
        $projectDefinition = json_decode(ProjectStructure::where('project_id', $project->id)
            ->value('project_definition'));

        $smallDesc = $projectDefinition->project->small_description;
        $this->assertEquals('Just a test project to test the removal of multiple spaces', $smallDesc);

    }

    public function test_small_description()
    {
        //empty
        $this->request['small_description'] = '';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //too short
        $this->request['small_description'] = 'ciao';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //too long
        $this->request['small_description'] = 'ciao blasbdja jhasbdjas djb jhdashjda da d ajsd hjasdhjashjd hajjd  ahdasjdh jahsd ah dha dhja jhd hja da hd a da dajhd aj dh asdjah ';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();
    }

    public function test_access()
    {
        //empty
        $this->request['access'] = '';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //invalid
        $this->request['access'] = 'ciao';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //null
        $this->request['access'] = null;

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();
    }

    public function test_form_name()
    {
        //empty
        $this->request['form_name'] = '';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //too long
        $this->request['form_name'] = 'dRJTyYVxAz4hYfBOKdrkUmzuQhdTDIB33MqjiA4Lz4tYmlxDl8R';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();
    }
}

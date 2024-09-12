<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Http\Validation\Project\RuleImportRequest;
use ec5\Libraries\Utilities\Common;
use ec5\Libraries\Utilities\Generators;
use ec5\Libraries\Utilities\Strings;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class ProjectImportControllerTest extends TestCase
{
    use DatabaseTransactions;

    const DRIVER = 'web';
    protected $faker;
    protected $request;
    protected $validator;
    protected $access;
    protected $projectNameMaxLength;

    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->faker = Faker::create();

        $this->validator = new RuleImportRequest();

        $this->projectNameMaxLength = config('epicollect.limits.project.name.max');
        $this->access = array_keys(config('epicollect.strings.projects_access'));

        //to have a user logged in as superadmin
        $user = User::find(1);
        $this->be($user);

        $this->reset();
    }

    public function reset()
    {

        $this->request = [
            'name' => 'Test Project 000001',
            'slug' => 'test-project-000001',
            'file' => 'test-project.json'
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
        $projectDefinition = (ProjectDefinitionGenerator::createProject(1));
        $projectDefinition['data']['project']['name'] = $projectName;
        $projectDefinition['data']['project']['slug'] = $projectSlug;
        // Create a temporary file with content
        $fileContent = json_encode($projectDefinition);
        $tempFile = tempnam(sys_get_temp_dir(), 'fakefile');
        file_put_contents($tempFile, $fileContent);
        // Create a fake UploadedFile instance from the temporary file
        $fakeFile = UploadedFile::fake()->create('fakefile.json', 512, 'application/json');
        copy($tempFile, $fakeFile->getRealPath());

        $response = $this->actingAs($user, self::DRIVER)
            ->post('myprojects/import', [
                'name' => 'Multiple    Spaces      between   words   ',
                'file' => $fakeFile
            ]);
        unlink($tempFile);

        //Check if the redirect is successful
        $response->assertRedirect('myprojects/' . $projectSlug)
            ->assertSessionHas('projectCreated', true)
            ->assertSessionHas('tab', 'import');
        //Check if the project is created
        $this->assertDatabaseHas('projects', ['slug' => $projectSlug]);
        //check name is sanitised with extra spaces removed
        $this->assertDatabaseHas('projects', ['name' => 'Multiple Spaces between words']);
        //check the original name with extra spaces was not saved
        $this->assertDatabaseMissing('projects', ['name' => $projectName]);
    }

    public function test_project_is_imported_correctly()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $projectName = Generators::projectRef();
        $projectSlug = Str::slug($projectName);
        $projectDefinition = (ProjectDefinitionGenerator::createProject(1));
        $projectDefinition['data']['project']['name'] = $projectName;
        $projectDefinition['data']['project']['slug'] = $projectSlug;
        // Create a temporary file with that structure content
        $fileContent = json_encode($projectDefinition);
        $tempFile = tempnam(sys_get_temp_dir(), 'fakefile');
        file_put_contents($tempFile, $fileContent);
        // Create a fake UploadedFile instance from the temporary file
        $fakeFile = UploadedFile::fake()->create('fakefile.json', 512, 'application/json');
        copy($tempFile, $fakeFile->getRealPath());

        $response = $this->actingAs($user, self::DRIVER)
            ->post('myprojects/import', [
                'name' => $projectName,
                'file' => $fakeFile
            ]);
        unlink($tempFile);

        //Check if the redirect is successful
        $response->assertRedirect('myprojects/' . $projectSlug)
            ->assertSessionHas('projectCreated', true)
            ->assertSessionHas('tab', 'import');
        //Check if the project is created
        $this->assertDatabaseHas('projects', ['slug' => $projectSlug]);
        //check name is sanitized with extra spaces removed
        $this->assertDatabaseHas('projects', ['name' => $projectName]);

        //check project definition structure matches (with ref replaced)
        $projectImported = Project::where('name', $projectName)->first();
        $projectStructureImported = ProjectStructure::where('project_id', $projectImported->id)->first();
        $projectDefinitionImported = json_decode($projectStructureImported->project_definition, true);
        $projectDefinitionExpected = Common::replaceRefInStructure(
            $projectDefinition['data']['project']['ref'],
            $projectImported->ref,
            $projectDefinition
        );
        $this->assertEquals($projectDefinitionExpected['data'], $projectDefinitionImported);
    }

    public function test_file()
    {
        //empty
        $this->request['file'] = '';
        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //too long
        $this->request['file'] = 'nrfNQU8EkL3jAATIu5WhSZtovXNpbXVpeBHOULKWXrkQeIF6ypa3oqzZDJggd0FA0wyh5swcFEPAfiYp0aqVjhqANuCXSdeev8NvXqLPQ6jIwHs1mbbggzSg201ROYhlMtLEAWbC37khYeXv9hPEVd0Sb5UaujpPqtE8ducNy5eqJus2xAxCtzGsPeiJgHF7YbP78DnWnJGy01M8NERT6Aq2WBU6BOaZ08Q6YncN4vGDJD3c5zd3agmHsHZnElcqoKNFOqMMLouZtHwoyAY6Nq22vjawV1xUXklnQ3xcX18UAhgLPigpxGpTlvhnWbXwV5zMqTYBXeiXtfUpg7cQ9feuRAEt27eIBLcUqJ6AlFfStoI2GubqarnqQMYMjD62qG38WGxrYKPLqzIjBWcmiGsf0rNdLLHQE549FUEPjNOhlyY84lmWtiiwLOwViUHw77MXFFvAnYN8mIN4i064yfnIOjWZOkn0gnZipsJz0u2iz8LwwLJpSc1cA6Oy1LJAFkoptW8uqoPRZLKpOPiB8UMfOmTV1a6HvJBsFLWgeLcJJm0mS79biMLSuyYAK636gb8kBQnQIlT8MmkHJO6UCzJl9J1kgyd1CdAtTGOGsgaZSrMeAw8w36YkvcizrQTLVIEZuZlEQi3RhuOHXvgaGIedSpFviBG3HHfujIaYRaBAarFYK5EEkobQSsqVd6qRCrugrxZM1Gu9x99sGtSTsJM5lZCon4sA4vOSgFZmVeRojJwQNN9ybttLHDGA0Uh2rVnhTEv1myO0rv5rRLJemvGczVPrpKF5K57IvWICKYZnM0zvvqkHCobFQJ5sExc4Goqc2qwgh56XDaGEO7IBDPudO6zHFIuZCCp5p1qEbOoRvaXhaamTJ0sUkXl7T8ETVA65fkkHFk1wXmBbBwnvLxV3jUhLSFuWL1O9R5pRzBcyfDGR3xmMWNR9ckbJyyBVyzjWcq9EJykSaQ1IJzeUu0lJLOaOVV1z88hhMphf6
';

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //file is null
        $this->request['file'] = null;

        $this->validator->validate($this->request);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();
    }

    public function test_project_name_already_exists()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        //create a mock project with that user and use ref as name to avoid conflicts
        $ref = Generators::projectRef();
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'name' => $ref,
            'slug' => $ref
        ]);
        //try to import a project with the same name (ref)
        $response = $this
            ->actingAs($user, self::DRIVER)
            ->post('/myprojects/import', [
                'name' => $ref,
                'file' => 'Test File'
            ])
            ->assertStatus(200);

        $this->assertEquals('project.project_create', $response->original->getName());
        // Assert that there is an error message with key 'ec5_85'
        $this->assertArrayHasKey('errors', $response->original->getData());
        // Assert that the view has an 'errors' variable
        $this->assertTrue($response->original->offsetExists('errors'));
        // Access the MessageBag and assert specific errors
        $errors = $response->original->offsetGet('errors');
        $this->assertTrue($errors->has('name'));
        $this->assertTrue($errors->has('slug'));
        $this->assertEquals('ec5_85', $errors->first('name'));
        $this->assertEquals('ec5_85', $errors->first('slug'));
        // Assert that the validation errors are passed to the view
        $response->assertViewHas('errors');
        // Assert that the 'tab' variable is passed to the view
        $response->assertViewHas('tab', 'import');
    }

    public function test_project_name_too_short()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();

        //try to import a project with the same name (ref)
        $response = $this
            ->actingAs($user, self::DRIVER)
            ->post('/myprojects/import', [
                'name' => 'a',
                'file' => 'Test File'
            ])
            ->assertStatus(200);

        $this->assertEquals('project.project_create', $response->original->getName());
        // Assert that there is an error message with key 'ec5_85'
        $this->assertArrayHasKey('errors', $response->original->getData());
        // Assert that the view has an 'errors' variable
        $this->assertTrue($response->original->offsetExists('errors'));
        // Access the MessageBag and assert specific errors
        $errors = $response->original->offsetGet('errors');
        $this->assertTrue($errors->has('name'));
        //error is already translated
        $this->assertEquals('Project name must be at least 3 chars long!', $errors->first('name'));
        // Assert that the validation errors are passed to the view
        $response->assertViewHas('errors');
        // Assert that the 'tab' variable is passed to the view
        $response->assertViewHas('tab', 'import');
    }

    public function test_project_name_too_long()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();

        //try to import a project with the same name (ref)
        $response = $this
            ->actingAs($user, self::DRIVER)
            ->post('/myprojects/import', [
                'name' => Strings::generateRandomAlphanumericString(51),
                'file' => 'Test File'
            ])
            ->assertStatus(200);

        $this->assertEquals('project.project_create', $response->original->getName());
        // Assert that there is an error message with key 'ec5_85'
        $this->assertArrayHasKey('errors', $response->original->getData());
        // Assert that the view has an 'errors' variable
        $this->assertTrue($response->original->offsetExists('errors'));
        // Access the MessageBag and assert specific errors
        $errors = $response->original->offsetGet('errors');
        $this->assertTrue($errors->has('name'));
        //error is already translated
        $this->assertEquals('Project name must be maximum 50 chars long!', $errors->first('name'));
        // Assert that the validation errors are passed to the view
        $response->assertViewHas('errors');
        // Assert that the 'tab' variable is passed to the view
        $response->assertViewHas('tab', 'import');
    }
}

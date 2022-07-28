<?php

namespace Tests\Project;

use ec5\Http\Validation\Project\RuleImportRequest;
use Tests\TestCase;
use ec5\Models\Users\User;

class ProjectImportRequestTest extends TestCase
{
    protected $request;
    protected $validator;
    protected $access;
    protected $projectNameMaxLength;

    public function setUp()
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();

        $this->validator = new RuleImportRequest();

        $this->projectNameMaxLength = config('ec5Limits.project.name.max');
        $this->access = config('ec5Enums.projects_access');

        //to have a user logged in as superadmin
        $user = User::find(1);
        $this->be($user);

        $this->reset();
    }

    public function reset(){

        $this->request = [
            'name' => 'Test Project 000001',
            'slug' => 'test-project-000001',
            'file' => 'test-project.json'
        ];
    }

    public function testName(){

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

    public function testFile(){

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
}

<?php

namespace Tests\Project;

use ec5\Http\Validation\Project\RuleCreateRequest;
use Tests\TestCase;
use ec5\Models\Users\User;

class ProjectCreateRequestTest extends TestCase
{
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

    public function reset(){

        $this->request = [
            'name' => 'Test Project 000001',
            'slug' => 'test-project-000001',
            'form_name' => 'Form One',
            'small_description' => 'Just a test project to test the validation of the project request',
            'access' => $this->access[array_rand($this->access)]
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

    public function testSmallDescription(){

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

    public function testAccess(){
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

    public function testFormName(){
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

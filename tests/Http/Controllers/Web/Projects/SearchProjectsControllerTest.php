<?php

namespace Tests\Http\Controllers\Web\Projects;

use ec5\Http\Validation\Project\RuleSearch as ProjectsSearchValidator;
use Tests\TestCase;

class SearchProjectsControllerTest extends TestCase
{
    protected $validator;

    public function setUp()
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->validator = new ProjectsSearchValidator();
    }

    public function test_page_renders_correctly()
    {
        $response = $this->call('GET', 'projects/search');
        $response->assertStatus(200);
        $response->assertViewHas('projects');

        //test when requesting via ajax (returns compiled html, not json)
        $this->call('GET', 'projects/search', ['HTTP_X-Requested-With' => 'XMLHttpRequest']);
        $response->assertStatus(200);
    }

    /**
     *
     */
    public function test_parameters()
    {
        //valid parameters
        $parameters = [
            'sort_by' => 'total_entries',
            'sort_order' => 'desc',
            'name' => '',
            'page' => '1'
        ];

        $this->validator->validate($parameters);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    public function test_sort_by()
    {
        //invalid sort_by
        $parameters['sort_by'] = 'id';
        $this->validator->validate($parameters);
        $this->assertTrue($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();

        //invalid sort_by
        $parameters['sort_by'] = '##';
        $this->validator->validate($parameters);
        $this->assertTrue($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();

        //empty sort_by, valid
        $parameters['sort_by'] = '';
        $this->validator->validate($parameters);
        $this->assertFalse($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();
    }

    public function test_sort_order()
    {
        //invalid sort_order
        $parameters['sort_order'] = 'xx';
        $this->validator->validate($parameters);
        $this->assertTrue($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();

        //invalid sort_order
        $parameters['sort_order'] = '##';
        $this->validator->validate($parameters);
        $this->assertTrue($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();

        //empty sort_order, valid
        $parameters['sort_order'] = '';
        $this->validator->validate($parameters);
        $this->assertFalse($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();

        //asc sort_order, valid
        $parameters['sort_order'] = 'asc';
        $this->validator->validate($parameters);
        $this->assertFalse($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();

        //desc sort_order, valid
        $parameters['sort_order'] = 'desc';
        $this->validator->validate($parameters);
        $this->assertFalse($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();
    }

    public function test_name()
    {
        //invalid name
        $parameters['name'] = '--';
        $this->validator->validate($parameters);
        $this->assertTrue($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();

        //invalid name
        $parameters['name'] = ' # # ';
        $this->validator->validate($parameters);
        $this->assertTrue($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();

        //invalid name, too long
        $parameters['name'] = '123456789012345678901234567890123456789012345678901';
        $this->validator->validate($parameters);
        $this->assertTrue($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();

        //empty name, valid
        $parameters['name'] = '';
        $this->validator->validate($parameters);
        $this->assertFalse($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();

        //name with spaces, valid
        $parameters['name'] = 'the project is ';
        $this->validator->validate($parameters);
        $this->assertFalse($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();
    }

    public function test_page()
    {
        //invalid page
        $parameters['page'] = '--';
        $this->validator->validate($parameters);
        $this->assertTrue($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();

        //invalid page
        $parameters['page'] = '0';
        $this->validator->validate($parameters);
        $this->assertTrue($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();

        //invalid page
        $parameters['page'] = '-4';
        $this->validator->validate($parameters);
        $this->assertTrue($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();

        //empty page, valid
        $parameters['page'] = '';
        $this->validator->validate($parameters);
        $this->assertFalse($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();

        //valid
        $parameters['page'] = '3';
        $this->validator->validate($parameters);
        $this->assertFalse($this->validator->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->validator->resetErrors();
    }
}

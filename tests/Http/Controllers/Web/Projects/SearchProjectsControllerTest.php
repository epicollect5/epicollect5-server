<?php

namespace Tests\Http\Controllers\Web\Projects;

use ec5\Http\Validation\Project\RuleSearch;
use Tests\TestCase;

class SearchProjectsControllerTest extends TestCase
{
    protected $ruleSearch;

    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->ruleSearch = new RuleSearch();
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

        $this->ruleSearch->validate($parameters);
        $this->assertFalse($this->ruleSearch->hasErrors());
        $this->ruleSearch->resetErrors();
    }

    public function test_sort_by()
    {
        //invalid sort_by
        $parameters['sort_by'] = 'id';
        $this->ruleSearch->validate($parameters);
        $this->assertTrue($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();

        //invalid sort_by
        $parameters['sort_by'] = '##';
        $this->ruleSearch->validate($parameters);
        $this->assertTrue($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();

        //empty sort_by, valid
        $parameters['sort_by'] = '';
        $this->ruleSearch->validate($parameters);
        $this->assertFalse($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();
    }

    public function test_sort_order()
    {
        //invalid sort_order
        $parameters['sort_order'] = 'xx';
        $this->ruleSearch->validate($parameters);
        $this->assertTrue($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();

        //invalid sort_order
        $parameters['sort_order'] = '##';
        $this->ruleSearch->validate($parameters);
        $this->assertTrue($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();

        //empty sort_order, valid
        $parameters['sort_order'] = '';
        $this->ruleSearch->validate($parameters);
        $this->assertFalse($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();

        //asc sort_order, valid
        $parameters['sort_order'] = 'asc';
        $this->ruleSearch->validate($parameters);
        $this->assertFalse($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();

        //desc sort_order, valid
        $parameters['sort_order'] = 'desc';
        $this->ruleSearch->validate($parameters);
        $this->assertFalse($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();
    }

    public function test_name()
    {
        //invalid name
        $parameters['name'] = '--';
        $this->ruleSearch->validate($parameters);
        $this->assertTrue($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();

        //invalid name
        $parameters['name'] = ' # # ';
        $this->ruleSearch->validate($parameters);
        $this->assertTrue($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();

        //invalid name, too long
        $parameters['name'] = '123456789012345678901234567890123456789012345678901';
        $this->ruleSearch->validate($parameters);
        $this->assertTrue($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();

        //empty name, valid
        $parameters['name'] = '';
        $this->ruleSearch->validate($parameters);
        $this->assertFalse($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();

        //name with spaces, valid
        $parameters['name'] = 'the project is ';
        $this->ruleSearch->validate($parameters);
        $this->assertFalse($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();
    }

    public function test_page()
    {
        //invalid page
        $parameters['page'] = '--';
        $this->ruleSearch->validate($parameters);
        $this->assertTrue($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();

        //invalid page
        $parameters['page'] = '0';
        $this->ruleSearch->validate($parameters);
        $this->assertTrue($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();

        //invalid page
        $parameters['page'] = '-4';
        $this->ruleSearch->validate($parameters);
        $this->assertTrue($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();

        //empty page, valid
        $parameters['page'] = '';
        $this->ruleSearch->validate($parameters);
        $this->assertFalse($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();

        //valid
        $parameters['page'] = '3';
        $this->ruleSearch->validate($parameters);
        $this->assertFalse($this->ruleSearch->hasErrors());
        //fwrite(STDOUT, print_r($this->validator->errors()) . "\n");
        $this->ruleSearch->resetErrors();
    }
}

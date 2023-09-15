<?php

namespace Tests\Http\Validation\Project;

use Config;
use ec5\Http\Validation\Project\RuleForm;
use ec5\Http\Validation\Project\RuleInput;
use ec5\Http\Validation\Project\RuleProjectDefinition;
use ec5\Http\Validation\Project\RuleProjectExtraDetails;
use ec5\Models\Projects\Project;
use ec5\Models\Projects\ProjectDefinition;
use ec5\Models\Projects\ProjectExtra;
use ec5\Models\Projects\ProjectMapping;
use ec5\Models\Projects\ProjectStats;
use Illuminate\Support\Str;
use Tests\TestCase;
use Webpatser\Uuid\Uuid;

class ProjectMaxInputsTest extends TestCase
{

    /*
    |--------------------------------------------------------------------------
    | ProjectTest
    |--------------------------------------------------------------------------
    |
    |
    |
    */
    protected $validator;
    protected $project;
    protected $projectExtraDetailsValidator;
    protected $formValidator;
    protected $inputValidator;
    protected $projectExtra;
    protected $projectMapping;
    protected $projectStats;
    protected $projectDefinition;
    protected $projectExtraCreateMethod;
    protected $inputs_limit;
    protected $error_too_many_questions;

    protected static function getProjectExtraMethod($name)
    {
        $class = new \ReflectionClass('\ec5\Models\Projects\ProjectExtra');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    private function getProjectMock()
    {
        $projectRef = str_replace('-', '', Uuid::generate(4));
        $projectName = 'Test inputs limit';

        return [
            'ref' => $projectRef,
            'name' => 'Test search inputs limit',
            'slug' => Str::slug($projectName, '-'),
            'access' => 'public',
            'small_description' => 'Just a test',
            'status' => 'active',
            'visibility' => 'hidden',
            'logo_url' => 'path-to-file',
            'description' => 'A long description here',
            'entries_limits' => [],
            'category' => Config::get('ec5Enums.search_projects_defaults.category'),
            'forms' => []
        ];
    }

    private function getFormMock($projectRef, $formIndex)
    {
        // Create project and first form refs
        $formRef = $projectRef . '_' . uniqid();
        $formName = 'Form ' . $formIndex;

        return [
            'ref' => $formRef,
            'name' => $formName,
            'type' => 'hierarchy',
            'slug' => Str::slug($formName, '-'),
            'inputs' => []
        ];

    }

    private function getInputMock($formRef)
    {
        //input types
        $inputTypes = ['text', 'integer', 'phone'];

        return [
            'ref' => $formRef . '_' . uniqid(),
            'type' => $inputTypes[array_rand($inputTypes)], //randomly set the search type
            'question' => 'Test question', // Question length checked in additionalChecks()
            'is_title' => false,
            'is_required' => false,
            'regex' => '',
            'default' => '',
            'verify' => false,
            'max' => null,
            'min' => null,
            'uniqueness' => 'none',
            'datetime_format' => null,
            'set_to_current_datetime' => false,
            'possible_answers' => [],
            'jumps' => [],
            'branch' => [],
            'group' => []
        ];
    }

    /**
     *
     */
    public function setUp()
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();

        $this->projectExtraDetailsValidator = new  RuleProjectExtraDetails();
        $this->formValidator = new RuleForm();
        $this->inputValidator = new RuleInput();
        $this->projectExtra = new ProjectExtra();
        $this->projectMapping = new ProjectMapping();
        $this->projectStats = new ProjectStats();
        $this->projectDefinition = new ProjectDefinition();

        $this->validator = new RuleProjectDefinition(
            $this->projectExtraDetailsValidator,
            $this->formValidator,
            $this->inputValidator,
            $this->projectExtra,
            $this->projectDefinition
        );

        $this->inputs_limit = config('ec5Limits.formlimits.inputs');
        $this->error_too_many_questions = 'ec5_262';
    }

    public function testMaxInputsOneForm()
    {
        $project = new Project(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats
        );

        $projectMock = $this->getProjectMock();
        $projectMock['forms'][0] = $this->getFormMock($projectMock['ref'], 0);

        //add form name to use create() method
        $projectMock['form_name'] = 'just to pass method check';

        // Create new JSON Project Definition
        $project->create($projectMock['ref'], $projectMock);

        //reset extra property, not part of project definition
        unset($projectMock['form_name']);

        //add max inputs to first form
        for ($i = 0; $i < $this->inputs_limit; $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testTooManyInputsOneForm()
    {
        $project = new Project(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats
        );

        $projectMock = $this->getProjectMock();
        $projectMock['forms'][0] = $this->getFormMock($projectMock['ref'], 0);

        //add form name to use create() method
        $projectMock['form_name'] = 'just to pass method check';

        // Create new JSON Project Definition
        $project->create($projectMock['ref'], $projectMock);

        //reset extra property, not part of project definition
        unset($projectMock['form_name']);

        //add max inputs to first form
        for ($i = 0; $i < ($this->inputs_limit + 1); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals($this->validator->errors['validation'][0], $this->error_too_many_questions);
    }

    public function testTooManyInputsTwoForms()
    {
        $project = new Project(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats
        );

        $projectMock = $this->getProjectMock();
        $projectMock['forms'][0] = $this->getFormMock($projectMock['ref'], 0);
        $projectMock['forms'][1] = $this->getFormMock($projectMock['ref'], 1);

        //add form name to use create() method
        $projectMock['form_name'] = 'just to pass method check';

        // Create new JSON Project Definition
        $project->create($projectMock['ref'], $projectMock);

        //reset extra property, not part of project definition
        unset($projectMock['form_name']);

        //add max inputs to first form
        for ($i = 0; $i < ($this->inputs_limit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);
        }

        $projectMock['forms'][1]['inputs'][$this->inputs_limit] = $this->getInputMock($projectMock['forms'][1]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals($this->validator->errors['validation'][0], $this->error_too_many_questions);
    }

    public function testTooManyInputsThreeForms()
    {
        $project = new Project(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats
        );

        $projectMock = $this->getProjectMock();
        $projectMock['forms'][0] = $this->getFormMock($projectMock['ref'], 0);
        $projectMock['forms'][1] = $this->getFormMock($projectMock['ref'], 1);
        $projectMock['forms'][2] = $this->getFormMock($projectMock['ref'], 2);

        //add form name to use create() method
        $projectMock['form_name'] = 'just to pass method check';

        // Create new JSON Project Definition
        $project->create($projectMock['ref'], $projectMock);

        //reset extra property, not part of project definition
        unset($projectMock['form_name']);

        //add max inputs to first form
        for ($i = 0; $i < ($this->inputs_limit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);
            $projectMock['forms'][2]['inputs'][$i] = $this->getInputMock($projectMock['forms'][2]['ref']);
        }

        $projectMock['forms'][2]['inputs'][$this->inputs_limit] = $this->getInputMock($projectMock['forms'][2]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals($this->validator->errors['validation'][0], $this->error_too_many_questions);
    }

    public function testTooManyInputsFourForms()
    {
        $project = new Project(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats
        );

        $projectMock = $this->getProjectMock();
        $projectMock['forms'][0] = $this->getFormMock($projectMock['ref'], 0);
        $projectMock['forms'][1] = $this->getFormMock($projectMock['ref'], 1);
        $projectMock['forms'][2] = $this->getFormMock($projectMock['ref'], 2);
        $projectMock['forms'][3] = $this->getFormMock($projectMock['ref'], 3);

        //add form name to use create() method
        $projectMock['form_name'] = 'just to pass method check';

        // Create new JSON Project Definition
        $project->create($projectMock['ref'], $projectMock);

        //reset extra property, not part of project definition
        unset($projectMock['form_name']);

        //add max inputs to first form
        for ($i = 0; $i < ($this->inputs_limit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);
            $projectMock['forms'][2]['inputs'][$i] = $this->getInputMock($projectMock['forms'][2]['ref']);
            $projectMock['forms'][3]['inputs'][$i] = $this->getInputMock($projectMock['forms'][3]['ref']);
        }

        $projectMock['forms'][3]['inputs'][$this->inputs_limit] = $this->getInputMock($projectMock['forms'][3]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals($this->validator->errors['validation'][0], $this->error_too_many_questions);
    }

    public function testTooManyInputsFiveForms()
    {
        $project = new Project(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats
        );

        $projectMock = $this->getProjectMock();
        $projectMock['forms'][0] = $this->getFormMock($projectMock['ref'], 0);
        $projectMock['forms'][1] = $this->getFormMock($projectMock['ref'], 1);
        $projectMock['forms'][2] = $this->getFormMock($projectMock['ref'], 2);
        $projectMock['forms'][3] = $this->getFormMock($projectMock['ref'], 3);
        $projectMock['forms'][4] = $this->getFormMock($projectMock['ref'], 4);

        //add form name to use create() method
        $projectMock['form_name'] = 'just to pass method check';

        // Create new JSON Project Definition
        $project->create($projectMock['ref'], $projectMock);

        //reset extra property, not part of project definition
        unset($projectMock['form_name']);

        //add max inputs to first form
        for ($i = 0; $i < ($this->inputs_limit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);
            $projectMock['forms'][2]['inputs'][$i] = $this->getInputMock($projectMock['forms'][2]['ref']);
            $projectMock['forms'][3]['inputs'][$i] = $this->getInputMock($projectMock['forms'][3]['ref']);
        }

        $projectMock['forms'][3]['inputs'][$this->inputs_limit] = $this->getInputMock($projectMock['forms'][3]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals($this->validator->errors['validation'][0], $this->error_too_many_questions);
    }

    public function testOneGroupInputWithMaxInputs()
    {
        $project = new Project(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats
        );

        $projectMock = $this->getProjectMock();
        $projectMock['forms'][0] = $this->getFormMock($projectMock['ref'], 0);

        //add form name to use create() method
        $projectMock['form_name'] = 'just to pass method check';

        // Create new JSON Project Definition
        $project->create($projectMock['ref'], $projectMock);

        //reset extra property, not part of project definition
        unset($projectMock['form_name']);

        $projectMock['forms'][0]['inputs'][0] = $this->getInputMock($projectMock['forms'][0]['ref']);

        //override input type
        $projectMock['forms'][0]['inputs'][0]['type'] = 'group';

        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];

        //add max inputs to first form as branches
        for ($i = 0; $i < ($this->inputs_limit - 1); $i++) {
            $projectMock['forms'][0]['inputs'][0]['group'][$i] = $this->getInputMock($inputRef);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testOneBranchInputWithMaxInputs()
    {
        $project = new Project(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats
        );

        $projectMock = $this->getProjectMock();
        $projectMock['forms'][0] = $this->getFormMock($projectMock['ref'], 0);

        //add form name to use create() method
        $projectMock['form_name'] = 'just to pass method check';

        // Create new JSON Project Definition
        $project->create($projectMock['ref'], $projectMock);

        //reset extra property, not part of project definition
        unset($projectMock['form_name']);

        $projectMock['forms'][0]['inputs'][0] = $this->getInputMock($projectMock['forms'][0]['ref']);

        //override input type
        $projectMock['forms'][0]['inputs'][0]['type'] = 'branch';

        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];

        //add max inputs to first form as branches
        for ($i = 0; $i < ($this->inputs_limit - 1); $i++) {
            $projectMock['forms'][0]['inputs'][0]['branch'][$i] = $this->getInputMock($inputRef);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testOneBranchInputWithTooManyInputs()
    {
        $project = new Project(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats
        );

        $projectMock = $this->getProjectMock();
        $projectMock['forms'][0] = $this->getFormMock($projectMock['ref'], 0);

        //add form name to use create() method
        $projectMock['form_name'] = 'just to pass method check';

        // Create new JSON Project Definition
        $project->create($projectMock['ref'], $projectMock);

        //reset extra property, not part of project definition
        unset($projectMock['form_name']);

        $projectMock['forms'][0]['inputs'][0] = $this->getInputMock($projectMock['forms'][0]['ref']);

        //override input type
        $projectMock['forms'][0]['inputs'][0]['type'] = 'branch';

        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];

        //add max inputs to first form as branches
        for ($i = 0; $i < ($this->inputs_limit); $i++) {
            $projectMock['forms'][0]['inputs'][0]['branch'][$i] = $this->getInputMock($inputRef);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals($this->validator->errors['validation'][0], $this->error_too_many_questions);
    }

    public function testOneGroupInputTooManyInputs()
    {
        $project = new Project(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats
        );

        $projectMock = $this->getProjectMock();
        $projectMock['forms'][0] = $this->getFormMock($projectMock['ref'], 0);

        //add form name to use create() method
        $projectMock['form_name'] = 'just to pass method check';

        // Create new JSON Project Definition
        $project->create($projectMock['ref'], $projectMock);

        //reset extra property, not part of project definition
        unset($projectMock['form_name']);

        $projectMock['forms'][0]['inputs'][0] = $this->getInputMock($projectMock['forms'][0]['ref']);

        //override input type
        $projectMock['forms'][0]['inputs'][0]['type'] = 'group';

        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];

        //add max inputs to first form as branches
        for ($i = 0; $i < ($this->inputs_limit); $i++) {
            $projectMock['forms'][0]['inputs'][0]['group'][$i] = $this->getInputMock($inputRef);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals($this->validator->errors['validation'][0], $this->error_too_many_questions);
    }

    public function testOneNestedGroupWithMaxInputs()
    {
        $project = new Project(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats
        );

        $projectMock = $this->getProjectMock();
        $projectMock['forms'][0] = $this->getFormMock($projectMock['ref'], 0);

        //add form name to use create() method
        $projectMock['form_name'] = 'just to pass method check';

        // Create new JSON Project Definition
        $project->create($projectMock['ref'], $projectMock);

        //reset extra property, not part of project definition
        unset($projectMock['form_name']);

        $projectMock['forms'][0]['inputs'][0] = $this->getInputMock($projectMock['forms'][0]['ref']);

        //override input type
        $projectMock['forms'][0]['inputs'][0]['type'] = 'branch';

        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];

        //add a search input to the branch
        $projectMock['forms'][0]['inputs'][0]['branch'][0] = $this->getInputMock($inputRef);

        $branchRef = $projectMock['forms'][0]['inputs'][0]['branch'][0]['ref'];

        //override to nested group type
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['type'] = 'group';

        //add max inputs to nested group
        for ($i = 0; $i < ($this->inputs_limit - 2); $i++) {
            $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][$i] = $this->getInputMock($branchRef);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testOneNestedGroupWithTooManyInputs()
    {
        $project = new Project(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats
        );

        $projectMock = $this->getProjectMock();
        $projectMock['forms'][0] = $this->getFormMock($projectMock['ref'], 0);

        //add form name to use create() method
        $projectMock['form_name'] = 'just to pass method check';

        // Create new JSON Project Definition
        $project->create($projectMock['ref'], $projectMock);

        //reset extra property, not part of project definition
        unset($projectMock['form_name']);

        $projectMock['forms'][0]['inputs'][0] = $this->getInputMock($projectMock['forms'][0]['ref']);

        //override input type
        $projectMock['forms'][0]['inputs'][0]['type'] = 'branch';

        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];

        //add a search input to the branch
        $projectMock['forms'][0]['inputs'][0]['branch'][0] = $this->getInputMock($inputRef);

        $branchRef = $projectMock['forms'][0]['inputs'][0]['branch'][0]['ref'];

        //override to nested group type
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['type'] = 'group';

        //add max inputs to nested group
        for ($i = 0; $i < ($this->inputs_limit - 1); $i++) {
            $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][$i] = $this->getInputMock($branchRef);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals($this->validator->errors['validation'][0], $this->error_too_many_questions);
    }
}

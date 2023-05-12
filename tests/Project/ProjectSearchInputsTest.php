<?php

namespace Tests\Project;

use ec5\Http\Validation\Project\RuleProjectDefinition;
use Tests\TestCase;

use ec5\Http\Validation\Project\RuleProjectExtraDetails;
use ec5\Http\Validation\Project\RuleForm;
use ec5\Http\Validation\Project\RuleInput;

use ec5\Models\Projects\Project;
use ec5\Models\Projects\ProjectDefinition;
use ec5\Models\Projects\ProjectExtra;
use ec5\Models\Projects\ProjectMapping;
use ec5\Models\Projects\ProjectStats;

use Uuid;
use Config;
use Illuminate\Support\Str;

class ProjectSearchInputsTest extends TestCase
{
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
        $projectName = 'Test search inputs limit';

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

    private function getSearchInputMock($formRef)
    {
        //search types
        $searchTypes = ['searchsingle', 'searchmultiple'];

        return [
            'ref' => $formRef . '_' . uniqid(),
            'type' => $searchTypes[array_rand($searchTypes)], //randomly set the search type
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
            'possible_answers' => [
                [
                    'answer' => 'A possible answer',
                    'answer_ref' => uniqid()
                ]
            ],
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
    }

    public function testOneSearchInputOneForm()
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

        //add 1 search inputs to first form
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testTwoSearchInputsOneForm()
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

        //add 2 search inputs to first form
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][1] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testThreeSearchInputsOneForm()
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

        //add 3 search inputs to first form
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][1] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][2] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testFourSearchInputsOneForm()
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

        //add 4 search inputs to first form
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][1] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][2] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][3] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testFiveSearchInputsOneForm()
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

        //add 5 search inputs to first form
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][1] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][2] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][3] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][4] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testSixSearchInputsOneForm()
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

        //add 3 search inputs to first form
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][1] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][2] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][3] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][4] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][5] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        //should fail as 6 > limit
        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
    }

    public function testFiveSearchInputsFiveForms()
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

        //add 1 search input per each form
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][1]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][1]['ref']);
        $projectMock['forms'][2]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][2]['ref']);
        $projectMock['forms'][3]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][3]['ref']);
        $projectMock['forms'][4]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][4]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testSixSearchInputsFiveForm()
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

        //add 1 search input per each form, 2 on the last one
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][1]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][2]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][3]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][4]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][4]['inputs'][1] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        //should fail as 6 > limit
        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
    }

    public function testOneBranchInputWithFiveSearchInputs()
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

        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);

        //override input type
        $projectMock['forms'][0]['inputs'][0]['type'] = 'branch';

        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];

        //add 5 search inputs to first form as branches
        $projectMock['forms'][0]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][1] = $this->getSearchInputMock($inputRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][2] = $this->getSearchInputMock($inputRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][3] = $this->getSearchInputMock($inputRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][4] = $this->getSearchInputMock($inputRef);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testOneBranchInputWithSixSearchInputs()
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

        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);

        //override type as we need a branch input (I know, not so elegant)
        $projectMock['forms'][0]['inputs'][0]['type'] = 'branch';

        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];

        //add 6 search inputs to first form as branches
        $projectMock['forms'][0]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][1] = $this->getSearchInputMock($inputRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][2] = $this->getSearchInputMock($inputRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][3] = $this->getSearchInputMock($inputRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][4] = $this->getSearchInputMock($inputRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][5] = $this->getSearchInputMock($inputRef);

        // dd(count($projectMock['forms'][0]['inputs'][0]['branch']));

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
    }

    public function testFiveBranchInputsWithOneSearchInputEach()
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

        $formRef = $projectMock['forms'][0]['ref'];

        //add 6 branch inputs, with 1 search input each
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];
        $projectMock['forms'][0]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][1] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][1]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][1]['ref'];
        $projectMock['forms'][0]['inputs'][1]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][2] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][2]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][2]['ref'];
        $projectMock['forms'][0]['inputs'][2]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][3] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][3]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][3]['ref'];
        $projectMock['forms'][0]['inputs'][3]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][4] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][4]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][4]['ref'];
        $projectMock['forms'][0]['inputs'][4]['branch'][0] = $this->getSearchInputMock($inputRef);

        // dd(count($projectMock['forms'][0]['inputs'][0]['branch']));

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testSixBranchInputsWithOneSearchInputEach()
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

        $formRef = $projectMock['forms'][0]['ref'];

        //add 6 branch inputs, with 1 search input each
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];
        $projectMock['forms'][0]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][1] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][1]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][1]['ref'];
        $projectMock['forms'][0]['inputs'][1]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][2] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][2]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][2]['ref'];
        $projectMock['forms'][0]['inputs'][2]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][3] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][3]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][3]['ref'];
        $projectMock['forms'][0]['inputs'][3]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][4] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][4]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][4]['ref'];
        $projectMock['forms'][0]['inputs'][4]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][5] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][5]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][5]['ref'];
        $projectMock['forms'][0]['inputs'][5]['branch'][0] = $this->getSearchInputMock($inputRef);

        // dd(count($projectMock['forms'][0]['inputs'][0]['branch']));

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
    }

    public function testOneGroupInputWithFiveSearchInputs()
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

        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);

        //override type to group
        $projectMock['forms'][0]['inputs'][0]['type'] = 'group';

        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];

        //add 5 search inputs to first form as branches
        $projectMock['forms'][0]['inputs'][0]['group'][0] = $this->getSearchInputMock($inputRef);
        $projectMock['forms'][0]['inputs'][0]['group'][1] = $this->getSearchInputMock($inputRef);
        $projectMock['forms'][0]['inputs'][0]['group'][2] = $this->getSearchInputMock($inputRef);
        $projectMock['forms'][0]['inputs'][0]['group'][3] = $this->getSearchInputMock($inputRef);
        $projectMock['forms'][0]['inputs'][0]['group'][4] = $this->getSearchInputMock($inputRef);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testFiveGroupInputsWithOneSearchInputEach()
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

        $formRef = $projectMock['forms'][0]['ref'];

        //add 6 group inputs, with 1 search input each
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][0]['type'] = 'group';
        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];
        $projectMock['forms'][0]['inputs'][0]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][1] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][1]['type'] = 'group';
        $inputRef = $projectMock['forms'][0]['inputs'][1]['ref'];
        $projectMock['forms'][0]['inputs'][1]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][2] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][2]['type'] = 'group';
        $inputRef = $projectMock['forms'][0]['inputs'][2]['ref'];
        $projectMock['forms'][0]['inputs'][2]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][3] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][3]['type'] = 'group';
        $inputRef = $projectMock['forms'][0]['inputs'][3]['ref'];
        $projectMock['forms'][0]['inputs'][3]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][4] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][4]['type'] = 'group';
        $inputRef = $projectMock['forms'][0]['inputs'][4]['ref'];
        $projectMock['forms'][0]['inputs'][4]['group'][0] = $this->getSearchInputMock($inputRef);

        // dd(count($projectMock['forms'][0]['inputs'][0]['branch']));

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testSixGroupInputsWithOneSearchInputEach()
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

        $formRef = $projectMock['forms'][0]['ref'];

        //add 6 group inputs, with 1 search input each
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][0]['type'] = 'group';
        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];
        $projectMock['forms'][0]['inputs'][0]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][1] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][1]['type'] = 'group';
        $inputRef = $projectMock['forms'][0]['inputs'][1]['ref'];
        $projectMock['forms'][0]['inputs'][1]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][2] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][2]['type'] = 'group';
        $inputRef = $projectMock['forms'][0]['inputs'][2]['ref'];
        $projectMock['forms'][0]['inputs'][2]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][3] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][3]['type'] = 'group';
        $inputRef = $projectMock['forms'][0]['inputs'][3]['ref'];
        $projectMock['forms'][0]['inputs'][3]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][4] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][4]['type'] = 'group';
        $inputRef = $projectMock['forms'][0]['inputs'][4]['ref'];
        $projectMock['forms'][0]['inputs'][4]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][0]['inputs'][5] = $this->getSearchInputMock($formRef);
        $projectMock['forms'][0]['inputs'][5]['type'] = 'group';
        $inputRef = $projectMock['forms'][0]['inputs'][5]['ref'];
        $projectMock['forms'][0]['inputs'][5]['group'][0] = $this->getSearchInputMock($inputRef);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
    }

    public function testOneNestedGroupWithValidTotalOfSearchInputs()
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

        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);

        //override input type
        $projectMock['forms'][0]['inputs'][0]['type'] = 'branch';

        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];

        //add a search input to the branch
        $projectMock['forms'][0]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);

        $branchRef = $projectMock['forms'][0]['inputs'][0]['branch'][0]['ref'];

        //override to nested group type
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['type'] = 'group';

        //add 5 search inputs to nested group
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][0] = $this->getSearchInputMock($branchRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][1] = $this->getSearchInputMock($branchRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][2] = $this->getSearchInputMock($branchRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][3] = $this->getSearchInputMock($branchRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][4] = $this->getSearchInputMock($branchRef);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testOneNestedGroupWithTooManySearchInputs()
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

        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);

        //override input type
        $projectMock['forms'][0]['inputs'][0]['type'] = 'branch';

        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];

        //add a search input to the branch
        $projectMock['forms'][0]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);

        $branchRef = $projectMock['forms'][0]['inputs'][0]['branch'][0]['ref'];

        //override to nested group type
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['type'] = 'group';

        //add 5 search inputs to nested group
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][0] = $this->getSearchInputMock($branchRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][1] = $this->getSearchInputMock($branchRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][2] = $this->getSearchInputMock($branchRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][3] = $this->getSearchInputMock($branchRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][4] = $this->getSearchInputMock($branchRef);
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][5] = $this->getSearchInputMock($branchRef);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
    }

    public function testFiveFormsEachWithAGroupHavingOneSearchInput()
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

        //add 1 branch input per each form
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][0]['type'] = 'group';
        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];
        $projectMock['forms'][0]['inputs'][0]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][1]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][1]['ref']);
        $projectMock['forms'][1]['inputs'][0]['type'] = 'group';
        $inputRef = $projectMock['forms'][1]['inputs'][0]['ref'];
        $projectMock['forms'][1]['inputs'][0]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][2]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][2]['ref']);
        $projectMock['forms'][2]['inputs'][0]['type'] = 'group';
        $inputRef = $projectMock['forms'][2]['inputs'][0]['ref'];
        $projectMock['forms'][2]['inputs'][0]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][3]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][3]['ref']);
        $projectMock['forms'][3]['inputs'][0]['type'] = 'group';
        $inputRef = $projectMock['forms'][3]['inputs'][0]['ref'];
        $projectMock['forms'][3]['inputs'][0]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][4]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][4]['ref']);
        $projectMock['forms'][4]['inputs'][0]['type'] = 'group';
        $inputRef = $projectMock['forms'][4]['inputs'][0]['ref'];
        $projectMock['forms'][4]['inputs'][0]['group'][0] = $this->getSearchInputMock($inputRef);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testFiveFormsEachWithTooManyGroupsHavingOneSearchInput()
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

        //add 1 branch input per each form
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][0]['type'] = 'group';
        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];
        $projectMock['forms'][0]['inputs'][0]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][1]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][1]['ref']);
        $projectMock['forms'][1]['inputs'][0]['type'] = 'group';
        $inputRef = $projectMock['forms'][1]['inputs'][0]['ref'];
        $projectMock['forms'][1]['inputs'][0]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][2]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][2]['ref']);
        $projectMock['forms'][2]['inputs'][0]['type'] = 'group';
        $inputRef = $projectMock['forms'][2]['inputs'][0]['ref'];
        $projectMock['forms'][2]['inputs'][0]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][3]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][3]['ref']);
        $projectMock['forms'][3]['inputs'][0]['type'] = 'group';
        $inputRef = $projectMock['forms'][3]['inputs'][0]['ref'];
        $projectMock['forms'][3]['inputs'][0]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][4]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][4]['ref']);
        $projectMock['forms'][4]['inputs'][0]['type'] = 'group';
        $inputRef = $projectMock['forms'][4]['inputs'][0]['ref'];
        $projectMock['forms'][4]['inputs'][0]['group'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][4]['inputs'][1] = $this->getSearchInputMock($projectMock['forms'][4]['ref']);
        $projectMock['forms'][4]['inputs'][1]['type'] = 'group';
        $inputRef = $projectMock['forms'][4]['inputs'][1]['ref'];
        $projectMock['forms'][4]['inputs'][1]['group'][0] = $this->getSearchInputMock($inputRef);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
    }

    public function testFiveFormsEachWithABranchHavingOneSearchInput()
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

        //add 1 branch input per each form
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];
        $projectMock['forms'][0]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][1]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][1]['ref']);
        $projectMock['forms'][1]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][1]['inputs'][0]['ref'];
        $projectMock['forms'][1]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][2]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][2]['ref']);
        $projectMock['forms'][2]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][2]['inputs'][0]['ref'];
        $projectMock['forms'][2]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][3]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][3]['ref']);
        $projectMock['forms'][3]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][3]['inputs'][0]['ref'];
        $projectMock['forms'][3]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][4]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][4]['ref']);
        $projectMock['forms'][4]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][4]['inputs'][0]['ref'];
        $projectMock['forms'][4]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testFiveFormsEachWithTooManyBranchesHavingOneSearchInput()
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

        //add 1 branch input per each form
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];
        $projectMock['forms'][0]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][1]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][1]['ref']);
        $projectMock['forms'][1]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][1]['inputs'][0]['ref'];
        $projectMock['forms'][1]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][2]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][2]['ref']);
        $projectMock['forms'][2]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][2]['inputs'][0]['ref'];
        $projectMock['forms'][2]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][3]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][3]['ref']);
        $projectMock['forms'][3]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][3]['inputs'][0]['ref'];
        $projectMock['forms'][3]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);

        $projectMock['forms'][4]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][4]['ref']);
        $projectMock['forms'][4]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][4]['inputs'][0]['ref'];
        $projectMock['forms'][4]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);
        $projectMock['forms'][4]['inputs'][0]['branch'][1] = $this->getSearchInputMock($inputRef);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
    }

    public function testFiveFormsEachWithABranchHavingOneNestedGroupWithOneSearchInput()
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

        //add 1 branch input per each form and a group within each branch
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];
        $projectMock['forms'][0]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);
        $branchInputRef =  $projectMock['forms'][0]['inputs'][0]['branch'][0]['ref'];
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['type'] = 'group';
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][0] = $this->getSearchInputMock($branchInputRef);


        $projectMock['forms'][1]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][1]['ref']);
        $projectMock['forms'][1]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][1]['inputs'][0]['ref'];
        $projectMock['forms'][1]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);
        $branchInputRef =  $projectMock['forms'][1]['inputs'][0]['branch'][0]['ref'];
        $projectMock['forms'][1]['inputs'][0]['branch'][0]['type'] = 'group';
        $projectMock['forms'][1]['inputs'][0]['branch'][0]['group'][0] = $this->getSearchInputMock($branchInputRef);


        $projectMock['forms'][2]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][2]['ref']);
        $projectMock['forms'][2]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][2]['inputs'][0]['ref'];
        $projectMock['forms'][2]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);
        $branchInputRef =  $projectMock['forms'][2]['inputs'][0]['branch'][0]['ref'];
        $projectMock['forms'][2]['inputs'][0]['branch'][0]['type'] = 'group';
        $projectMock['forms'][2]['inputs'][0]['branch'][0]['group'][0] = $this->getSearchInputMock($branchInputRef);


        $projectMock['forms'][3]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][3]['ref']);
        $projectMock['forms'][3]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][3]['inputs'][0]['ref'];
        $projectMock['forms'][3]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);
        $branchInputRef =  $projectMock['forms'][3]['inputs'][0]['branch'][0]['ref'];
        $projectMock['forms'][3]['inputs'][0]['branch'][0]['type'] = 'group';
        $projectMock['forms'][3]['inputs'][0]['branch'][0]['group'][0] = $this->getSearchInputMock($branchInputRef);


        $projectMock['forms'][4]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][4]['ref']);
        $projectMock['forms'][4]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][4]['inputs'][0]['ref'];
        $projectMock['forms'][4]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);
        $branchInputRef =  $projectMock['forms'][4]['inputs'][0]['branch'][0]['ref'];
        $projectMock['forms'][4]['inputs'][0]['branch'][0]['type'] = 'group';
        $projectMock['forms'][4]['inputs'][0]['branch'][0]['group'][0] = $this->getSearchInputMock($branchInputRef);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testFiveFormsEachWithABranchHavingTooManyNestedGroupsWithOneSearchInput()
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

        //add 1 branch input per each form and a group within each branch
        $projectMock['forms'][0]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][0]['inputs'][0]['ref'];
        $projectMock['forms'][0]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);
        $branchInputRef =  $projectMock['forms'][0]['inputs'][0]['branch'][0]['ref'];
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['type'] = 'group';
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][0] = $this->getSearchInputMock($branchInputRef);


        $projectMock['forms'][1]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][1]['ref']);
        $projectMock['forms'][1]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][1]['inputs'][0]['ref'];
        $projectMock['forms'][1]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);
        $branchInputRef =  $projectMock['forms'][1]['inputs'][0]['branch'][0]['ref'];
        $projectMock['forms'][1]['inputs'][0]['branch'][0]['type'] = 'group';
        $projectMock['forms'][1]['inputs'][0]['branch'][0]['group'][0] = $this->getSearchInputMock($branchInputRef);


        $projectMock['forms'][2]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][2]['ref']);
        $projectMock['forms'][2]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][2]['inputs'][0]['ref'];
        $projectMock['forms'][2]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);
        $branchInputRef =  $projectMock['forms'][2]['inputs'][0]['branch'][0]['ref'];
        $projectMock['forms'][2]['inputs'][0]['branch'][0]['type'] = 'group';
        $projectMock['forms'][2]['inputs'][0]['branch'][0]['group'][0] = $this->getSearchInputMock($branchInputRef);


        $projectMock['forms'][3]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][3]['ref']);
        $projectMock['forms'][3]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][3]['inputs'][0]['ref'];
        $projectMock['forms'][3]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);
        $branchInputRef =  $projectMock['forms'][3]['inputs'][0]['branch'][0]['ref'];
        $projectMock['forms'][3]['inputs'][0]['branch'][0]['type'] = 'group';
        $projectMock['forms'][3]['inputs'][0]['branch'][0]['group'][0] = $this->getSearchInputMock($branchInputRef);


        $projectMock['forms'][4]['inputs'][0] = $this->getSearchInputMock($projectMock['forms'][4]['ref']);
        $projectMock['forms'][4]['inputs'][0]['type'] = 'branch';
        $inputRef = $projectMock['forms'][4]['inputs'][0]['ref'];
        $projectMock['forms'][4]['inputs'][0]['branch'][0] = $this->getSearchInputMock($inputRef);
        $branchInputRef =  $projectMock['forms'][4]['inputs'][0]['branch'][0]['ref'];
        $projectMock['forms'][4]['inputs'][0]['branch'][0]['type'] = 'group';
        $projectMock['forms'][4]['inputs'][0]['branch'][0]['group'][0] = $this->getSearchInputMock($branchInputRef);
        $projectMock['forms'][4]['inputs'][0]['branch'][0]['group'][1] = $this->getSearchInputMock($branchInputRef);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->validator->validate($project);
        $this->assertTrue($this->validator->hasErrors());
    }
}

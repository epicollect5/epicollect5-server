<?php

namespace Tests\Http\Validation\Project;

use ec5\DTO\ProjectDefinitionDTO;
use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectExtraDTO;
use ec5\DTO\ProjectMappingDTO;
use ec5\DTO\ProjectStatsDTO;
use ec5\Http\Validation\Project\RuleForm;
use ec5\Http\Validation\Project\RuleInput;
use ec5\Http\Validation\Project\RuleProjectDefinition;
use ec5\Http\Validation\Project\RuleProjectExtraDetails;
use ec5\Services\Mapping\ProjectMappingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class RuleProjectDefinitionTest extends TestCase
{
    use DatabaseTransactions;

    protected $validator;
    protected $project;
    protected $ruleProjectExtraDetails;
    protected $ruleForm;
    protected $ruleInput;
    protected $projectExtra;
    protected $projectMapping;
    protected $projectStats;
    protected $projectDefinition;
    protected $projectExtraCreateMethod;
    protected $inputs_limit;

    protected static function getProjectExtraMethod($name): \ReflectionMethod
    {
        $class = new \ReflectionClass('\ec5\DTO\ProjectExtraDTO');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    private function getProjectMock(): array
    {
        $projectRef = str_replace('-', '', Uuid::uuid4()->toString());
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
            'category' => config('epicollect.strings.project_categories.general'),
            'forms' => []
        ];
    }

    private function getFormMock($projectRef, $formIndex): array
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

    private function getInputMock($formRef): array
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

    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();

        $this->ruleProjectExtraDetails = new  RuleProjectExtraDetails();
        $this->ruleForm = new RuleForm();
        $this->ruleInput = new RuleInput();
        $this->projectExtra = new ProjectExtraDTO();
        $this->projectMapping = new ProjectMappingDTO();
        $this->projectStats = new ProjectStatsDTO();
        $this->projectDefinition = new ProjectDefinitionDTO();

        $this->validator = new RuleProjectDefinition(
            $this->ruleProjectExtraDetails,
            $this->ruleForm,
            $this->ruleInput,
            $this->projectExtra,
            $this->projectDefinition
        );

        $this->inputs_limit = config('epicollect.limits.formlimits.inputs');
    }

    public function test_max_inputs_one_form()
    {
        $project = new ProjectDTO(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats,
            new ProjectMappingService()
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

    public function test_too_many_inputs_one_form()
    {
        $project = new ProjectDTO(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats,
            new ProjectMappingService()
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
        $this->assertEquals($this->validator->errors['validation'][0], 'ec5_262');
    }

    public function test_too_many_inputs_two_forms()
    {
        $project = new ProjectDTO(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats,
            new ProjectMappingService()
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
        $this->assertEquals($this->validator->errors['validation'][0], 'ec5_262');
    }

    public function test_too_many_inputs_three_forms()
    {
        $project = new ProjectDTO(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats,
            new ProjectMappingService()
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
        $this->assertEquals($this->validator->errors['validation'][0], 'ec5_262');
    }

    public function test_too_many_inputs_four_forms()
    {
        $project = new ProjectDTO(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats,
            new ProjectMappingService()
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
        $this->assertEquals($this->validator->errors['validation'][0], 'ec5_262');
    }

    public function test_too_many_inputs_five_forms()
    {
        $project = new ProjectDTO(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats,
            new ProjectMappingService()
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
        $this->assertEquals($this->validator->errors['validation'][0], 'ec5_262');
    }

    public function test_one_group_input_with_max_inputs()
    {
        $project = new ProjectDTO(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats,
            new ProjectMappingService()
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

    public function test_one_branch_input_with_max_inputs()
    {
        $project = new ProjectDTO(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats,
            new ProjectMappingService()
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

    public function test_one_branch_input_with_too_many_inputs()
    {
        $project = new ProjectDTO(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats,
            new ProjectMappingService()
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
        $this->assertEquals($this->validator->errors['validation'][0], 'ec5_262');
    }

    public function test_one_group_input_too_many_inputs()
    {
        $project = new ProjectDTO(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats,
            new ProjectMappingService()
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
        $this->assertEquals($this->validator->errors['validation'][0], 'ec5_262');
    }

    public function test_one_nested_group_with_max_inputs()
    {
        $project = new ProjectDTO(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats,
            new ProjectMappingService()
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

    public function test_one_nested_group_with_too_many_inputs()
    {
        $project = new ProjectDTO(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats,
            new ProjectMappingService()
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

        //override to the nested group type
        $projectMock['forms'][0]['inputs'][0]['branch'][0]['type'] = 'group';

        //add max inputs to the nested group
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
        $this->assertEquals('ec5_262', $this->validator->errors['validation'][0]);
    }
}

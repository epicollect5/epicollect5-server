<?php

namespace Tests\Http\Validation\Project\RuleProjectDefinition;

use ec5\DTO\ProjectDTO;
use ec5\Services\Mapping\ProjectMappingService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Throwable;

class MaxInputsTest extends RuleProjectDefinitionBaseTest
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @throws Exception
     */
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
        for ($i = 0; $i < $this->inputsLimit; $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertFalse($this->ruleProjectDefinition->hasErrors());
    }

    /**
     * @throws Throwable
     */
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
        for ($i = 0; $i < ($this->inputsLimit + 1); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_262', $this->ruleProjectDefinition->errors['validation'][0]);
    }

    /**
     * @throws Exception
     */
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
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);
        }

        $projectMock['forms'][1]['inputs'][$this->inputsLimit] = $this->getInputMock($projectMock['forms'][1]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_262', $this->ruleProjectDefinition->errors['validation'][0]);
    }

    /**
     * @throws Exception
     */
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
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);
            $projectMock['forms'][2]['inputs'][$i] = $this->getInputMock($projectMock['forms'][2]['ref']);
        }

        $projectMock['forms'][2]['inputs'][$this->inputsLimit] = $this->getInputMock($projectMock['forms'][2]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_262', $this->ruleProjectDefinition->errors['validation'][0]);
    }

    /**
     * @throws Exception
     */
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
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);
            $projectMock['forms'][2]['inputs'][$i] = $this->getInputMock($projectMock['forms'][2]['ref']);
            $projectMock['forms'][3]['inputs'][$i] = $this->getInputMock($projectMock['forms'][3]['ref']);
        }

        $projectMock['forms'][3]['inputs'][$this->inputsLimit] = $this->getInputMock($projectMock['forms'][3]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_262', $this->ruleProjectDefinition->errors['validation'][0]);
    }

    /**
     * @throws Exception
     */
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
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);
            $projectMock['forms'][2]['inputs'][$i] = $this->getInputMock($projectMock['forms'][2]['ref']);
            $projectMock['forms'][3]['inputs'][$i] = $this->getInputMock($projectMock['forms'][3]['ref']);
        }

        $projectMock['forms'][3]['inputs'][$this->inputsLimit] = $this->getInputMock($projectMock['forms'][3]['ref']);

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_262', $this->ruleProjectDefinition->errors['validation'][0]);
    }

    /**
     * @throws Exception
     */
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
        for ($i = 0; $i < ($this->inputsLimit - 1); $i++) {
            $projectMock['forms'][0]['inputs'][0]['group'][$i] = $this->getInputMock($inputRef);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertFalse($this->ruleProjectDefinition->hasErrors());
    }

    /**
     * @throws Exception
     */
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
        for ($i = 0; $i < ($this->inputsLimit - 1); $i++) {
            $projectMock['forms'][0]['inputs'][0]['branch'][$i] = $this->getInputMock($inputRef);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertFalse($this->ruleProjectDefinition->hasErrors());
    }

    /**
     * @throws Exception
     */
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
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][0]['branch'][$i] = $this->getInputMock($inputRef);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_262', $this->ruleProjectDefinition->errors['validation'][0]);
    }

    /**
     * @throws Exception
     */
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
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][0]['group'][$i] = $this->getInputMock($inputRef);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_262', $this->ruleProjectDefinition->errors['validation'][0]);
    }

    /**
     * @throws Exception
     */
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
        for ($i = 0; $i < ($this->inputsLimit - 2); $i++) {
            $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][$i] = $this->getInputMock($branchRef);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertFalse($this->ruleProjectDefinition->hasErrors());
    }

    /**
     * @throws Exception
     */
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
        for ($i = 0; $i < ($this->inputsLimit - 1); $i++) {
            $projectMock['forms'][0]['inputs'][0]['branch'][0]['group'][$i] = $this->getInputMock($branchRef);
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_262', $this->ruleProjectDefinition->errors['validation'][0]);
    }
}

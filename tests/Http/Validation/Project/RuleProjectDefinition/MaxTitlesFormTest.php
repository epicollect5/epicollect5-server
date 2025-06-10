<?php

namespace Tests\Http\Validation\Project\RuleProjectDefinition;

use ec5\DTO\ProjectDTO;
use ec5\Services\Mapping\ProjectMappingService;
use Exception;

class MaxTitlesFormTest extends RuleProjectDefinitionBaseTest
{
    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
    }

    /**
     * @throws Exception
     */
    public function test_correct_number_of_titles_one_form()
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

        //add  inputs to first form
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);

            //add title to each input up to max
            if ($i < config('epicollect.limits.titlesMaxCount')) {
                $projectMock['forms'][0]['inputs'][$i]['is_title'] = true;
            }
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
    public function test_correct_number_of_titles_two_forms()
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

        //add  inputs to first form
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);

            //add title to each input up to max
            if ($i < config('epicollect.limits.titlesMaxCount')) {
                $projectMock['forms'][0]['inputs'][$i]['is_title'] = true;
                $projectMock['forms'][1]['inputs'][$i]['is_title'] = true;
            }
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
    public function test_correct_number_of_titles_three_forms()
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

        //add  inputs to first form
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);
            $projectMock['forms'][2]['inputs'][$i] = $this->getInputMock($projectMock['forms'][2]['ref']);

            //add title to each input up to max
            if ($i < config('epicollect.limits.titlesMaxCount')) {
                $projectMock['forms'][0]['inputs'][$i]['is_title'] = true;
                $projectMock['forms'][1]['inputs'][$i]['is_title'] = true;
                $projectMock['forms'][2]['inputs'][$i]['is_title'] = true;
            }
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
    public function test_correct_number_of_titles_four_forms()
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

        //add  inputs to first form
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);
            $projectMock['forms'][2]['inputs'][$i] = $this->getInputMock($projectMock['forms'][2]['ref']);
            $projectMock['forms'][3]['inputs'][$i] = $this->getInputMock($projectMock['forms'][3]['ref']);

            //add title to each input up to max
            if ($i < config('epicollect.limits.titlesMaxCount')) {
                $projectMock['forms'][0]['inputs'][$i]['is_title'] = true;
                $projectMock['forms'][1]['inputs'][$i]['is_title'] = true;
                $projectMock['forms'][2]['inputs'][$i]['is_title'] = true;
                $projectMock['forms'][3]['inputs'][$i]['is_title'] = true;
            }
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
    public function test_correct_number_of_titles_five_forms()
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

        //add  inputs to first form
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);
            $projectMock['forms'][2]['inputs'][$i] = $this->getInputMock($projectMock['forms'][2]['ref']);
            $projectMock['forms'][3]['inputs'][$i] = $this->getInputMock($projectMock['forms'][3]['ref']);
            $projectMock['forms'][4]['inputs'][$i] = $this->getInputMock($projectMock['forms'][4]['ref']);

            //add title to each input up to max
            if ($i < config('epicollect.limits.titlesMaxCount')) {
                $projectMock['forms'][0]['inputs'][$i]['is_title'] = true;
                $projectMock['forms'][1]['inputs'][$i]['is_title'] = true;
                $projectMock['forms'][2]['inputs'][$i]['is_title'] = true;
                $projectMock['forms'][3]['inputs'][$i]['is_title'] = true;
                $projectMock['forms'][4]['inputs'][$i]['is_title'] = true;
            }
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
    public function test_too_many_titles_form_one()
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

        //add  inputs to first form
        $inputRef = '';
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);

            //add title to each input beyond limit
            if ($i < (config('epicollect.limits.titlesMaxCount') + 1)) {
                $projectMock['forms'][0]['inputs'][$i]['is_title'] = true;
                $inputRef = $projectMock['forms'][0]['inputs'][$i]['ref'];
            }
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_211', $this->ruleProjectDefinition->errors[$inputRef][0]);
    }

    /**
     * @throws Exception
     */
    public function test_too_many_titles_form_two()
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

        //add  inputs to first form
        $inputRef = '';
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);


            //add title to each input beyond limit only to form two
            if ($i < (config('epicollect.limits.titlesMaxCount') + 1)) {
                $projectMock['forms'][1]['inputs'][$i]['is_title'] = true;
                $inputRef = $projectMock['forms'][1]['inputs'][$i]['ref'];
            }
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_211', $this->ruleProjectDefinition->errors[$inputRef][0]);
    }

    /**
     * @throws Exception
     */
    public function test_too_many_titles_form_three()
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

        //add  inputs to first form
        $inputRef = '';
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);
            $projectMock['forms'][2]['inputs'][$i] = $this->getInputMock($projectMock['forms'][2]['ref']);


            //add title to each input beyond limit only to form two
            if ($i < (config('epicollect.limits.titlesMaxCount') + 1)) {
                $projectMock['forms'][2]['inputs'][$i]['is_title'] = true;
                $inputRef = $projectMock['forms'][2]['inputs'][$i]['ref'];
            }
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_211', $this->ruleProjectDefinition->errors[$inputRef][0]);
    }

    /**
     * @throws Exception
     */
    public function test_too_many_titles_form_four()
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

        //add  inputs to first form
        $inputRef = '';
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);
            $projectMock['forms'][2]['inputs'][$i] = $this->getInputMock($projectMock['forms'][2]['ref']);
            $projectMock['forms'][3]['inputs'][$i] = $this->getInputMock($projectMock['forms'][3]['ref']);

            //add title to each input beyond limit only to form two
            if ($i < (config('epicollect.limits.titlesMaxCount') + 1)) {
                $projectMock['forms'][3]['inputs'][$i]['is_title'] = true;
                $inputRef = $projectMock['forms'][3]['inputs'][$i]['ref'];
            }
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_211', $this->ruleProjectDefinition->errors[$inputRef][0]);
    }

    /**
     * @throws Exception
     */
    public function test_too_many_titles_form_five()
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

        //add  inputs to first form
        $inputRef = '';
        for ($i = 0; $i < ($this->inputsLimit); $i++) {
            $projectMock['forms'][0]['inputs'][$i] = $this->getInputMock($projectMock['forms'][0]['ref']);
            $projectMock['forms'][1]['inputs'][$i] = $this->getInputMock($projectMock['forms'][1]['ref']);
            $projectMock['forms'][2]['inputs'][$i] = $this->getInputMock($projectMock['forms'][2]['ref']);
            $projectMock['forms'][3]['inputs'][$i] = $this->getInputMock($projectMock['forms'][3]['ref']);
            $projectMock['forms'][4]['inputs'][$i] = $this->getInputMock($projectMock['forms'][4]['ref']);

            //add title to each input beyond limit only to form two
            if ($i < (config('epicollect.limits.titlesMaxCount') + 1)) {
                $projectMock['forms'][4]['inputs'][$i]['is_title'] = true;
                $inputRef = $projectMock['forms'][4]['inputs'][$i]['ref'];
            }
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_211', $this->ruleProjectDefinition->errors[$inputRef][0]);
    }
}

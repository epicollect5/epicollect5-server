<?php

namespace Tests\Http\Validation\Project\RuleProjectDefinition;

use ec5\DTO\ProjectDTO;
use ec5\Services\Mapping\ProjectMappingService;
use Exception;

class MaxTitlesGroupTest extends RuleProjectDefinitionBaseTest
{
    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
    }



    /**
     * @throws Exception
     */
    public function test_correct_number_of_titles_group_in_parent_form()
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

        //add inputs to first form
        for ($i = 0; $i < 5; $i++) {
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

        //add first group as 5th input, first 3 inputs of the form are set as titles
        $projectMock['forms'][0]['inputs'][4]['type'] = 'group';
        //add 50 branch inputs
        for ($i = 0; $i < 5; $i++) {
            $groupRef =  $projectMock['forms'][0]['inputs'][4]['ref'];
            $projectMock['forms'][0]['inputs'][4]['group'][$i] = $this->getInputMock($groupRef);

            //add title to each input up to max
            if ($i < config('epicollect.limits.titlesMaxCount')) {
                $projectMock['forms'][0]['inputs'][4]['group'][$i]['is_title'] = true;
            }
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertFalse($this->ruleProjectDefinition->hasErrors());


        //add one more title input to the branch
        $projectMock['forms'][0]['inputs'][4]['group'][5] = $this->getInputMock($groupRef);
        $projectMock['forms'][0]['inputs'][4]['group'][5]['is_title'] = true;

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        //validate and check for errors
        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_211', $this->ruleProjectDefinition->errors[$projectMock['forms'][0]['inputs'][4]['group'][5]['ref']][0]);

        //remove title from branch input
        $projectMock['forms'][0]['inputs'][4]['group'][5]['is_title'] = false;
        //reset errors
        $this->ruleProjectDefinition->errors = [];

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        //validate and check for no errors
        $this->ruleProjectDefinition->validate($project);
        $this->assertFalse($this->ruleProjectDefinition->hasErrors());

        //add another branch input
        $projectMock['forms'][0]['inputs'][5] = $this->getInputMock($projectMock['forms'][0]['ref']);
        $projectMock['forms'][0]['inputs'][5]['type'] = 'group';
        //add 50 branch inputs
        for ($i = 0; $i < 5; $i++) {
            $groupRef =  $projectMock['forms'][0]['inputs'][5]['ref'];
            $projectMock['forms'][0]['inputs'][5]['group'][$i] = $this->getInputMock($groupRef);

            //add title to each input up to max
            if ($i < config('epicollect.limits.titlesMaxCount')) {
                $projectMock['forms'][0]['inputs'][5]['group'][$i]['is_title'] = true;
            }
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        //validate and check for no errors
        $this->ruleProjectDefinition->validate($project);
        $this->assertFalse($this->ruleProjectDefinition->hasErrors());

        //add one more title input to the branch
        $projectMock['forms'][0]['inputs'][5]['group'][5] = $this->getInputMock($groupRef);
        $projectMock['forms'][0]['inputs'][5]['group'][5]['is_title'] = true;

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        //validate and check for errors
        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_211', $this->ruleProjectDefinition->errors[$projectMock['forms'][0]['inputs'][5]['group'][5]['ref']][0]);
    }

    /**
     * @throws Exception
     */
    public function test_correct_number_of_titles_group_in_child_form()
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

        //add inputs to forms
        for ($i = 0; $i < 5; $i++) {
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

        //Child form add first group as 5th input, first 3 inputs of the form are set as titles
        $projectMock['forms'][1]['inputs'][4]['type'] = 'group';
        //add 5 group inputs
        for ($i = 0; $i < 5; $i++) {
            $groupRef =  $projectMock['forms'][1]['inputs'][4]['ref'];
            $projectMock['forms'][1]['inputs'][4]['group'][$i] = $this->getInputMock($groupRef);

            //add title to each input up to max
            if ($i < config('epicollect.limits.titlesMaxCount')) {
                $projectMock['forms'][1]['inputs'][4]['group'][$i]['is_title'] = true;
            }
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertFalse($this->ruleProjectDefinition->hasErrors());


        //add one more title input to the group
        $projectMock['forms'][1]['inputs'][4]['group'][5] = $this->getInputMock($groupRef);
        $projectMock['forms'][1]['inputs'][4]['group'][5]['is_title'] = true;

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        //validate and check for errors
        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_211', $this->ruleProjectDefinition->errors[$projectMock['forms'][1]['inputs'][4]['group'][5]['ref']][0]);

        //remove title from group input
        $projectMock['forms'][1]['inputs'][4]['group'][5]['is_title'] = false;
        //reset errors
        $this->ruleProjectDefinition->errors = [];

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        //validate and check for no errors
        $this->ruleProjectDefinition->validate($project);
        $this->assertFalse($this->ruleProjectDefinition->hasErrors());

        //add another branch input
        $projectMock['forms'][1]['inputs'][5] = $this->getInputMock($projectMock['forms'][1]['ref']);
        $projectMock['forms'][1]['inputs'][5]['type'] = 'group';
        //add 50 branch inputs
        for ($i = 0; $i < 5; $i++) {
            $groupRef =  $projectMock['forms'][1]['inputs'][5]['ref'];
            $projectMock['forms'][1]['inputs'][5]['group'][$i] = $this->getInputMock($groupRef);

            //add title to each input up to max
            if ($i < config('epicollect.limits.titlesMaxCount')) {
                $projectMock['forms'][1]['inputs'][5]['group'][$i]['is_title'] = true;
            }
        }

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        //validate and check for no errors
        $this->ruleProjectDefinition->validate($project);
        $this->assertFalse($this->ruleProjectDefinition->hasErrors());

        //add one more title input to the group
        $projectMock['forms'][1]['inputs'][5]['group'][5] = $this->getInputMock($groupRef);
        $projectMock['forms'][1]['inputs'][5]['group'][5]['is_title'] = true;

        //add inputs to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        //validate and check for errors
        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_211', $this->ruleProjectDefinition->errors[$projectMock['forms'][1]['inputs'][5]['group'][5]['ref']][0]);
    }

}

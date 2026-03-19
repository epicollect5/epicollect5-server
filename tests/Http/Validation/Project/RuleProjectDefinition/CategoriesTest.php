<?php

namespace Http\Validation\Project\RuleProjectDefinition;

use ec5\DTO\ProjectDTO;
use ec5\Services\Mapping\ProjectMappingService;
use Exception;
use Tests\Http\Validation\Project\RuleProjectDefinition\RuleProjectDefinitionBaseTest;
use Throwable;

class CategoriesTest extends RuleProjectDefinitionBaseTest
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @throws Exception
     */
    public function test_it_should_catch_invalid_category()
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
        $projectMock['category'] = 'invalid_category';
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);


        $project->category = 'invalid_category';
        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());

        //assert error is ec5_408
        $this->assertEquals('ec5_408', $this->ruleProjectDefinition->errors['validation'][0]);
    }

    /**
    * @throws Throwable
    */
    public function test_it_should_pass_valid_category()
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

        $categories = array_keys(config('epicollect.strings.project_categories'));
        foreach ($categories as $category) {
            //add inputs an to project definition
            $projectMock['category'] = $category;
            $project->addProjectDefinition([
                'id' => $projectMock['ref'],
                'project' => $projectMock
            ]);

            $project->category = $category;
            $this->ruleProjectDefinition->validate($project);
            $this->assertFalse($this->ruleProjectDefinition->hasErrors());
        }
    }
}

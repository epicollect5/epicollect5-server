<?php

namespace Tests\Http\Validation\Project\RuleProjectDefinition;

use ec5\DTO\ProjectDTO;
use ec5\Services\Mapping\ProjectMappingService;
use Exception;

class MaxFormsTest extends RuleProjectDefinitionBaseTest
{
    /**
     * The app-layer form-count check (ec5_263) is masked at the import-validation
     * HTTP endpoint by the JSON Schema gate (which also caps forms at the same
     * limit), so it is exercised directly against the definition validator here.
     *
     * @throws Exception
     */
    public function test_too_many_forms(): void
    {
        $project = new ProjectDTO(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats,
            new ProjectMappingService()
        );

        $projectMock = $this->getProjectMock();
        $max = config('epicollect.limits.formsMaxCount');

        for ($i = 0; $i <= $max; $i++) {
            $projectMock['forms'][$i] = $this->getFormMock($projectMock['ref'], $i);
            $projectMock['forms'][$i]['inputs'][0] = $this->getInputMock($projectMock['forms'][$i]['ref']);
        }

        //add form name to use create() method
        $projectMock['form_name'] = 'just to pass method check';

        // Create new JSON Project Definition
        $project->create($projectMock['ref'], $projectMock);

        //reset extra property, not part of project definition
        unset($projectMock['form_name']);

        //add forms to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertTrue($this->ruleProjectDefinition->hasErrors());
        $this->assertEquals('ec5_263', $this->ruleProjectDefinition->errors['validation'][0]);
    }

    /**
     * @throws Exception
     */
    public function test_max_forms_allowed(): void
    {
        $project = new ProjectDTO(
            $this->projectDefinition,
            $this->projectExtra,
            $this->projectMapping,
            $this->projectStats,
            new ProjectMappingService()
        );

        $projectMock = $this->getProjectMock();
        $max = config('epicollect.limits.formsMaxCount');

        for ($i = 0; $i < $max; $i++) {
            $projectMock['forms'][$i] = $this->getFormMock($projectMock['ref'], $i);
            $projectMock['forms'][$i]['inputs'][0] = $this->getInputMock($projectMock['forms'][$i]['ref']);
        }

        //add form name to use create() method
        $projectMock['form_name'] = 'just to pass method check';

        // Create new JSON Project Definition
        $project->create($projectMock['ref'], $projectMock);

        //reset extra property, not part of project definition
        unset($projectMock['form_name']);

        //add forms to project definition
        $project->addProjectDefinition([
            'id' => $projectMock['ref'],
            'project' => $projectMock
        ]);

        $this->ruleProjectDefinition->validate($project);
        $this->assertFalse($this->ruleProjectDefinition->hasErrors());
    }
}

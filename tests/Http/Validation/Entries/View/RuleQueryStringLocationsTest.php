<?php

namespace Tests\Http\Validation\Entries\View;

use ec5\DTO\ProjectDefinitionDTO;
use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectExtraDTO;
use ec5\DTO\ProjectMappingDTO;
use ec5\DTO\ProjectRoleDTO;
use ec5\DTO\ProjectStatsDTO;
use ec5\Http\Validation\Entries\View\RuleQueryStringLocations;
use ec5\Libraries\Utilities\Common;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Services\Project\ProjectExtraService;
use Faker\Factory as Faker;
use Tests\Generators\EntryGenerator;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class RuleQueryStringLocationsTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->faker = Faker::create();
        //create fake user for testing
        $user = factory(User::class)->create();
        $role = config('epicollect.strings.project_roles.creator');

        //create a project with custom project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(5);
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'name' => array_get($projectDefinition, 'data.project.name'),
                'slug' => array_get($projectDefinition, 'data.project.slug'),
                'ref' => array_get($projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.private')
            ]
        );
        //add role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //create project structures
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($projectDefinition['data']);
        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data']),
                'project_extra' => json_encode($projectExtra)
            ]
        );
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );

        $this->entryGenerator = new EntryGenerator($projectDefinition);
        $this->user = $user;
        $this->role = $role;
        $this->project = $project;
        $this->projectDefinition = $projectDefinition;
        $this->projectExtra = $projectExtra;
        $this->ruleQueryStringLocations = new RuleQueryStringLocations();
    }

    public function test_valid_form_ref_and_input_ref()
    {
        $requestedProject = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO()
        );
        $requestedProjectRole = new ProjectRoleDTO();
        $requestedProject->initAllDTOs(Project::findBySlug($this->project->slug));
        $requestedProjectRole->setRole($this->user, $this->project->id, $this->role);

        //get forms
        $forms = $this->projectDefinition['data']['project']['forms'];
        foreach ($forms as $index => $form) {
            $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, $index);
            foreach ($locationInputRefs as $locationInputRef) {
                $params = [
                    'form_ref' => $form['ref'],
                    'input_ref' => $locationInputRef
                ];
                $this->ruleQueryStringLocations->additionalChecks($requestedProject, $params);
                $this->assertFalse($this->ruleQueryStringLocations->hasErrors());


                //if no form_ref provided, it will default to the first one
                if ($index === 0) {
                    $params = [
                        'form_ref' => '',
                        'input_ref' => $locationInputRef
                    ];
                    $this->ruleQueryStringLocations->additionalChecks($requestedProject, $params);
                    $this->assertFalse($this->ruleQueryStringLocations->hasErrors());
                }
            }
        }
    }

    public function test_valid_form_ref_but_invalid_input_ref()
    {
        $requestedProject = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO()
        );
        $requestedProjectRole = new ProjectRoleDTO();
        $requestedProject->initAllDTOs(Project::findBySlug($this->project->slug));
        $requestedProjectRole->setRole($this->user, $this->project->id, $this->role);

        //get forms
        $forms = $this->projectDefinition['data']['project']['forms'];
        foreach ($forms as $index => $form) {
            $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, $index);
            foreach ($locationInputRefs as $locationInputRef) {
                $params = [
                    'form_ref' => $form['ref'],
                    'input_ref' => ''
                ];
                $this->ruleQueryStringLocations->additionalChecks($requestedProject, $params);
                $this->assertTrue($this->ruleQueryStringLocations->hasErrors());
            }

            $params = [
                'form_ref' => $form['ref'],
                'input_ref' => Generators::inputRef($form['ref'])
            ];
            $this->ruleQueryStringLocations->additionalChecks($requestedProject, $params);
            $this->assertTrue($this->ruleQueryStringLocations->hasErrors());

            $params = [
                'form_ref' => $form['ref'],
                'input_ref' => 'ciao'
            ];
            $this->ruleQueryStringLocations->additionalChecks($requestedProject, $params);
            $this->assertTrue($this->ruleQueryStringLocations->hasErrors());

            $params = [
                'form_ref' => $form['ref'],
                'input_ref' => null
            ];
            $this->ruleQueryStringLocations->additionalChecks($requestedProject, $params);
            $this->assertTrue($this->ruleQueryStringLocations->hasErrors());
        }
    }

    public function test_invalid_form_ref()
    {
        $requestedProject = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO()
        );
        $requestedProjectRole = new ProjectRoleDTO();
        $requestedProject->initAllDTOs(Project::findBySlug($this->project->slug));
        $requestedProjectRole->setRole($this->user, $this->project->id, $this->role);

        //get forms
        $projectRef = $this->projectDefinition['data']['project']['ref'];
        $forms = $this->projectDefinition['data']['project']['forms'];
        foreach ($forms as $index => $form) {
            $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, $index);
            foreach ($locationInputRefs as $locationInputRef) {
                $params = [
                    'form_ref' => Generators::formRef($projectRef),
                    'input_ref' => $locationInputRef
                ];
                $this->ruleQueryStringLocations->additionalChecks($requestedProject, $params);
                $this->assertTrue($this->ruleQueryStringLocations->hasErrors());

                $params = [
                    'form_ref' => 'ciao',
                    'input_ref' => $locationInputRef
                ];
                $this->ruleQueryStringLocations->additionalChecks($requestedProject, $params);
                $this->assertTrue($this->ruleQueryStringLocations->hasErrors());

            }
        }
    }

    public function test_valid_form_ref_and_input_ref_and_branch_ref()
    {
        $requestedProject = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO()
        );
        $requestedProjectRole = new ProjectRoleDTO();
        $requestedProject->initAllDTOs(Project::findBySlug($this->project->slug));
        $requestedProjectRole->setRole($this->user, $this->project->id, $this->role);

        //get forms
        $forms = $this->projectDefinition['data']['project']['forms'];
        foreach ($forms as $formIndex => $form) {
            $branchRefs = Common::getBranchRefs($this->projectDefinition, $formIndex);
            foreach ($branchRefs as $branchRef) {
                $branchLocationInputRefs = Common::getBranchLocationInputRefs($this->projectDefinition, $formIndex, $branchRef);
                foreach ($branchLocationInputRefs as $branchLocationInputRef) {
                    $params = [
                        'form_ref' => $form['ref'],
                        'input_ref' => $branchLocationInputRef,//the branch owner ref
                        'branch_ref' => $branchRef//must be a branch input location ref
                    ];
                    $this->ruleQueryStringLocations->additionalChecks($requestedProject, $params);
                    $this->assertFalse($this->ruleQueryStringLocations->hasErrors());
                }
            }
        }
    }

    public function test_valid_form_ref_and_input_ref_but_invalid_branch_ref()
    {
        $requestedProject = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO()
        );
        $requestedProjectRole = new ProjectRoleDTO();
        $requestedProject->initAllDTOs(Project::findBySlug($this->project->slug));
        $requestedProjectRole->setRole($this->user, $this->project->id, $this->role);

        //get forms
        $forms = $this->projectDefinition['data']['project']['forms'];
        foreach ($forms as $index => $form) {
            $branchRefs = Common::getBranchRefs($this->projectDefinition, $index);
            foreach ($branchRefs as $branchRef) {
                $branchLocationInputRefs = Common::getBranchLocationInputRefs($this->projectDefinition, $index, $branchRef);
                foreach ($branchLocationInputRefs as $branchLocationInputRef) {
                    $params = [
                        'form_ref' => $form['ref'],
                        'input_ref' => $branchLocationInputRef,
                        //let's pass a wrong one
                        'branch_ref' => Generators::branchInputRef($branchLocationInputRef)
                    ];
                    $this->ruleQueryStringLocations->additionalChecks($requestedProject, $params);
                    $this->assertTrue($this->ruleQueryStringLocations->hasErrors());


                    //if no form_ref provided, it will default to the first one
                    if ($index === 0) {
                        $params = [
                            'form_ref' => '',
                            'input_ref' => $branchLocationInputRef,
                            //let's pass a wrong one
                            'branch_ref' => Generators::branchInputRef($branchLocationInputRef)
                        ];
                        $this->ruleQueryStringLocations->additionalChecks($requestedProject, $params);
                        $this->assertTrue($this->ruleQueryStringLocations->hasErrors());
                    }
                }
            }
        }
    }

}
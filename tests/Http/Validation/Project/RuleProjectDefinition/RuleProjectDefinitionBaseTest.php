<?php

namespace Tests\Http\Validation\Project\RuleProjectDefinition;

use ec5\DTO\ProjectDefinitionDTO;
use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectExtraDTO;
use ec5\DTO\ProjectMappingDTO;
use ec5\DTO\ProjectStatsDTO;
use ec5\Http\Validation\Project\RuleForm;
use ec5\Http\Validation\Project\RuleInput;
use ec5\Http\Validation\Project\RuleProjectDefinition;
use ec5\Http\Validation\Project\RuleProjectExtraDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class RuleProjectDefinitionBaseTest extends TestCase
{
    use DatabaseTransactions;

    protected RuleProjectDefinition $ruleProjectDefinition;
    protected ProjectDTO $project;
    protected RuleProjectExtraDetails $ruleProjectExtraDetails;
    protected RuleForm $ruleForm;
    protected RuleInput $ruleInput;
    protected ProjectExtraDTO $projectExtra;
    protected ProjectMappingDTO $projectMapping;
    protected ProjectStatsDTO $projectStats;
    protected ProjectDefinitionDTO $projectDefinition;
    protected int $inputsLimit;

    protected function getProjectMock(): array
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

    protected function getFormMock($projectRef, $formIndex): array
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

    protected function getInputMock($ref): array
    {
        //input types
        $inputTypes = ['text', 'integer', 'decimal', 'phone'];

        return [
            'ref' => $ref . '_' . uniqid(),
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

        $this->ruleProjectExtraDetails = new RuleProjectExtraDetails();
        $this->ruleForm = new RuleForm();
        $this->ruleInput = new RuleInput();
        $this->projectExtra = new ProjectExtraDTO();
        $this->projectMapping = new ProjectMappingDTO();
        $this->projectStats = new ProjectStatsDTO();
        $this->projectDefinition = new ProjectDefinitionDTO();

        $this->ruleProjectDefinition = new RuleProjectDefinition(
            $this->ruleProjectExtraDetails,
            $this->ruleForm,
            $this->ruleInput,
            $this->projectExtra,
            $this->projectDefinition
        );

        $this->inputsLimit = config('epicollect.limits.formlimits.inputs');
    }

    public function test_rule_project_definition_base()
    {
        $this->assertTrue(true);
    }

}

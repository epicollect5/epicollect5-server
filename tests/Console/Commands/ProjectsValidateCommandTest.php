<?php

namespace Tests\Console\Commands;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStructure;
use ec5\Services\Project\ProjectValidationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectsValidateCommandTest extends TestCase
{
    use DatabaseTransactions;

    private ProjectValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProjectValidationService::class);
    }

    public function test_single_valid_project_passes(): void
    {
        $project = factory(Project::class)->create();
        factory(ProjectStructure::class)->create(['project_id' => $project->id]);

        $this->artisan('projects:validate', ['--slug' => $project->slug])
            ->expectsOutputToContain('passed validation')
            ->assertExitCode(0);
    }

    public function test_single_missing_project_fails(): void
    {
        $this->artisan('projects:validate', ['--slug' => 'does-not-exist'])
            ->assertExitCode(1);
    }

    public function test_invalid_definition_is_reported_as_failure(): void
    {
        $project = factory(Project::class)->create();
        $structure = factory(ProjectStructure::class)->create(['project_id' => $project->id]);

        $definition = json_decode($structure->project_definition, true);
        $definition['project']['category'] = 'not_a_real_category';
        $structure->project_definition = json_encode($definition);
        $structure->save();

        $this->artisan('projects:validate', ['--slug' => $project->slug])
            ->expectsOutputToContain('failed validation')
            ->assertExitCode(1);
    }

    public function test_project_with_no_questions_is_skipped(): void
    {
        $project = factory(Project::class)->create();
        $structure = factory(ProjectStructure::class)->create(['project_id' => $project->id]);

        $definition = json_decode($structure->project_definition, true);
        // Remove all inputs so the project has forms but no questions yet.
        foreach ($definition['project']['forms'] as &$form) {
            $form['inputs'] = [];
        }
        unset($form);
        $structure->project_definition = json_encode($definition);
        $structure->save();

        $this->artisan('projects:validate', ['--slug' => $project->slug])
            ->expectsOutputToContain('skipped: no questions')
            ->assertExitCode(0);

        // Skipped projects must not be written to the failures CSV.
        $this->assertNull($this->service->failuresCsvPath());
    }

    public function test_all_projects_runs_and_writes_csv_on_failure(): void
    {
        $valid = factory(Project::class)->create();
        factory(ProjectStructure::class)->create(['project_id' => $valid->id]);

        $invalid = factory(Project::class)->create();
        $invalidStructure = factory(ProjectStructure::class)->create(['project_id' => $invalid->id]);
        $definition = json_decode($invalidStructure->project_definition, true);
        $definition['project']['category'] = 'not_a_real_category';
        $invalidStructure->project_definition = json_encode($definition);
        $invalidStructure->save();

        $this->artisan('projects:validate')
            ->expectsOutputToContain('Done.')
            ->assertExitCode(0);

        $csvPath = $this->service->failuresCsvPath();
        $this->assertNotNull($csvPath);
        $this->assertNotEmpty(Storage::disk('temp')->get($csvPath));
    }
}

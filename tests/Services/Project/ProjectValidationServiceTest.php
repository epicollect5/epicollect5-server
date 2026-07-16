<?php

namespace Tests\Services\Project;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStructure;
use ec5\Services\Project\ProjectValidationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectValidationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function makeService(): ProjectValidationService
    {
        return app(ProjectValidationService::class);
    }

    /**
     * A project with a valid definition and default mapping should pass.
     */
    public function test_valid_project_passes(): void
    {
        $project = factory(Project::class)->create();
        factory(ProjectStructure::class)->create(['project_id' => $project->id]);

        $result = $this->makeService()->validateProject($project->slug);

        $this->assertEquals('pass', $result['status']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * A project whose definition has an invalid category should fail.
     */
    public function test_invalid_definition_fails(): void
    {
        $project = factory(Project::class)->create();
        $structure = factory(ProjectStructure::class)->create(['project_id' => $project->id]);

        // Corrupt the category so definition validation fails.
        $definition = json_decode($structure->project_definition, true);
        $definition['project']['category'] = 'not_a_real_category';
        $structure->project_definition = json_encode($definition);
        $structure->save();

        $result = $this->makeService()->validateProject($project->slug);

        $this->assertEquals('fail', $result['status']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * Validating a non-existent slug returns a failure result.
     */
    public function test_missing_project_fails(): void
    {
        $result = $this->makeService()->validateProject('does-not-exist');

        $this->assertEquals('fail', $result['status']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * ec5_* error codes should be written to the CSV with their human-readable label.
     */
    public function test_failure_csv_maps_error_code_to_label(): void
    {
        $service = $this->makeService();
        $service->resetFailures();

        // A project with no project_definition triggers the ec5_67 guard.
        $project = factory(Project::class)->create();
        DB::table(config('epicollect.tables.project_structures'))
            ->where('project_id', $project->id)
            ->delete();

        $service->validateProject($project->slug);

        $csvPath = $service->failuresCsvPath();
        $this->assertNotNull($csvPath);

        $contents = Storage::disk('temp')->get($csvPath);
        $this->assertStringContainsString('ec5_67', $contents);
        $this->assertStringContainsString(config('epicollect.codes.ec5_67'), $contents);
    }

    /**
     * A project whose default mapping has a custom name (e.g. "Test") must pass — it must
     * not be flagged ec5_228 ("mapping name already exists") by self-collision, since we are
     * validating the project as it already is, not importing a new mapping.
     */
    public function test_custom_named_default_mapping_passes(): void
    {
        $project = factory(Project::class)->create();
        $structure = factory(ProjectStructure::class)->create(['project_id' => $project->id]);

        $mapping = json_decode($structure->project_mapping, true);
        $mapping[0]['name'] = 'Test';
        $structure->project_mapping = json_encode($mapping);
        $structure->save();

        $result = $this->makeService()->validateProject($project->slug);

        $this->assertEquals('pass', $result['status'], json_encode($result['errors']));
    }
}

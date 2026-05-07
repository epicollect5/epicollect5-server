<?php

namespace Tests\Models\Eloquent;

use ec5\Models\Project\Project;
use Tests\TestCase;

class ProjectStatsCountsTest extends TestCase
{
    public function test_get_total_forms_and_branches_from_project_stats_refs()
    {
        $project = new Project();
        $project->forceFill([
            'form_counts' => json_encode([
                'form_1' => ['count' => 3],
                'form_2' => ['count' => 5],
            ]),
            'branch_counts' => [
                'branch_1' => ['count' => 2],
                'branch_2' => ['count' => 4],
            ],
        ]);

        $this->assertSame(2, $project->total_forms);
        $this->assertSame(2, $project->total_branches);
    }

    public function test_get_total_forms_and_branches_handles_empty_counts()
    {
        $project = new Project();
        $project->forceFill([
            'form_counts' => null,
            'branch_counts' => 'not-json',
        ]);

        $this->assertSame(0, $project->total_forms);
        $this->assertSame(0, $project->total_branches);
    }
}

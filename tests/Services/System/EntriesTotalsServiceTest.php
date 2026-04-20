<?php

namespace Tests\Services\System;

use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use ec5\Services\System\EntriesTotalsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EntriesTotalsServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function testTotalUsesProjectStatsAggregate()
    {
        $service = new EntriesTotalsService();
        $initialStats = $service->getStats();

        $privateProject = factory(Project::class)->create([
            'access' => config('epicollect.strings.project_access.private')
        ]);
        $publicProject = factory(Project::class)->create([
            'access' => config('epicollect.strings.project_access.public')
        ]);

        factory(ProjectStats::class)->create([
            'project_id' => $privateProject->id,
            'total_entries' => 11
        ]);
        factory(ProjectStats::class)->create([
            'project_id' => $publicProject->id,
            'total_entries' => 7
        ]);

        factory(Entry::class)->create([
            'project_id' => $privateProject->id
        ]);
        factory(Entry::class)->create([
            'project_id' => $publicProject->id
        ]);

        $stats = $service->getStats();

        $this->assertSame(
            11,
            (int)$stats['total']['private'] - (int)$initialStats['total']['private']
        );
        $this->assertSame(
            7,
            (int)$stats['total']['public'] - (int)$initialStats['total']['public']
        );
    }
}

<?php

namespace Tests\Services\System;

use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use ec5\Services\System\BranchEntriesTotalsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BranchEntriesTotalsServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function testTotalUsesProjectStatsAggregate()
    {
        $service = new BranchEntriesTotalsService();
        $initialStats = $service->getStats();

        $privateProject = factory(Project::class)->create([
            'access' => config('epicollect.strings.project_access.private')
        ]);
        $publicProject = factory(Project::class)->create([
            'access' => config('epicollect.strings.project_access.public')
        ]);

        factory(ProjectStats::class)->create([
            'project_id' => $privateProject->id,
            'branch_counts' => json_encode([
                'branch_private_1' => ['count' => 3],
                'branch_private_2' => ['count' => 8]
            ])
        ]);
        factory(ProjectStats::class)->create([
            'project_id' => $publicProject->id,
            'branch_counts' => json_encode([
                'branch_public_1' => ['count' => 2],
                'branch_public_2' => ['count' => 5]
            ])
        ]);

        $privateOwnerEntry = factory(Entry::class)->create([
            'project_id' => $privateProject->id
        ]);
        $publicOwnerEntry = factory(Entry::class)->create([
            'project_id' => $publicProject->id
        ]);

        factory(BranchEntry::class)->create([
            'project_id' => $privateProject->id,
            'owner_entry_id' => $privateOwnerEntry->id
        ]);
        factory(BranchEntry::class)->create([
            'project_id' => $publicProject->id,
            'owner_entry_id' => $publicOwnerEntry->id
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

<?php

namespace Tests\DTO;

use ec5\DTO\ProjectStatsDTO;
use Tests\TestCase;

class ProjectStatsDTOTest extends TestCase
{
    public function test_get_most_recent_entry_timestamp_from_array_form_counts(): void
    {
        $projectStats = new ProjectStatsDTO();
        $projectStats->init([
            'form_counts' => [
                'form_1' => [
                    'last_entry_created' => '2026-05-01 10:00:00',
                ],
                'form_2' => [
                    'last_entry_created' => '2026-05-01 12:00:00',
                ],
            ],
        ]);

        $this->assertSame(
            (string) strtotime('2026-05-01 12:00:00'),
            $projectStats->getMostRecentEntryTimestamp()
        );
    }

    public function test_get_most_recent_entry_timestamp_from_json_form_counts(): void
    {
        $projectStats = new ProjectStatsDTO();
        $projectStats->init([
            'form_counts' => json_encode([
                'form_1' => [
                    'last_entry_created' => '2026-05-01 10:00:00',
                ],
                'form_2' => [
                    'last_entry_created' => '2026-05-01 12:00:00',
                ],
            ]),
        ]);

        $this->assertSame(
            (string) strtotime('2026-05-01 12:00:00'),
            $projectStats->getMostRecentEntryTimestamp()
        );
    }

    public function test_get_most_recent_entry_timestamp_ignores_empty_values(): void
    {
        $projectStats = new ProjectStatsDTO();
        $projectStats->init([
            'form_counts' => [
                'form_1' => [
                    'last_entry_created' => '',
                ],
                'form_2' => [
                    'last_entry_created' => '2026-05-01 12:00:00',
                ],
                'form_3' => [],
            ],
        ]);

        $this->assertSame(
            (string) strtotime('2026-05-01 12:00:00'),
            $projectStats->getMostRecentEntryTimestamp()
        );
    }

    public function test_get_most_recent_entry_timestamp_returns_empty_string_when_form_counts_are_empty(): void
    {
        $projectStats = new ProjectStatsDTO();
        $projectStats->init([
            'form_counts' => [],
        ]);

        $this->assertSame('', $projectStats->getMostRecentEntryTimestamp());
    }

    public function test_get_most_recent_entry_timestamp_returns_empty_string_for_invalid_json(): void
    {
        $projectStats = new ProjectStatsDTO();
        $projectStats->init([
            'form_counts' => 'not-json',
        ]);

        $this->assertSame('', $projectStats->getMostRecentEntryTimestamp());
    }

    public function test_get_branch_counts_from_array(): void
    {
        $branchCounts = [
            'branch_1' => [
                'count' => 2,
            ],
        ];

        $projectStats = new ProjectStatsDTO();
        $projectStats->init([
            'branch_counts' => $branchCounts,
        ]);

        $this->assertSame($branchCounts, $projectStats->getBranchCounts());
    }

    public function test_get_branch_counts_from_json(): void
    {
        $branchCounts = [
            'branch_1' => [
                'count' => 2,
            ],
        ];

        $projectStats = new ProjectStatsDTO();
        $projectStats->init([
            'branch_counts' => json_encode($branchCounts),
        ]);

        $this->assertSame($branchCounts, $projectStats->getBranchCounts());
    }

    public function test_get_branch_counts_returns_empty_array_for_invalid_json(): void
    {
        $projectStats = new ProjectStatsDTO();
        $projectStats->init([
            'branch_counts' => 'not-json',
        ]);

        $this->assertSame([], $projectStats->getBranchCounts());
    }
}

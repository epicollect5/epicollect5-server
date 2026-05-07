<?php

namespace ec5\DTO;

class ProjectStatsDTO
{
    // Coming from project_structures table (updated_at)
    public string $structure_last_updated;
    // Own public properties, reflecting those in 'project_stats' db table
    public int $total_entries = 0;
    public int $total_files = 0;
    public int $total_bytes = 0;
    public array $form_counts = [];
    public array $branch_counts = [];

    /**
     * Behaves differently to other DTOs
     * Properties are class properties rather
     * than contained within a 'data' property
     */
    public function init($params): void
    {
        $this->total_entries = $params['total_entries'] ?? 0;
        $this->total_files = $params['total_files'] ?? 0;
        $this->total_bytes = $params['total_bytes'] ?? 0;
        $this->form_counts = $this->decodeCounts($params['form_counts'] ?? []);
        $this->branch_counts = $this->decodeCounts($params['branch_counts'] ?? []);
        $this->structure_last_updated = $params['structure_last_updated'] ?? '';
    }

    /**
     * Get project stats data (as Array)
     */
    public function toArray(): array
    {
        return [
            'total_entries' => $this->total_entries,
            'total_files' => $this->total_files,
            'total_bytes' => $this->total_bytes,
            'form_counts' => $this->form_counts,
            'branch_counts' => $this->branch_counts,
        ];
    }

    /**
     * Get project stats data (JSON encoded)
     */
    public function toJsonEncoded(): array
    {
        return [
            'total_entries' => $this->total_entries,
            'total_files' => $this->total_files,
            'total_bytes' => $this->total_bytes,
            'form_counts' => json_encode($this->form_counts),
            'branch_counts' => json_encode($this->branch_counts),
        ];
    }

    public function getFormCounts(): array
    {
        return $this->form_counts;
    }

    public function getMostRecentEntryTimestamp(): string
    {
        $formCounts = $this->getFormCounts();

        if (empty($formCounts)) {
            return '';
        }

        $timestamps = collect($formCounts)
            ->pluck('last_entry_created')
            ->reject(function ($entry) {
                return empty($entry);
            })
            ->map(function ($entry) {
                return strtotime($entry);
            });

        $mostRecentTimestamp = $timestamps->max();

        return $mostRecentTimestamp > 0 ? (string) $mostRecentTimestamp : '';
    }

    public function getBranchCounts(): array
    {
        return $this->branch_counts;
    }

    private function decodeCounts(array|string|null $counts): array
    {
        if (is_array($counts)) {
            return $counts;
        }

        $decoded = json_decode($counts ?? '', true);

        return is_array($decoded) ? $decoded : [];
    }
}

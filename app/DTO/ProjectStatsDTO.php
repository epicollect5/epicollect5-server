<?php

namespace ec5\DTO;

class ProjectStatsDTO
{
    // Coming from project_structures table (updated_at)
    public string $structure_last_updated;
    public string $project_definition_version;
    // Own public properties, reflecting those in 'project_stats' db table
    public int $total_entries = 0;
    public int $total_files = 0;
    public int $total_bytes = 0;
    public array|string $form_counts = [];
    public array|string $branch_counts = [];

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
        $this->form_counts = $params['form_counts'] ?? [];
        $this->branch_counts = $params['branch_counts'] ?? [];
        $this->structure_last_updated = $params['structure_last_updated'] ?? '';
        $projectDefinitionVersion = $params['project_definition_version'] ?? $this->structure_last_updated;
        $this->project_definition_version = (string)strtotime($projectDefinitionVersion);
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
            'form_counts' => is_array($this->form_counts) ? json_encode($this->form_counts) : $this->form_counts,
            'branch_counts' => is_array($this->branch_counts) ? json_encode($this->branch_counts) : $this->branch_counts,
        ];
    }

    public function hasFormEntries(string $formRef): bool
    {
        return $this->getCountByRef($this->form_counts, $formRef) > 0;
    }

    public function hasBranchEntries(string $branchRef): bool
    {
        return $this->getCountByRef($this->branch_counts, $branchRef) > 0;
    }

    private function getCountByRef(array|string $counts, string $ref): int
    {
        if (!is_array($counts)) {
            return 0;
        }

        $count = $counts[$ref]['count'] ?? 0;

        return is_numeric($count) ? (int)$count : 0;
    }
}

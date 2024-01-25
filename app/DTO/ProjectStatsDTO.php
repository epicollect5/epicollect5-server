<?php

namespace ec5\DTO;

class ProjectStatsDTO
{
    // Coming from project_structures table (updated_at)
    public $structure_last_updated;
    // Own public properties, reflecting those in 'project_stats' db table
    public $total_entries = 0;
    public $total_users = 0;
    public $form_counts = [];
    public $branch_counts = [];

    /**
     * Behaves differently to other DTOs
     * Properties are class properties rather
     * than contained within a 'data' property
     *
     */
    public function init($params)
    {
        $this->total_entries = $params['total_entries'] ?? 0;
        $this->total_users = $params['total_users'] ?? 0;
        $this->form_counts = $params['form_counts'] ?? [];
        $this->branch_counts = $params['branch_counts'] ?? [];
        $this->structure_last_updated = $params['structure_last_updated'] ?? '';
    }

    /**
     * Get project stats data (as Array)
     */
    public function toArray(): array
    {
        return [
            'total_entries' => $this->total_entries,
            'total_users' => $this->total_users,
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
            'total_users' => $this->total_users,//todo: this is redundant?
            'form_counts' => is_array($this->form_counts) ? json_encode($this->form_counts) : $this->form_counts,
            'branch_counts' => is_array($this->branch_counts) ? json_encode($this->branch_counts) : $this->branch_counts,
        ];
    }
}
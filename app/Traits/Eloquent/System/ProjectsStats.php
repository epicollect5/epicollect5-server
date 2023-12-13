<?php

namespace ec5\Traits\Eloquent\System;

use DB;

trait ProjectsStats
{
    public function getProjectTotalByThreshold($lowerThreshold, $upperThreshold): int
    {
        $table = 'project_stats';

        $projectsTotal = DB::table($table)
            ->whereBetween('total_entries', [$lowerThreshold, $upperThreshold])
            ->get();

        return $projectsTotal->count();
    }
}

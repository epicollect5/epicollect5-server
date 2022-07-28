<?php

namespace ec5\Traits\Eloquent;

use DB;

trait ProjectsStats
{
    public function getProjectTotalByThreshold($lowerThreshold, $upperThreshold)
    {
        $table = 'project_stats';

        $projectsTotal =  DB::table($table)
            ->whereBetween('total_entries',[$lowerThreshold, $upperThreshold])
            ->get();

        return $projectsTotal->count();
    }
}

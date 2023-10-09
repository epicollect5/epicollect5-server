<?php

namespace ec5\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use ec5\Traits\Eloquent\ProjectsStats;

use DB;
use Carbon\Carbon;
use ec5\Libraries\EC5Logger\EC5Logger;
use Config;

class Project extends Model
{

    use ProjectsStats;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'projects';

    protected $fillable = ['slug'];

    public function getTotal()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select(['access', 'visibility', DB::raw('count(id) as projects_total')])
            ->groupBy('access', 'visibility')
            ->get();
    }

    public function getTotalBelow10()
    {
        $lowerThreshold = 0;
        $upperThreshold = 10;
        return $this->getProjectTotalByThreshold($lowerThreshold, $upperThreshold);
    }

    public function getTotalBelow100()
    {
        $lowerThreshold = 11;
        $upperThreshold = 100;
        return $this->getProjectTotalByThreshold($lowerThreshold, $upperThreshold);
    }

    public function getTotalBelow1000()
    {
        $lowerThreshold = 101;
        $upperThreshold = 1000;
        return $this->getProjectTotalByThreshold($lowerThreshold, $upperThreshold);
    }

    public function getTotalBelow10000()
    {
        $lowerThreshold = 1001;
        $upperThreshold = 10000;
        return $this->getProjectTotalByThreshold($lowerThreshold, $upperThreshold);
    }

    public function getTotalBelow25000()
    {
        $lowerThreshold = 10001;
        $upperThreshold = 25000;
        return $this->getProjectTotalByThreshold($lowerThreshold, $upperThreshold);
    }

    public function getTotalBelow50000()
    {
        $lowerThreshold = 25001;
        $upperThreshold = 50000;
        return $this->getProjectTotalByThreshold($lowerThreshold, $upperThreshold);
    }

    public function getTotalAbove50000()
    {
        $lowerThreshold = 50000;
        $upperThreshold = 4294967295; //max INT value possible
        return $this->getProjectTotalByThreshold($lowerThreshold, $upperThreshold);
    }

    public function getYesterday()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select(['access', 'visibility', DB::raw('count(id) as projects_total')])
            ->where($this->table . '.created_at', '>=', Carbon::yesterday())
            ->groupBy('access', 'visibility')
            ->get();
    }

    public function getWeek()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select(['access', 'visibility', DB::raw('count(id) as projects_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->subWeeks(1))
            ->where($this->table . '.created_at', '<=', Carbon::now()->subDays(1))
            ->groupBy('access', 'visibility')
            ->get();
    }

    public function getMonth()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select(['access', 'visibility', DB::raw('count(id) as projects_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->startOfMonth())
            ->groupBy('access', 'visibility')
            ->get();
    }

    public function getYear()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select(['access', 'visibility', DB::raw('count(id) as projects_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->startOfYear())
            ->where($this->table . '.created_at', '<=', Carbon::now()->endOfYear())
            ->groupBy('access', 'visibility')
            ->get();
    }

    public function yearByMonth()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('MONTH(created_at) as month'), DB::raw('count(id) as projects_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->startOfYear())
            ->groupBy('month')
            ->get();
    }

    public function getStats()
    {
        $total = $this->getTotal();
        $today = $this->getYesterday();
        $week = $this->getWeek();
        $month = $this->getMonth();
        $year = $this->getYear();
        $yearByMonth = $this->yearByMonth();

        //convert $yearByMonth to associative array of "month=>total" pairs
        $distributionByMonth = [];
        foreach ($yearByMonth as $currentMonth) {
            array_push(
                $distributionByMonth,
                array(
                    $this->getMonthName($currentMonth->month) => $currentMonth->projects_total
                )
            );
        }

        return array(
            "total" => array(
                "private" => array(
                    "hidden" => $this->getProjectsTotal($total, "private", "hidden"),
                    "listed" => $this->getProjectsTotal($total, "private", "listed"),
                ),
                "public" => array(
                    "hidden" => $this->getProjectsTotal($total, "public", "hidden"),
                    "listed" => $this->getProjectsTotal($total, "public", "listed"),
                )
            ),
            "today" => array(
                "private" => array(
                    "hidden" => $this->getProjectsTotal($today, "private", "hidden"),
                    "listed" => $this->getProjectsTotal($today, "private", "listed"),
                ),
                "public" => array(
                    "hidden" => $this->getProjectsTotal($today, "public", "hidden"),
                    "listed" => $this->getProjectsTotal($today, "public", "listed"),
                )
            ),
            "week" => array(
                "private" => array(
                    "hidden" => $this->getProjectsTotal($week, "private", "hidden"),
                    "listed" => $this->getProjectsTotal($week, "private", "listed"),
                ),
                "public" => array(
                    "hidden" => $this->getProjectsTotal($week, "public", "hidden"),
                    "listed" => $this->getProjectsTotal($week, "public", "listed"),
                )
            ),
            "month" => array(
                "private" => array(
                    "hidden" => $this->getProjectsTotal($month, "private", "hidden"),
                    "listed" => $this->getProjectsTotal($month, "private", "listed"),
                ),
                "public" => array(
                    "hidden" => $this->getProjectsTotal($month, "public", "hidden"),
                    "listed" => $this->getProjectsTotal($month, "public", "listed"),
                )
            ),
            "year" => array(
                "private" => array(
                    "hidden" => $this->getProjectsTotal($year, "private", "hidden"),
                    "listed" => $this->getProjectsTotal($year, "private", "listed"),
                ),
                "public" => array(
                    "hidden" => $this->getProjectsTotal($year, "public", "hidden"),
                    "listed" => $this->getProjectsTotal($year, "public", "listed"),
                )
            ),
            "by_month" => $distributionByMonth,
            "by_threshold" => array(
                "below10" => $this->getTotalBelow10(),
                "below100" => $this->getTotalBelow100(),
                "below1000" => $this->getTotalBelow1000(),
                "below10000" => $this->getTotalBelow10000(),
                "below25000" => $this->getTotalBelow25000(),
                "below50000" => $this->getTotalBelow50000(),
                "above50000" => $this->getTotalAbove50000()
            )
        );
    }

    //get month name passing number
    private function getMonthName($monthNumber)
    {
        return date("M", mktime(0, 0, 0, $monthNumber, 1));
    }

    //get projects total based on access and visibility
    private function getProjectsTotal($items, $access, $visibility)
    {
        foreach ($items as $item) {
            {
                if ($item->access === $access) {
                    if ($item->visibility === $visibility) {
                        return $item->projects_total;
                    }
                }
            }
        }

        return 0;
    }

    public function transferOwnership($projectId, $creatorId, $managerId): bool
    {
        try {
            //update projects table, set manager to be new creator
            $project = $this->findOrFail($projectId);
            //set new manager as creator
            $project->created_by = $managerId;

            //set old Creator as Manager
            $oldCreator = ProjectRole::where('project_id', $projectId)->where('user_id', $creatorId)->firstOrFail();
            $oldCreator->role = Config::get('ec5Permissions.projects.manager_role');

            //set new Manager as Creator
            $newCreator = ProjectRole::where('project_id', $projectId)->where('user_id', $managerId)->firstOrFail();
            $newCreator->role = Config::get('ec5Permissions.projects.creator_role');

            //try to commit all changes
            DB::transaction(function () use ($project, $oldCreator, $newCreator) {
                $project->save();
                $oldCreator->save();
                $newCreator->save();
            });

            return true;
        } catch (\Exception $e) {
            // If any exceptions, log
            EC5Logger::error('Transfer ownership failed', $this->requestedProject, [$e]);
            \Log::error('Transfer ownership failed: ', [
                'exception' => $e->getMessage(),
                'project' => $this->requestedProject->name
            ]);

            return false;
        }
    }
}

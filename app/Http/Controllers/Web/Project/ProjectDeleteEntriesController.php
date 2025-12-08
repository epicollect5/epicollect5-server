<?php

namespace ec5\Http\Controllers\Web\Project;

use Cache;
use ec5\Models\Project\ProjectStats;
use ec5\Traits\Eloquent\Archiver;
use ec5\Traits\Eloquent\StatsRefresher;
use ec5\Traits\Requests\RequestAttributes;
use Throwable;

class ProjectDeleteEntriesController
{
    use RequestAttributes;
    use Archiver;
    use StatsRefresher;

    protected array $errors = [];

    /**
     * @throws Throwable
     */
    public function show()
    {
        ///check permissions
        if (!$this->requestedProjectRole()->canDeleteEntries()) {
            $errors = ['ec5_91'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        //is the project locked? Otherwise, bail out
        if ($this->requestedProject()->status !== config('epicollect.strings.project_status.locked')) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }
        //check lock for this user (only 1 deletion process per user)
        $userId = $this->requestedUser()->id;
        $userCacheKey = 'bulk_entries_deletion_user_' . $userId;
        // Remove the user-level cache lock if it exists (optional, only if you want to reset the lock here)
        Cache::lock($userCacheKey)->release();

        //refresh stats to get the latest entries and branch entries counts
        $this->refreshProjectStats($this->requestedProject());

        $projectStats = ProjectStats::where('project_id', $this->requestedProject()->getId())->first();
        return view('project.project_delete_entries', [
            'project' => $this->requestedProject(),
            'totalEntries' => $projectStats->total_entries
        ]);
    }
}

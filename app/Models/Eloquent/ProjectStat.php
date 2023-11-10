<?php

namespace ec5\Models\Eloquent;

use Eloquent;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Eloquent
 */
class ProjectStat extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'project_stats';
    public $timestamps = false;

    public function getMostRecentEntryTimestamp(): string
    {
        $formCounts = json_decode($this->form_counts, true);

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

        return $mostRecentTimestamp > 0 ? $mostRecentTimestamp : '';
    }
}

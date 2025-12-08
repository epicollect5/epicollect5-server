<?php

namespace ec5\Models\Project;

use Carbon\Carbon;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;

class ProjectFeatured extends Model
{
    /**
     * @property int $id
     * @property int $project_id
     * @property Carbon $created_at
     * @property Carbon $updated_at
     */

    use SerializeDates;

    protected $table = 'projects_featured';
    protected $guarded = [];
}

<?php

namespace ec5\Models\Project;

use DateTimeInterface;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;

class ProjectFeatured extends Model
{
    use SerializeDates;

    protected $table = 'projects_featured';
    protected $guarded = [];
}

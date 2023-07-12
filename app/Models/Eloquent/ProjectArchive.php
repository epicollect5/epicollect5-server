<?php

namespace ec5\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;

class ProjectArchive extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'projects_archive';

    protected $guarded = [];

    public $timestamps = false;
}

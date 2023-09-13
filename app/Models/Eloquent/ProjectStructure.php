<?php

namespace ec5\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;

class ProjectStructure extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'project_structures';

    public $timestamps = ['updated_at']; //only want to use updated_at column
    const CREATED_AT = null; //and created_at by default null set

}

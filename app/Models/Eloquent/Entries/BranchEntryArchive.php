<?php

namespace ec5\Models\Eloquent\Entries;

use Illuminate\Database\Eloquent\Model;

class BranchEntryArchive extends Model
{
    protected $table = 'branch_entries_archive';
    public $timestamps = false;
    public $guarded = [];
}

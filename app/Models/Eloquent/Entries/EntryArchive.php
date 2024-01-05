<?php

namespace ec5\Models\Eloquent\Entries;

use Illuminate\Database\Eloquent\Model;

class EntryArchive extends Model
{
    protected $table = 'entries_archive';

    public $timestamps = false;
    public $guarded = [];
}

<?php

namespace ec5\Models\Entries;

use Illuminate\Database\Eloquent\Model;

class EntryArchive extends Model
{
    protected $table = 'entries_archive';

    public $timestamps = false;
    public $guarded = [];
}

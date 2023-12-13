<?php

namespace ec5\Models\Eloquent\System;

use Illuminate\Database\Eloquent\Model;

class StorageStats extends Model
{
    protected $table = 'storage_stats';
    public $timestamps = false;
    protected $guarded = [];
}

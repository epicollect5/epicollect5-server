<?php

namespace ec5\Models\System;

use DateTimeInterface;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;

class StorageStats extends Model
{
    use SerializeDates;

    protected $table = 'storage_stats';
    public $timestamps = false;
    protected $guarded = [];
}

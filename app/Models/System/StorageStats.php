<?php

namespace ec5\Models\System;

use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;

class StorageStats extends Model
{
    /**
     * @property int $id
     * @property int $project_id
     * @property string $project_ref
     * @property string $project_name
     * @property int $files
     * @property int $entries
     * @property string|null $last_entry_uploaded
     * @property int $branches
     * @property string|null $last_branch_uploaded
     * @property int $audio_bytes
     * @property int $photo_bytes
     * @property int $video_bytes
     * @property int $overall_bytes
     * @property string $created_at
     */

    use SerializeDates;

    protected $table = 'storage_stats';
    public $timestamps = false;
    protected $guarded = [];
}

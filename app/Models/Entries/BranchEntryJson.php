<?php

namespace ec5\Models\Entries;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $entry_id
 * @property string $entry_data
 * @property string $geo_json_data
 */

class BranchEntryJson extends Model
{
    protected $table = 'branch_entries_json';
    public $timestamps = false;

    protected $fillable = [
        'entry_id',
        'entry_data',
        'geo_json_data',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(BranchEntry::class, 'entry_id');
    }
}

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

class EntryJson extends Model
{
    protected $table = 'entries_json';
    protected $guarded = [];
    public $timestamps = false;

    protected $fillable = [
        'entry_id',
        'entry_data',
        'geo_json_data',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'entry_id');
    }
}

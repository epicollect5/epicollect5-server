<?php

namespace ec5\Models\Project;

use Carbon\Carbon;
use ec5\DTO\ProjectDTO;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;
use Log;
use Throwable;

/**
 * @property int $id
 * @property int $project_id
 * @property mixed $project_definition
 * @property mixed $project_extra
 * @property mixed $project_mapping
 * @property string $updated_at
 */
class ProjectStructure extends Model
{
    use SerializeDates;

    protected $table = 'project_structures';
    public $timestamps = false; // Disable automatic handling of both timestamps
    public const null CREATED_AT = null;    // Disable created_at
    /**
     * Only handle updated_at manually
     *
     *  updated_at drives the project versioning, so we decide when to update it
     *  For example, a formbuilder save will trigger a project update,
     *  while changing project privacy settings will ignore it
     */
    public const string UPDATED_AT = 'updated_at';

    //Cast 'updated_at' to datetime (to get a Carbon instance instead of string)
    //also a format without milliseconds for legacy reasons
    protected $casts = [
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public $guarded = [];

    public static function updateStructures(ProjectDTO $project, $setUpdatedAt = false): bool
    {
        $currentStructure = self::where('project_id', $project->getId())->first();
        try {
            $currentStructure->project_definition = $project->getProjectDefinition()->getJsonData();
            $currentStructure->project_extra = $project->getProjectExtra()->getJsonData();
            $currentStructure->project_mapping = $project->getProjectMapping()->getJsonData();

            // Set updated_at value when needed
            if ($setUpdatedAt) {
                //imp: we skip this for status updates for example,
                // as that should not trigger a project update on the app
                // updated_at is the value we use for project versioning
                $currentStructure->updated_at = Carbon::now(); // Set updated_at to the current time
            }

            return $currentStructure->save();


        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            return false;
        }
    }
}

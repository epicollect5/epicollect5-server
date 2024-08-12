<?php

namespace ec5\Models\Project;

use Carbon\Carbon;
use ec5\DTO\ProjectDTO;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Log;

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
    protected $table = 'project_structures';
    public $timestamps = false; // Disable automatic handling of both timestamps
    const CREATED_AT = null;    // Disable created_at
    const UPDATED_AT = 'updated_at'; // Only handle updated_at manually

    //Cast 'updated_at' to datetime (to get a Carbon instance instead of string)
    protected $casts = [
        'updated_at' => 'datetime',
    ];


    public $guarded = [];

    public static function updateStructures(ProjectDTO $project, $setUpdatedAt = false): bool
    {
        $currentStructure = self::where('project_id', $project->getId())->first();
        try {
            $currentStructure->project_definition = $project->getProjectDefinition()->getJsonData();
            $currentStructure->project_extra = $project->getProjectExtra()->getJsonData();
            $currentStructure->project_mapping = $project->getProjectMapping()->getJsonData();

            // Set updated_at field when needed
            //imp: we skip this for status updates for example,
            // as that should not trigger a project update on the app
            // updated_at is the value we use for project versioning
//            if ($setUpdatedAt) {
//                $currentStructure->updated_at = date('Y-m-d H:i:s');
//                return $currentStructure->save();
//            } else {
//                $currentStructure->timestamps = false;
//                $wasSaved = $currentStructure->save();
//                $currentStructure->timestamps = true;
//                return $wasSaved;
//            }

            // Set updated_at field when needed
            if ($setUpdatedAt) {
                $currentStructure->updated_at = Carbon::now(); // Set updated_at to the current time
                // Alternatively, use the 'touch' method:
                // $currentStructure->touch();
            }

            return $currentStructure->save();


        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            return false;
        }
    }
}

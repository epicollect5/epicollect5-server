<?php

namespace ec5\Models\Project;

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
    public $timestamps = ['updated_at']; //only want to use updated_at column
    const CREATED_AT = null; //and created_at by default null set
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
            //imp: as that should not trigger a project update on the app
            //imp: therefore timestamps gets disabled in that case
            if ($setUpdatedAt) {
                return $currentStructure->save();
            } else {
                $currentStructure->timestamps = false;
                $currentStructure->updated_at = date('Y-m-d H:i:s');
                $wasSaved = $currentStructure->save();
                $currentStructure->timestamps = true;
                return $wasSaved;
            }
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            return false;
        }
    }
}

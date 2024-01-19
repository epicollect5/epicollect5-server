<?php

namespace ec5\Models\Eloquent;

use Exception;
use Illuminate\Database\Eloquent\Model;
use ec5\Models\Projects\Project;
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

    public static function updateStructures(Project $project, $setUpdatedAt = false): bool
    {
        $currentStructure = self::where('project_id', $project->getId())->first();
        try {
            $currentStructure->project_definition = $project->getProjectDefinition()->getJsonData();
            $currentStructure->project_extra = $project->getProjectExtra()->getJsonData();
            $currentStructure->project_mapping = $project->getProjectMapping()->getJsonData();

            // Set updated_at field when needed
            if ($setUpdatedAt) {
                $currentStructure->updated_at = date('Y-m-d H:i:s');
            }
            return $currentStructure->save();
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            return false;
        }
    }
}

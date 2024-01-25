<?php

namespace ec5\Models\Projects;

use ec5\DTO\ProjectStatsDTO;
use ec5\Http\Validation\Project\RuleProjectDefinition;
use ec5\Libraries\Utilities\Common;
use Exception;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

/*
|--------------------------------------------------------------------------
| Project Model
|--------------------------------------------------------------------------
| A container for all the other Project models
| Contains its own properties reflecting those in the 'projects' db table
| Deals with the initialisation, creation and importing of data into this
| and contained models
|
*/

class Project
{
    /**
     * @var ProjectDefinition
     */
    protected $projectDefinition;
    /**
     * @var ProjectExtra
     */
    protected $projectExtra;
    /**
     * @var ProjectMapping
     */
    protected $projectMapping;
    /**
     * @var ProjectStatsDTO
     */
    protected $projectStats;
    // Null ids
    protected $id = null;
    protected $structure_id = null;
    // Null timestamps
    public $created_at = null;
    protected $updated_at = null;
    // Own public properties, reflecting those in 'projects' db table which are mutable
    public $name = '';
    public $slug = '';
    public $ref = '';
    public $description = '';
    public $small_description = '';
    public $logo_url = '';
    public $access = '';
    public $visibility = '';
    public $category = '';
    public $created_by = '';
    public $status = '';
    public $can_bulk_upload = 'nobody';

    /**
     * Project constructor.
     *
     * @param ProjectDefinition $projectDefinition
     * @param ProjectExtra $projectExtra
     * @param ProjectMapping $projectMapping
     * @param ProjectStatsDTO $projectStats
     */
    public function __construct(
        ProjectDefinition $projectDefinition,
        ProjectExtra      $projectExtra,
        ProjectMapping    $projectMapping,
        ProjectStatsDTO   $projectStats
    )
    {
        $this->projectDefinition = $projectDefinition;
        $this->projectExtra = $projectExtra;
        $this->projectMapping = $projectMapping;
        $this->projectStats = $projectStats;
    }

    /**
     * Initialise from existing data
     * $data is an object
     */
    public function init(stdClass $data)
    {
        $this->addProjectDefinition($data->project_definition ?? []);
        $this->addProjectExtra($data->project_extra ?? []);
        $this->addProjectMapping($data->project_mapping ?? []);

        $this->addProjectStats([
            'total_entries' => $data->total_entries ?? 0,
            'total_users' => $data->total_users ?? 0,
            'form_counts' => isset($data->form_counts) ? json_decode($data->form_counts, true) : [],
            'branch_counts' => isset($data->branch_counts) ? json_decode($data->branch_counts, true) : [],
            'structure_last_updated' => $data->structure_last_updated ?? '',
        ]);
        // Add all the project data object properties to this class
        $this->addProjectDetails(get_object_vars($data));
        // Lastly, initialize the protected class properties
        // Set timestamps
        $this->created_at = $data->created_at ?? null;
        $this->updated_at = $data->updated_at ?? null;
        // Remap 'project_id' to 'id'
        $this->id = $data->project_id ?? null;
        // Set the project structure_id
        $this->structure_id = $data->structure_id ?? null;
        $this->can_bulk_upload = $data->can_bulk_upload;
    }

    /**
     * Create from new data
     *
     * @param $projectRef
     * @param $data
     * @throws Exception
     */
    public function create($projectRef, $data)
    {
        // Check we have the required project and form name data set
        if (!isset($data['name']) || !isset($data['form_name'])) {
            throw new Exception(config('status_codes.ec5_224'));
        }
        // Create project and first form refs
        $formRef = $projectRef . '_' . uniqid();
        // Set required project data
        $data['ref'] = $projectRef;
        $data['status'] = 'active';
        $data['visibility'] = 'hidden';
        $data['category'] = config('epicollect.strings.project_categories.general');
        // Set required form data
        $data['forms'][0]['name'] = $data['form_name'];
        $data['forms'][0]['slug'] = Str::slug($data['form_name'], '-');
        $data['forms'][0]['type'] = 'hierarchy';
        $data['forms'][0]['ref'] = $formRef;

        // Add the Project details
        $this->addProjectDetails($data);
        // Create new JSON Project Definition
        $this->projectDefinition->create([
            'id' => $projectRef,
            'project' => $data
        ]);

        // Create new JSON Project Extra
        $this->projectExtra->create([
            'project' => [
                'details' => $data,
                'forms' => [$formRef]
            ],
            'forms' => [
                $formRef => [
                    'details' => $data['forms'][0]
                ]
            ]
        ]);
        // Create new JSON Project Mapping
        //$this->projectMapping->create([]);
        $this->projectMapping->autoGenerateMap($this->projectExtra);
        // No need to initialise the Project Stats, as they will be empty
    }

    /**
     * Import from existing data
     *
     * @param $projectRef
     * @param $projectName
     * @param $createdBy
     * @param $projectDefinitionData
     * @param RuleProjectDefinition $projectDefinitionValidator
     * @throws Exception
     */
    public function import(
        $projectRef,
        $projectName,
        $createdBy,
        $projectDefinitionData,
        RuleProjectDefinition $projectDefinitionValidator
    )
    {
        // Take new name, slug, default logo_url
        $projectDefinitionData['project']['name'] = $projectName;
        $projectDefinitionData['project']['slug'] = Str::slug($projectName, '-');
        $projectDefinitionData['project']['logo_url'] = '';
        $projectDefinitionData['id'] = $projectRef;
        // Swap the old project ref with the new one
        $existingProjectRef = $projectDefinitionData['project']['ref'];
        $projectDefinitionDataString = str_replace($existingProjectRef, $projectRef, json_encode($projectDefinitionData));
        // Decode back to array
        $projectDefinitionData = json_decode($projectDefinitionDataString, true);
        $this->addProjectDetails(array_merge($projectDefinitionData['project'], ['created_by' => $createdBy]));
        // Add this updated project definition to the Project Definition model
        $this->addProjectDefinition($projectDefinitionData);
        // Validate the Project Definition and create the Project Extra data
        $projectDefinitionValidator->validate($this);
        if ($projectDefinitionValidator->hasErrors()) {
            throw new Exception(config('status_codes.ec5_225'));
        }
        // Create new JSON Project Mapping
        $this->projectMapping->autoGenerateMap($this->projectExtra);
        // No need to initialise the Project Stats, as they will be empty
    }

    /**
     * Clone from existing structure
     */
    public function cloneProject($params)
    {
        $existingProjectRef = $this->ref;
        $newProjectRef = str_replace('-', '', Uuid::uuid4()->toString());
        // Cloned project will be set to 'active'
        $params['status'] = config('epicollect.strings.project_status.active');
        // Update the Project class properties
        $this->addProjectDetails($params);
        // Nullify the id, created_at, updated_at and structure_id as new ones will need to be created
        $this->id = null;
        $this->created_at = null;
        $this->updated_at = null;
        $this->structure_id = null;
        // Add new ref
        $this->ref = $newProjectRef;

        // Update the Project Definition
        $this->projectDefinition->updateProjectDetails($params);
        $this->projectDefinition->setData(
            Common::replaceRefInStructure(
                $existingProjectRef,
                $newProjectRef,
                $this->projectDefinition->getData()
            )
        );

        // Update the Project Extra
        $this->projectExtra->updateProjectDetails($params);
        $this->projectExtra->setData(
            Common::replaceRefInStructure(
                $existingProjectRef,
                $newProjectRef,
                $this->projectExtra->getData()
            )
        );

        // Update the Project Mapping
        $this->projectMapping->setData(
            Common::replaceRefInStructure(
                $existingProjectRef,
                $newProjectRef,
                $this->projectMapping->getData()
            )
        );

        // Update the Project Stats (to empty)
        $this->projectStats->init([]);
    }

    /**
     * Add project details into public class properties
     *
     * @param array $projectDetails
     * @return Project
     */
    public function addProjectDetails(array $projectDetails): Project
    {
        // Get this class properties
        $reflection = new ReflectionClass($this);
        // Loop round properties and set those which also exist in the $projectDetails array
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (isset($projectDetails[$property->name])) {
                $this->{$property->name} = $projectDetails[$property->name];
            }
        }
        return $this;
    }

    /**
     * @param $projectDefinitionData
     * @return Project
     */
    public function addProjectDefinition($projectDefinitionData): Project
    {
        $this->projectDefinition->init($projectDefinitionData);
        return $this;
    }

    /**
     * @param String | array $projectExtraData
     * @return Project
     */
    public function addProjectExtra($projectExtraData): Project
    {
        $this->projectExtra->init($projectExtraData);
        return $this;
    }

    /**
     * @param String | array $projectMappingData
     * @return Project
     */
    public function addProjectMapping($projectMappingData): Project
    {
        $this->projectMapping->init($projectMappingData);
        return $this;
    }

    /**
     * @param String | array $projectStatsData
     * @return Project
     */
    public function addProjectStats($projectStatsData): Project
    {
        $this->projectStats->init($projectStatsData);
        return $this;
    }

    /**
     * Update the Project Definition and Project Extra Data
     * Based on the data received
     *
     * @param $data
     */
    public function updateProjectDetails($data)
    {
        // Detail keys that are allowed to be updated
        $updateOnly = array_keys(config('epicollect.structures.updatable_project_details'));
        // Remove unwanted keys from the $data
        foreach ($data as $key => $value) {
            if (!in_array($key, $updateOnly)) {
                unset($data[$key]);
            }
        }
        // Updated the Project Details
        $this->addProjectDetails($data);
        // Update the Project Definition
        $this->projectDefinition->updateProjectDetails($data);
        // Update the Project Extra
        $this->projectExtra->updateProjectDetails($data);
    }

    /**
     * Update the Project Forms Extra and Project Mappings
     * Based on the latest Project Definition/Extra
     */
    public function updateProjectMappings()
    {
        // Update custom mappings
        $this->projectMapping->syncNewStructure($this->projectExtra);
    }

    /**
     * Get project details from public class properties
     *
     * @return array
     */
    public function getProjectDetails(): array
    {
        $out = [];
        // Get this class properties
        $reflection = new ReflectionClass($this);
        // Loop round properties and set those which also exist in the $projectDetails array
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $out[$property->name] = $this->{$property->name};
        }
        return $out;
    }

    /**
     * @return ProjectDefinition
     */
    public function getProjectDefinition(): ProjectDefinition
    {
        return $this->projectDefinition;
    }

    /**
     * @return ProjectExtra
     */
    public function getProjectExtra(): ProjectExtra
    {
        return $this->projectExtra;
    }

    /**
     * @return ProjectMapping
     */
    public function getProjectMapping(): ProjectMapping
    {
        return $this->projectMapping;
    }


    /**
     * @return ProjectStatsDTO
     */
    public function getProjectStats(): ProjectStatsDTO
    {
        return $this->projectStats;
    }

    /**
     * @return
     */
    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get the structure_id from the project_structures table
     *
     * @return null | int
     */
    public function getProjectStructureId()
    {
        return $this->structure_id;
    }

    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function getCanBulkUpload()
    {
        return $this->can_bulk_upload;
    }


    public function setEntriesLimits($entriesLimits)
    {
        $this->projectExtra->clearEntriesLimits();
        $this->projectDefinition->clearEntriesLimits();
        foreach ($entriesLimits as $ref => $params) {
            // If a limit is set
            if ($params['setLimit']) {
                $this->projectExtra->setEntriesLimit($ref, $params['limitTo']);
                $this->projectDefinition->setEntriesLimit($ref, $params['limitTo']);
            }
        }
    }

    /**
     * @return bool
     */
    public function isPrivate()
    {
        return $this->access == config('epicollect.strings.project_access.private');
    }

    /**
     * @return bool
     */
    public function isPublic()
    {
        return $this->access == config('epicollect.strings.project_access.public');
    }

    public function canBulkUpload()
    {
        return $this->can_bulk_upload;
    }

    public function hasInputs()
    {

    }
}

<?php

namespace ec5\DTO;

use ec5\Http\Validation\Project\RuleProjectDefinition;
use ec5\Libraries\Utilities\Common;
use Exception;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use ec5\Services\Mapping\ProjectMappingService;

/*
|--------------------------------------------------------------------------
| Project DTO
|--------------------------------------------------------------------------
| A container for all the other Project DTOs
| Contains its own properties reflecting those in the 'projects' db table
| Deals with the initialization, creation and importing of data into this
| and contained DTOs
|
*/

class ProjectDTO
{
    protected ProjectDefinitionDTO $projectDefinition;
    protected ProjectExtraDTO $projectExtra;
    protected ProjectMappingDTO $projectMapping;
    protected ProjectStatsDTO $projectStats;
    protected int|null $id = null;
    protected int|null $structure_id = null;
    // Null timestamps
    public string|null $created_at = null;
    protected string|null $updated_at = null;
    // Own public properties, reflecting those in 'projects' db table which are mutable
    public string $name = '';
    public string $slug = '';
    public string $ref = '';
    public string $description = '';
    public string $small_description = '';
    public string $logo_url = '';
    public string $access = '';
    public string $visibility = '';
    public string $category = '';
    public string $created_by = '';
    public string $status = '';
    public string $can_bulk_upload = 'nobody';
    public string $app_link_visibility = 'hidden';
    private ProjectMappingService $projectMappingService;

    public function __construct(
        ProjectDefinitionDTO  $projectDefinition,
        ProjectExtraDTO       $projectExtra,
        ProjectMappingDTO     $projectMapping,
        ProjectStatsDTO       $projectStats,
        ProjectMappingService $projectMappingService
    ) {
        $this->projectDefinition = $projectDefinition;
        $this->projectExtra = $projectExtra;
        $this->projectMapping = $projectMapping;
        $this->projectStats = $projectStats;
        $this->projectMappingService = $projectMappingService;
    }

    /**
     * Initialise from existing data
     * imp: $data is an object like:
     *
     * "id": 145096
     * "name": "EC5 8U5iSRja7C"
     * "slug": "ec5-8u5isrja7c"
     * "ref": "914c66a1ab074819b0dde5a2bc282f06"
     * "description": "Magnam eum perspiciatis quibusdam eveniet consequatur."
     * "small_description": "Aliquam quidem.Quod quasi perspiciatis sit qui. Perferendis nam quisquam incidunt porro."
     * "logo_url": ""
     * "access": "private"
     * "visibility": "listed"
     * "category": "general"
     * "created_by": 312321
     * "created_at": "2024-02-08 14:19:36"
     * "updated_at": "2024-02-08 14:19:42"
     * "status": "active"
     * "can_bulk_upload": "nobody"
     * "stats_id": 147223
     * "project_id": 167369
     * "total_entries": 0
     * "total_users": 0
     * "form_counts": "[]"
     * "branch_counts": "[]"
     * "project_definition":{json string...}"
     * "project_extra": "{json string...}"
     * "project_mapping": "{json string...}"
     * "structure_last_updated": "2024-02-08 14:19:42"
     * "structure_id": 145096
     * }
     */
    public function initAllDTOs(stdClass $data): void
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
        $this->app_link_visibility = $data->app_link_visibility;
    }

    /**
     * Create from new data
     *
     * @param $projectRef
     * @param $projectDetails
     * @throws Exception
     */
    public function create($projectRef, $projectDetails): void
    {
        // Check we have the required project and form name data set
        if (!isset($projectDetails['name']) || !isset($projectDetails['form_name'])) {
            throw new Exception(config('epicollect.codes.ec5_224'));
        }
        // Create project and first form refs
        $formRef = $projectRef . '_' . uniqid();
        // Set required project data
        $projectDetails['ref'] = $projectRef;
        $projectDetails['status'] = 'active';
        $projectDetails['visibility'] = 'hidden';
        $projectDetails['category'] = config('epicollect.strings.project_categories.general');
        // Set required form data
        $projectDetails['forms'][0]['name'] = $projectDetails['form_name'];
        $projectDetails['forms'][0]['slug'] = Str::slug($projectDetails['form_name'], '-');
        $projectDetails['forms'][0]['type'] = 'hierarchy';
        $projectDetails['forms'][0]['ref'] = $formRef;

        // Add the Project details
        $this->addProjectDetails($projectDetails);
        // Create new JSON Project Definition
        $this->projectDefinition->create([
            'id' => $projectRef,
            'project' => $projectDetails
        ]);

        // Create new JSON Project Extra
        $this->projectExtra->create([
            'project' => [
                'details' => $projectDetails,
                'forms' => [$formRef]
            ],
            'forms' => [
                $formRef => [
                    'details' => $projectDetails['forms'][0]
                ]
            ]
        ]);
        // Create new JSON Project Mapping
        //$this->projectMapping->create([]);

        $mapping = $this->projectMappingService->createEC5AUTOMapping($this->getProjectExtra()->getData());
        $this->projectMapping->setEC5AUTOMapping($mapping);
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
    ): void {
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
            throw new Exception(config('epicollect.codes.ec5_225'));
        }
        //EC5 AUTO mapping
        $mapping = $this->projectMappingService->createEC5AUTOMapping($this->getProjectExtra()->getData());
        $this->projectMapping->setEC5AUTOMapping($mapping);
        // No need to initialise the Project Stats, as they will be empty
    }

    /**
     * Clone from existing structure
     */
    public function cloneProject(array $params): void
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
     * @return ProjectDTO
     */
    public function addProjectDetails(array $projectDetails): ProjectDTO
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
     * @return ProjectDTO
     */
    public function addProjectDefinition($projectDefinitionData): ProjectDTO
    {
        $this->projectDefinition->init($projectDefinitionData);
        return $this;
    }

    public function addProjectExtra(array|string $projectExtraData): ProjectDTO
    {
        $this->projectExtra->init($projectExtraData);
        return $this;
    }

    public function addProjectMapping(array|string $projectMappingData): ProjectDTO
    {
        $this->projectMapping->init($projectMappingData);
        return $this;
    }

    public function addProjectStats(array $projectStatsData): ProjectDTO
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
    public function updateProjectDetails($data): void
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
     * @return ProjectDefinitionDTO
     */
    public function getProjectDefinition(): ProjectDefinitionDTO
    {
        return $this->projectDefinition;
    }

    /**
     * @return ProjectExtraDTO
     */
    public function getProjectExtra(): ProjectExtraDTO
    {
        return $this->projectExtra;
    }

    /**
     * @return ProjectMappingDTO
     */
    public function getProjectMapping(): ProjectMappingDTO
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }
    /** @noinspection PhpUnused */
    //used in blade template
    public function getUpdatedAt(): string
    {
        return $this->updated_at;
    }

    public function getCreatedAt(): ?string
    {
        return $this->created_at;
    }

    public function getCanBulkUpload(): string
    {
        return $this->can_bulk_upload;
    }

    public function isAppLinkShown(): string
    {
        if (!config('epicollect.setup.system.app_link_enabled')) {
            return false;
        }

        return $this->app_link_visibility === config('epicollect.strings.app_link_visibility.shown');
    }


    public function setEntriesLimits($entriesLimits): void
    {
        $this->projectDefinition->clearEntriesLimits();
        foreach ($entriesLimits as $ref => $params) {
            // If a limit is set
            if ($params['setLimit']) {
                $this->projectDefinition->setEntriesLimit($ref, $params['limitTo']);
            }
        }
    }

    public function isPrivate(): bool
    {
        return $this->access === config('epicollect.strings.project_access.private');
    }

    public function isPublic(): bool
    {
        return $this->access === config('epicollect.strings.project_access.public');
    }

    public function isLocked(): bool
    {
        return $this->status === config('epicollect.strings.project_status.locked');
    }

    public function isTrashed(): bool
    {
        return $this->status === config('epicollect.strings.project_status.trashed');
    }

    public function canBulkUpload(): string
    {
        return $this->can_bulk_upload;
    }
}

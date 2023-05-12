<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Models\Projects\Exceptions\ProjectImportException;
use ec5\Models\Projects\Project;
use Illuminate\Http\Request;
use ec5\Http\Validation\Project\RuleImportRequest as ImportRequestValidator;
use ec5\Http\Validation\Project\RuleImportJson as ImportJsonValidator;
use ec5\Http\Validation\Project\RuleProjectDefinition as ProjectDefinitionValidator;
use ec5\Http\Validation\Project\Mapping\RuleMappingUpdate;
use ec5\Http\Validation\Project\Mapping\RuleMappingStructure;
use ec5\Repositories\QueryBuilder\Project\CreateRepository as CreateProject;
use ec5\Repositories\QueryBuilder\Project\DeleteRepository as DeleteProject;
use ec5\Repositories\QueryBuilder\Project\UpdateRepository as UpdateRep;
use Illuminate\Support\Str;
use Webpatser\Uuid\Uuid;
use Redirect;
use File;

class ProjectImportController extends ProjectCreateController
{

    public function __construct(Request $request, Project $project, UpdateRep $updateRep, DeleteProject $deleteProject)
    {
        parent::__construct($request, $project, $updateRep, $deleteProject);
    }

    public function import(
        Request $request,
        CreateProject $createProject,
        ProjectDefinitionValidator $projectDefinitionValidator,
        ImportJsonValidator $importJsonValidator,
        ImportRequestValidator $importRequestValidator,
        RuleMappingUpdate $mappingUpdateValidator,
        RuleMappingStructure $mappingStructureValidator
    ) {
        $this->type = self::IMPORT;

        // Get all the form post data
        $input = $request->all();
        $input['slug'] = Str::slug($input['name'], '-');

        //trim project name
        $input['name'] = trim($input['name']);

        // Run validation
        $importRequestValidator->validate($input, true);

        // If errors, return
        if ($importRequestValidator->hasErrors()) {
            $request->flash();
            return view('project.project_create')
                ->withErrors($importRequestValidator->errors())
                ->with(['tab' => $this->type]);
        }

        //assign user
        $input['created_by'] = $request->user()->id;
        // Generate new project ref
        $newProjectRef = str_replace('-', '', Uuid::generate(4));

        // Decode json file contents into array
        $file = $request->file('file');
        $data = json_decode(File::get($file->getRealPath()), true);

        // Check properly formatted JSON
        if (json_last_error() != JSON_ERROR_NONE) {
            // todo check this always works - json with extra line breaks for example
            $this->errors['project-import'] = ['ec5_62'];
            return Redirect::to('myprojects/create')
                ->withErrors($this->errors)
                ->with(['tab' => $this->type]);
        }

        // Validate the json from the file
        $importJsonValidator->validate($data);

        if ($importJsonValidator->hasErrors()) {
            $request->flash();
            return Redirect::to('myprojects/create')
                ->withErrors($importJsonValidator->errors())
                ->with(['tab' => $this->type]);
        }

        $projectDefinitionData = $data['data'];
        $projectMapping =  $data['meta']['project_mapping'] ?? [];

        try {
            // Import this project
            $this->project->import(
                $newProjectRef,
                $input['name'],
                $input['created_by'],
                $projectDefinitionData,
                $projectDefinitionValidator,
                $projectMapping,
                $mappingUpdateValidator,
                $mappingStructureValidator
            );
        } catch (ProjectImportException $e) {
            \Log::error($e);
            $request->flash();
            return Redirect::to('myprojects/create')
                ->withErrors($projectDefinitionValidator->errors())
                ->withErrors($mappingUpdateValidator->errors())
                ->withErrors($mappingStructureValidator->errors())
                ->with(['tab' => $this->type]);
        }

        // Try and create, else return DB errors
        $projectId = $createProject->create($this->project);

        //if $projectId = 0 a project was not created so skip avatar creation
        if ($projectId > 0) {
            $errors = $this->createProjectAvatar($projectId);

            if (sizeof($errors) === 0) {
                return Redirect::to('myprojects/' . $this->project->slug)
                    ->with('projectCreated', true)
                    ->with('tab', 'import');
            } else {
                $request->flash();
                return Redirect::to('myprojects/create')
                    ->withErrors($errors)
                    ->with(['tab' => $this->type]);
            }
        }

        // Return db create errors
        $request->flash();
        return Redirect::to('myprojects/create')
            ->withErrors(['db' => ['ec5_116']])
            ->with(['tab' => $this->type]);
    }
}

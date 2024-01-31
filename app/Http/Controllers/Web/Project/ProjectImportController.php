<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\Project\RuleImportJson as ImportJsonValidator;
use ec5\Http\Validation\Project\RuleImportRequest as ImportRequestValidator;
use ec5\Http\Validation\Project\RuleProjectDefinition as ProjectDefinitionValidator;
use ec5\Libraries\Utilities\Generators;
use ec5\Services\ProjectService;
use ec5\Traits\Project\ProjectTools;
use Exception;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Redirect;

class ProjectImportController
{
    use ProjectTools;

    const IMPORT = 'import';

    protected $project;
    protected $type;

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * ProjectCreateController constructor.
     * @param ProjectDTO $project
     */
    public function __construct(ProjectDTO $project)
    {
        $this->project = $project;
    }

    public function import(
        Request                    $request,
        ProjectDefinitionValidator $projectDefinitionValidator,
        ImportJsonValidator        $importJsonValidator,
        ImportRequestValidator     $importRequestValidator,
        ProjectService             $projectService
    )
    {
        $this->type = ProjectImportController::IMPORT;
        // Get all the form post data
        $payload = $request->all();
        $payload['slug'] = Str::slug($payload['name'], '-');
        // Run validation
        $importRequestValidator->validate($payload, true);
        //trim project name and remove extra spaces if any
        $payload['name'] = trim($payload['name']);
        $payload['name'] = preg_replace('/\s+/', ' ', $payload['name']);
        // If errors, return
        if ($importRequestValidator->hasErrors()) {
            $request->flash();
            return view('project.project_create')
                ->withErrors($importRequestValidator->errors())
                ->with(['tab' => $this->type]);
        }

        //assign user
        $payload['created_by'] = $request->user()->id;
        // Generate new project ref
        $newProjectRef = Generators::projectRef();
        // Check the content before decoding
        $file = $request->file('file');
        // Decode json file contents into array
        try {
            $data = json_decode(File::get($file->getRealPath()), true);
        } catch (Exception $e) {
            $request->flash();
            return Redirect::to('myprojects/create')
                ->withErrors(['file' => ['ec5_69']])
                ->with(['tab' => $this->type]);
        }

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

        try {
            // Import this project
            $this->project->import(
                $newProjectRef,
                $payload['name'],
                $payload['created_by'],
                $projectDefinitionData,
                $projectDefinitionValidator
            );
        } catch (Exception $e) {
            $request->flash();
            return Redirect::to('myprojects/create')
                ->withErrors($projectDefinitionValidator->errors())
                ->with(['tab' => $this->type]);
        }

        // Try and create, else return DB errors
        $projectId = $projectService->storeProject($this->project);

        //if $projectId = 0 a project was not created so skip avatar creation
        if ($projectId > 0) {
            $errors = $this->createProjectAvatar(
                $projectId,
                $this->project->ref,
                $this->project->name
            );
            //create avatar
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

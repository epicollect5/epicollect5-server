<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Libraries\Utilities\Generators;
use ec5\Models\Projects\Exceptions\ProjectImportException;
use ec5\Models\Projects\Exceptions\ProjectNameMissingException;
use ec5\Models\Projects\Project;
use Illuminate\Http\Request;
use ec5\Http\Validation\Project\RuleCreateRequest as CreateRequestValidator;
use ec5\Http\Validation\Project\RuleImportRequest as ImportRequestValidator;
use ec5\Http\Validation\Project\RuleImportJson as ImportJsonValidator;
use ec5\Http\Validation\Project\RuleProjectDefinition as ProjectDefinitionValidator;
use ec5\Repositories\QueryBuilder\Project\CreateRepository as CreateProject;
use ec5\Repositories\QueryBuilder\Project\DeleteRepository as DeleteProject;
use ec5\Repositories\QueryBuilder\Project\UpdateRepository as UpdateRep;
use ec5\Models\Images\CreateProjectLogoAvatar;
use Illuminate\Support\Str;
use Redirect;
use File;
use Session;

class ProjectCreateController
{
    const CREATE = 'create';
    const IMPORT = 'import';

    protected $project;
    protected $deleteProject;
    protected $updateRep;
    protected $type;

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * ProjectCreateController constructor.
     * @param Project $project
     * @param UpdateRep $updateRep
     * @param DeleteProject $deleteProject
     */
    public function __construct(Project $project, UpdateRep $updateRep, DeleteProject $deleteProject)
    {
        $this->project = $project;
        $this->updateRep = $updateRep;
        $this->deleteProject = $deleteProject;
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show()
    {
        return view('project.project_create', [
            'tab' => Session::get('tab') ?? ProjectCreateController::CREATE
        ]);
    }


    /**
     * Store a newly created project
     * Create the project, one form and no inputs, if it passes validation
     * create json projectStructure, Mapping for cols and if all good,
     * try and insert into projects, project_roles, project_stats & project_structure,
     * if pass the creating, redirect to my projects list, otherwise, return view with errors
     *
     * @param Request $request
     * @param CreateRequestValidator $createRequestValidator
     * @param CreateProject $createProject
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function create(
        Request                $request,
        CreateRequestValidator $createRequestValidator,
        CreateProject          $createProject
    )
    {
        $this->type = ProjectCreateController::CREATE;
        // todo: send active 'tab' with the response if there are errors (to go back to correct tab)

        // Get all the form post data
        $input = $request->all();
        $input['slug'] = Str::slug($input['name'], '-');

        // Run validation (before trimming)
        $createRequestValidator->validate($input, true);

        //trim metadata strings
        $input['name'] = trim($input['name']);
        $input['form_name'] = trim($input['form_name']);
        $input['small_description'] = trim($input['small_description']);

        //remove extra spaces between words (if any)
        $input['name'] = preg_replace('/\s+/', ' ', $input['name']);
        $input['form_name'] = preg_replace('/\s+/', ' ', $input['form_name']);
        $input['small_description'] = preg_replace('/\s+/', ' ', $input['small_description']);

        // Run validation (after trimming)
        $createRequestValidator->validate($input, true);

        // If errors, return
        if ($createRequestValidator->hasErrors()) {
            $request->flash();
            return view('project.project_create')
                ->withErrors($createRequestValidator->errors())->with(['tab' => $this->type]);
        }

        $input['created_by'] = $request->user()->id;

        // Generate new project ref
        $newProjectRef = Generators::projectRef();

        try {
            // Create everything for this project
            $this->project->create($newProjectRef, $input);
        } catch (ProjectNameMissingException $e) {
            // Return model create errors
            $request->flash();
            return Redirect::to('myprojects/create')
                ->withErrors(['db' => ['ec5_224']])
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
                    ->with('tab', 'create');
            } else {
                $request->flash();
                return Redirect::to('myprojects/create')
                    ->withErrors($errors)
                    ->with(['tab' => $this->type]);
            }
        } else {
            // Return db create errors
            $request->flash();
            return Redirect::to('myprojects/create')
                ->withErrors(['db' => ['ec5_116']])
                ->with(['tab' => $this->type]);
        }
    }

    public function import(
        Request                    $request,
        CreateProject              $createProject,
        ProjectDefinitionValidator $projectDefinitionValidator,
        ImportJsonValidator        $importJsonValidator,
        ImportRequestValidator     $importRequestValidator
    )
    {
        $this->type = ProjectCreateController::IMPORT;
        // Get all the form post data
        $input = $request->all();
        $input['slug'] = Str::slug($input['name'], '-');
        // Run validation
        $importRequestValidator->validate($input, true);
        //trim project name and remove extra spaces if any
        $input['name'] = trim($input['name']);
        $input['name'] = preg_replace('/\s+/', ' ', $input['name']);
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
        $newProjectRef = Generators::projectRef();
        // Check the content before decoding
        $file = $request->file('file');
        // Decode json file contents into array
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

        try {
            // Import this project
            $this->project->import(
                $newProjectRef,
                $input['name'],
                $input['created_by'],
                $projectDefinitionData,
                $projectDefinitionValidator
            );
        } catch (ProjectImportException $e) {
            $request->flash();
            return Redirect::to('myprojects/create')
                ->withErrors($projectDefinitionValidator->errors())
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

    private function createProjectAvatar($projectId)
    {
        //set the newly generated project ID in the model in memory
        $this->project->setId($projectId);

        //generate project logo avatar(s)
        $avatarCreator = new CreateProjectLogoAvatar();
        $wasCreated = $avatarCreator->generate($this->project->ref, $this->project->name);

        if ($wasCreated) {

            unset($input);
            //update logo_url as we are creating an avatar placeholder
            $input['logo_url'] = $this->project->ref;

            if ($this->doUpdate($input)) {
                return [];
            } else {
                // Return db update errors
                return ['db' => ['ec5_104']];
            }
        } else {

            //delete project just created
            //here we assume the deletion cannot fail!!!
            $this->deleteProject->delete($projectId);

            //error generating project avatar, handle it!
            return ['avatar' => ['ec5_348']];
        }
    }

    /**
     * Update the project in db
     *
     * @param $input
     * @return bool
     */
    private function doUpdate($input)
    {
        // Update the Definition and Extra data
        $this->project->updateProjectDetails($input);
        // Update in the database
        return $this->updateRep->updateProject($this->project, $input, false);
    }
}

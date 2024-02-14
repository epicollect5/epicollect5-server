<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\Project\RuleCreateRequest;
use ec5\Libraries\Utilities\Generators;
use ec5\Services\Project\ProjectService;
use ec5\Traits\Project\ProjectTools;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Redirect;
use Session;

class ProjectCreateController
{
    use ProjectTools;

    const CREATE = 'create';

    protected $project;
    protected $type;

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @param ProjectDTO $project
     */
    public function __construct(ProjectDTO $project)
    {
        $this->project = $project;
    }

    public function show()
    {
        return view('project.project_create', [
            'tab' => Session::get('tab') ?? ProjectCreateController::CREATE
        ]);
    }


    /**
     * Store a newly created project,
     * Create the project, one form and no inputs, if it passes validation
     * create json projectStructure, Mapping for cols and if all good,
     * try and insert into projects, project_roles, project_stats & project_structure,
     * if pass the creating, redirect to my projects list, otherwise, return view with errors
     *
     * @param Request $request
     * @param RuleCreateRequest $ruleCreateRequest
     * @param ProjectService $projectService
     * @return Factory|Application|RedirectResponse|View
     */
    public function create(
        Request           $request,
        RuleCreateRequest $ruleCreateRequest,
        ProjectService    $projectService
    )
    {
        $this->type = ProjectCreateController::CREATE;
        // todo: send active 'tab' with the response if there are errors (to go back to correct tab)

        // Get all the form post data
        $payload = $request->all();
        $payload['slug'] = Str::slug($payload['name'], '-');

        // Run validation (before trimming)
        $ruleCreateRequest->validate($payload, true);

        //trim metadata strings
        $payload['name'] = trim($payload['name']);
        $payload['form_name'] = trim($payload['form_name']);
        $payload['small_description'] = trim($payload['small_description']);

        //remove extra spaces between words (if any)
        $payload['name'] = preg_replace('/\s+/', ' ', $payload['name']);
        $payload['form_name'] = preg_replace('/\s+/', ' ', $payload['form_name']);
        $payload['small_description'] = preg_replace('/\s+/', ' ', $payload['small_description']);

        // Run validation (after trimming)
        $ruleCreateRequest->validate($payload, true);
        if ($ruleCreateRequest->hasErrors()) {
            $request->flash();
            return view('project.project_create')
                ->withErrors($ruleCreateRequest->errors())->with(['tab' => $this->type]);
        }

        $payload['created_by'] = $request->user()->id;
        // Generate new project ref
        $newProjectRef = Generators::projectRef();
        try {
            // Create everything for this project
            $this->project->create($newProjectRef, $payload);
        } catch (Exception $e) {
            $request->flash();
            return Redirect::to('myprojects/create')
                ->withErrors(['db' => ['ec5_224']])
                ->with(['tab' => $this->type]);
        }

        //store project and related data
        $projectId = $projectService->storeProject($this->project);
        if ($projectId === 0) {
            //if $projectId = 0 a project was not created, so skip avatar creation
            $request->flash();
            return Redirect::to('myprojects/create')
                ->withErrors(['db' => ['ec5_116']])
                ->with(['tab' => $this->type]);
        }

        $errors = $this->createProjectAvatar(
            $projectId,
            $this->project->ref,
            $this->project->name
        );

        if (sizeof($errors) === 0) {
            return Redirect::to('myprojects/' . $this->project->slug)
                ->with('projectCreated', true)
                ->with('tab', 'create');
        } else {
            //project creates but avatar failed, anyway, users can upload an image
            $request->flash();
            return Redirect::to('myprojects/create')
                ->withErrors($errors)
                ->with(['tab' => $this->type]);
        }
    }
}

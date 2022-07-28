<?php

namespace ec5\Http\Controllers\Api\Projects;


use ec5\Http\Controllers\Controller;
use ec5\Repositories\QueryBuilder\Project\SearchRepository as ProjectSearch;
use ec5\Http\Validation\Project\RuleName as Validator;
use ec5\Http\Controllers\Api\ApiResponse as ApiResponse;
use Illuminate\Support\Str;


class ProjectController extends Controller
{

    /**
     * @var
     */
    protected $projectSearch;

    /**
     * Create a new project controller instance.
     *
     * @param ProjectSearch $projectSearch
     * ProjectRoleCreate $projectRoleCreate
     */
    public function __construct(ProjectSearch $projectSearch)
    {
        $this->projectSearch = $projectSearch;
    }

    /**
     * Search for a project.
     *
     * @param ApiResponse $apiResponse
     * @param $name
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Get(
     *   path="/api/projects/{name}",
     *   summary="Search for projects",
     *   tags={"projects"},
     *   operationId="search",
     *   produces={"application/json"},
     *   @SWG\Parameter(
     *     name="name",
     *     in="path",
     *     description="Project name.",
     *     required=true,
     *     type="string",
     *     default="ec5"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=400, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function search(ApiResponse $apiResponse, $name = '')
    {
        $searchResults = [];
        $projects = [];

        if (!empty($name)) {
            $searchResults = $this->projectSearch->startsWith($name, ['name', 'slug', 'access', 'ref']);
        }

        // todo make this more efficient? ie don't loop twice
        // Build the json api response
        foreach ($searchResults as $searchResult) {

            //HACK for COG-UK *********************************
            if ($searchResult->ref !== env('COGUK_REF', '')) {

                $data['type'] = 'project';
                $data['id'] = $searchResult->ref;
                $data['project'] = $searchResult;

                $projects[] = $data;
            }
        }

        // Set the data
        $apiResponse->setData($projects);

        return $apiResponse->toJsonResponse('200', $options = 0);
    }

    /**
     * Check for duplicate names for projects
     *
     * @param ApiResponse $apiResponse
     * @param Validator $validator
     * @param $name
     * @return \Illuminate\Http\JsonResponse
     */
    public function exists(ApiResponse $apiResponse, Validator $validator, $name)
    {

        $input['name'] = $name;
        $input['slug'] = Str::slug($name, '-');
        // Run validation
        $validator->validate($input);
        // todo should type, id, attributes be setter methods in ApiResponse ?
        $apiResponse->setData([
            'type' => 'exists',
            'id' => $input['name'],
            'attributes' => [
                'exists' => $validator->hasErrors()
            ]
        ]);
        return $apiResponse->toJsonResponse('200', $options = 0);
    }

    /**
     * Get the project version
     *
     * @param ApiResponse $apiResponse
     * @param $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function version(ApiResponse $apiResponse, $slug)
    {

        $hasProject = $this->projectSearch->find($slug, $columns = array('*'));

        // If no project found, error out
        if (!$hasProject) {
            $errors = ['version' => ['ec5_11']];
            return $apiResponse->errorResponse('500', $errors);
        }

        // Return structure_last_updated date
        // todo should type, id, attributes be setter methods in ApiResponse ?
        $apiResponse->setData([
            'type' => 'project-version',
            'id' => $slug,
            'attributes' => [
                'structure_last_updated' => $hasProject->structure_last_updated
            ]

        ]);
        return $apiResponse->toJsonResponse('200', $options = 0);
    }
}

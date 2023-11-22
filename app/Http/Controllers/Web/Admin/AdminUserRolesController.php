<?php

namespace ec5\Http\Controllers\Web\Admin;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Controller;

use ec5\Http\Validation\Admin\RuleProjectRole as ProjectRoleValidator;

use ec5\Repositories\QueryBuilder\ProjectRole\CreateRepository as ProjectRoleCreate;
use ec5\Repositories\QueryBuilder\ProjectRole\DeleteRepository as ProjectRoleDelete;

use Illuminate\Http\Request;

class AdminUserRolesController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Manage Project Users Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the management of project users from a server administrator.
    |
    */

    protected $projectRoleCreate;
    protected $projectRoleDelete;

    /**
     * Create a new manager project users controller instance.
     *
     * @param ProjectRoleCreate $projectRoleCreate
     * @param ProjectRoleDelete $projectRoleDelete
     */
    public function __construct(ProjectRoleCreate $projectRoleCreate, ProjectRoleDelete $projectRoleDelete)
    {
        $this->projectRoleCreate = $projectRoleCreate;
        $this->projectRoleDelete = $projectRoleDelete;
    }

    public function update(Request $request, ApiResponse $apiResponse, ProjectRoleValidator $projectRoleValidator)
    {
        // Get request data
        $input = $request->all();

        // Validate the data
        $projectRoleValidator->validate($input);
        if ($projectRoleValidator->hasErrors()) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, $projectRoleValidator->errors());
            }
            return redirect()->back()->withErrors($projectRoleValidator->errors());
        }

        $role = $input['role'];
        $projectId = $input['project_id'];
        $adminUser = $request->user();

        // Remove current role for the admin user
        $this->projectRoleDelete->delete($adminUser->id, $projectId);

        // Add new role
        if (!empty($role)) {
            // Attempt to update the user's role
            if (!$this->projectRoleCreate->create($adminUser->id, $projectId, $role)) {

                $errors = $this->projectRoleCreate->errors();

                if ($request->ajax()) {
                    return $apiResponse->errorResponse(400, ['update-admin-project-role' => $errors, 'adminUser' => $adminUser]);
                }

                // Redirect back to admin page
                return redirect()->back()->withErrors($errors);

            }

        }

        // If ajax, return success response
        if ($request->ajax()) {
            return $apiResponse->successResponse('ec5_241');
        }
        // Redirect back to admin page
        return redirect()->back();
    }
}

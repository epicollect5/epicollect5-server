<?php

namespace ec5\Http\Controllers\Web\Admin;

use ec5\Http\Controllers\Controller;
use ec5\Http\Validation\Admin\RuleProjectRole as ProjectRoleValidator;
use ec5\Models\Project\ProjectRole;
use Exception;
use Log;
use Response;

class AdminUserRolesController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Manage Admin Users
    |--------------------------------------------------------------------------
    |
    | This controller handles the management of project users from a server administrator.
    |
    */

    public function update(ProjectRoleValidator $projectRoleValidator)
    {
        // Get request data
        $input = request()->all();
        // Validate the data
        $projectRoleValidator->validate($input);
        if ($projectRoleValidator->hasErrors()) {
            if (request()->ajax()) {
                return Response::apiErrorCode(400, $projectRoleValidator->errors());
            }
            return redirect()->back()->withErrors($projectRoleValidator->errors());
        }

        $role = $input['role'];
        $projectId = $input['project_id'];
        $adminUser = request()->user();

        try {
            // Remove the current role for the admin user
            ProjectRole::where('user_id', $adminUser->id)
                ->where('project_id', $projectId)
                ->delete();
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            if (request()->ajax()) {
                return Response::apiErrorCode(400, ['update-admin-project-role' => ['ec5_104']]);
            }
            // Redirect back to admin page
            return redirect()->back()->withErrors(['update-admin-project-role' => ['ec5_104']]);
        }

        // Add the new role
        if (!empty($role)) {
            //Update the user's role
            $projectRole = ProjectRole::where('user_id', $adminUser->id)
                ->where('project_id', $projectId)->first();
            $projectRole->role = $role;
            $projectRole->save();
        }

        // If ajax, return success response
        if (request()->ajax()) {
            return Response::apiSuccessCode('ec5_241');
        }
        // Redirect back to admin page
        return redirect()->back();
    }
}

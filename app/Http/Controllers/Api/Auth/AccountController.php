<?php

namespace ec5\Http\Controllers\Api\Auth;

use Config;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Controller;
use ec5\Mail\UserAccountDeletionAdmin;
use ec5\Mail\UserAccountDeletionConfirmation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use ec5\Mail\UserAccountDeletionUser;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\User;
use Exception;
use Illuminate\Support\Facades\DB;
use ec5\Traits\Eloquent\Archive;
use ec5\Models\Eloquent\Project;

class AccountController extends Controller
{
    use Archive;

    protected $apiResponse;

    function __construct(ApiResponse $apiResponse)
    {
        $this->apiResponse = $apiResponse;
    }

    public function handleDeletionRequest()
    {
        //get user email
        $email = Auth::user()->email;
        //get route name
        $routeName = request()->route()->getName();
        //find any projects the user has a role
        $userId = Auth::user()->id;
        $projectRoles = ProjectRole::where('user_id', $userId)->get();

        //if a user is NOT a member of any projects
        if (sizeof($projectRoles) === 0) {
            if (!$this->handleDeleteWithoutProjectRoles($email)) {
                return $this->apiResponse->errorResponse(400, [
                    'account-deletion' => ['ec5_103']
                ]);
            }
        }

        //if the user IS a member of some projects
        if (sizeof($projectRoles) > 0) {
            if (!$this->handleDeleteWithProjectRoles($projectRoles, $email)) {
                return $this->apiResponse->errorResponse(400, [
                    'account-deletion' => ['ec5_103']
                ]);
            }
        }
        //**Account deleted successfully**

        //request from the web? (internal api)
        if ($routeName === 'internalAccountDelete') {
            //on the web, log user out from  session
            Auth::logout();
            request()->session()->flush();
            request()->session()->regenerate();
        }

        //return a JSON response 
        // about account deleted, will trigger a page refresh on the web
        // or logout users from the mobile app
        $data = ['id' => 'account-deletion-performed', 'deleted' => true];
        $this->apiResponse->setData($data);
        return $this->apiResponse->toJsonResponse(200, 0);
    }

    private function handleDeleteWithoutProjectRoles($email)
    {
        //user is not a member of any projects so just remove 
        DB::beginTransaction();
        try {
            $user = User::where('email', $email);
            $user->delete();
            DB::commit();
            $this->sendAccountDeletionConfirmationEmail($email);
            return true;
        } catch (\Exception $e) {
            \Log::error('Error deleting account ', [
                'exception' => $e->getMessage()
            ]);
            DB::rollBack();
            return false;
        }
    }

    private function handleDeleteWithProjectRoles($projectRoles, $email)
    {
        $roles = Config::get('ec5Strings.project_roles');
        //per each project, act based on user role on that project
        DB::beginTransaction();
        try {
            foreach ($projectRoles as $projectRole) {

                $projectId = $projectRole->project_id;
                $userId = $projectRole->user_id;

                switch ($projectRole->role) {
                    case $roles['creator']:
                        $slug = Project::where('id', $projectId)
                            ->where('created_by',  $userId)->value('slug');

                        //1 - archive project and entries
                        if (!($this->archiveProject($projectId, $slug)
                            &&
                            $this->archiveEntries($projectId))) {
                            throw new \Exception('Archive project & entries error');
                        }
                        //todo: check project roles when a project is archived
                        //2 - remove user role
                        $projectRole->delete();
                        break;
                    case $roles['manager']:
                    case $roles['curator']:
                    case $roles['collector']:
                    case $roles['viewer']:
                        //we do not touch the entries currently, but remove the user from the project
                        //entries will become anonymous anyway
                        $projectRole->delete();
                        break;
                }
            }
            $user = User::where('email', $email)->where('id', $userId);
            $user->delete();
            DB::commit();
            $this->sendAccountDeletionConfirmationEmail($email);
            return true;
        } catch (Exception $e) {
            \Log::error('Error deleting account ', [
                'exception' => $e->getMessage()
            ]);
            DB::rollBack();
            return false;
        }
    }

    private function sendAccountDeletionEmails($email)
    {
        //send confirmation email to user
        try {
            Mail::to($email)->send(new UserAccountDeletionUser());
        } catch (Exception $e) {
            return $this->apiResponse->errorResponse(400, [
                'account-deletion' => ['ec5_103']
            ]);
        }

        //send request to admin
        try {
            Mail::to(env('SYSTEM_EMAIL'))->send(new UserAccountDeletionAdmin($email));
        } catch (Exception $e) {
            return $this->apiResponse->errorResponse(400, [
                'account-deletion' => ['ec5_103']
            ]);
        }

        if (Mail::failures()) {
            return $this->apiResponse->errorResponse(400, ['account-deletion' => 'ec5_103']);
        }

        //return a JSON response (request accepted)
        $data = ['id' => 'account-deletion-request', 'accepted' => true];
        $this->apiResponse->setData($data);

        return $this->apiResponse->toJsonResponse(200, 0);
    }

    private function sendAccountDeletionConfirmationEmail($email)
    {
        //send confirmation email to user
        try {
            Mail::to($email)->send(new UserAccountDeletionConfirmation());
        } catch (Exception $e) {
            //mail not sent, not show stopping, just log why
            \Log::error('Account Deletion Confirmation email error', [
                'exception' => $e->getMessage()
            ]);
        }
    }
}

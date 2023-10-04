<?php

namespace ec5\Http\Controllers\Api\Auth;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Controller;
use ec5\Mail\UserAccountDeletionAdmin;
use ec5\Mail\UserAccountDeletionConfirmation;
use ec5\Models\Eloquent\ProjectFeatured;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use ec5\Mail\UserAccountDeletionUser;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\User;
use ec5\Traits\Eloquent\Archive;

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
        $creatorRole = Config('ec5Strings.project_roles.creator');
        //get route name
        $routeName = request()->route()->getName();
        //find any projects the user has a role
        $userId = Auth::user()->id;
        $userProjectRoles = ProjectRole::where('user_id', $userId)->get();

        //request from the web?
        if ($routeName === 'internalAccountDelete') {
            if (sizeof($userProjectRoles) === 0) {
                //user is not a member of any projects so just remove 
                return $this->removeUserWeb($email, $userId);
            } else {
                //user has roles in projects, but if no CREATOR role found, remove it
                $projectsWithCreatorRoles = [];
                foreach ($userProjectRoles as $userProjectRole) {
                    if ($userProjectRole['role'] === $creatorRole) {
                        $projectsWithCreatorRoles[] = $userProjectRole;
                    }
                }
                if (sizeOf($projectsWithCreatorRoles) > 0) {
                    //archive projects created by this user before removing
                    try {
                        DB::beginTransaction();
                        $this->archiveProjectsCreatedByUser($projectsWithCreatorRoles, $userId);
                        DB::commit();
                        return $this->removeUserWeb($email, $userId);
                    } catch (\Exception $e) {
                        \Log::error('Archive projects created by user failure', ['exception' => $e->getMessage()]);
                        DB::rollBack();
                        return $this->apiResponse->errorResponse(400, [
                            'account-deletion' => ['ec5_104']
                        ]);
                    }
                } else {
                    //delete user directly (entries will be anonymized)
                    return $this->removeUserWeb($email, $userId);
                }
            }
        }
        //request from the mobile app?
        if ($routeName === 'externalAccountDelete') {
            //imp:this is a request from the mobile app, needs api response
            if (sizeof($userProjectRoles) === 0) {
                //user is not a member of any projects so just remove
                return $this->removeUserApi($email);
            } else {
                //user has roles in projects, but if no CREATOR role found, remove it
                $projectsWithCreatorRoles = [];
                foreach ($userProjectRoles as $userProjectRole) {
                    if ($userProjectRole['role'] === $creatorRole) {
                        $projectsWithCreatorRoles[] = $userProjectRole;
                    }
                }

                if (sizeOf($projectsWithCreatorRoles) > 0) {
                    //archive projects created by this user before removing
                    try {
                        DB::beginTransaction();
                        $this->archiveProjectsCreatedByUser($projectsWithCreatorRoles, $userId);
                        DB::commit();
                        return $this->removeUserApi($email, $userId);
                    } catch (\Exception $e) {
                        \Log::error('Archive projects created by user failure', ['exception' => $e->getMessage()]);
                        DB::rollBack();
                        return $this->apiResponse->errorResponse(400, [
                            'account-deletion' => ['ec5_104']
                        ]);
                    }
                } else {
                    //delete user directly (entries will be anonymized)
                    //user is not a member of any projects so just remove
                    return $this->removeUserApi($email);
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected function archiveProjectsCreatedByUser($projects, $userId)
    {
        foreach ($projects as $project) {
            $projectId = $project['project_id'];
            $projectSlug = Project::where('id', $projectId)
                ->where('created_by', $userId)
                ->value('slug');

            $testProject = Project::where('id', $projectId)
                ->where('created_by', $userId)->get();


            //if any of the projects is a featured one, throw error
            //as we need to deal with them manually
            $isFeatured = ProjectFeatured::where('project_id', $projectId)->exists();
            if ($isFeatured) {
                throw new \Exception('Project archive failed because is featured project');
            }


            if (!$this->archiveProject($projectId, $projectSlug)) {
                throw new \Exception('Project created by user archive failed');
            }
        }
    }

    private
    function sendAccountDeletionEmails($email): \Illuminate\Http\JsonResponse
    {
        //send confirmation email to user
        try {
            Mail::to($email)->send(new UserAccountDeletionUser());
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse(400, [
                'account-deletion' => ['ec5_103']
            ]);
        }

        //send request to admin
        try {
            Mail::to(env('SYSTEM_EMAIL'))->send(new UserAccountDeletionAdmin($email));
        } catch (\Exception $e) {
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

    private
    function removeUserWeb($email, $userId): \Illuminate\Http\JsonResponse
    {
        $user = User::where('email', $email)->where('id', $userId);
        $user->delete();

        //log user out
        Auth::logout();
        request()->session()->flush();
        request()->session()->regenerate();

        //send confirmation email to user
        try {
            Mail::to($email)->send(new UserAccountDeletionConfirmation());
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse(400, [
                'account-deletion' => ['ec5_103']
            ]);
        }

        //return a JSON response (account deleted, will trigger a page refresh)
        $data = ['id' => 'account-deletion-performed', 'deleted' => true];
        $this->apiResponse->setData($data);

        return $this->apiResponse->toJsonResponse(200, 0);
    }

    private
    function removeUserApi($email): \Illuminate\Http\JsonResponse
    {
        $user = User::where('email', $email);
        $user->delete();

        //send confirmation email to user
        try {
            Mail::to($email)->send(new UserAccountDeletionConfirmation());
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse(400, [
                'account-deletion' => ['ec5_103']
            ]);
        }

        //return a JSON response (account deleted, will trigger a logout on the mobile app)
        $data = ['id' => 'account-deletion-performed', 'deleted' => true];
        $this->apiResponse->setData($data);

        return $this->apiResponse->toJsonResponse(200, 0);
    }
}

<?php

/** @noinspection PhpInconsistentReturnPointsInspection */

namespace ec5\Http\Controllers\Api\Auth;

use ec5\Http\Controllers\Controller;
use ec5\Mail\UserAccountDeletionConfirmation;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectFeatured;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\User\User;
use ec5\Traits\Eloquent\Archiver;
use ec5\Traits\Eloquent\Remover;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Log;
use Response;
use Throwable;

class AccountController extends Controller
{
    use Archiver;
    use Remover;

    /**
     * @throws Throwable
     * @noinspection PhpInconsistentReturnPointsInspection
     */
    public function handleDeletionRequest()
    {
        //get user email
        $email = Auth::user()->email;
        $creatorRole = Config('epicollect.strings.project_roles.creator');
        //get route name
        $routeName = request()->route()->getName();
        //find any projects the user has a role
        $userId = Auth::user()->id;
        $userProjectRoles = ProjectRole::where('user_id', $userId)->get();

        //request from the web?
        if ($routeName === 'internalAccountDelete') {
            if (sizeof($userProjectRoles) === 0) {
                //user is not a member of any projects so remove it directly
                //(imp: the user ID for entries added to public projects is never considered)
                if ($this->removeUser($email, $userId)) {
                    $this->logoutUser();
                    return $this->sendResponseSuccess($email);
                } else {
                    return $this->sendResponseError();
                }
            } else {

                //imp: we archive the user instead of removing it because
                //it could have added entries to private projects
                //and due to an old mysql bug, a new user could get an existing ID
                //edge case but better safe than sorry

                //user has roles in projects, but if no CREATOR role found, remove it
                $projectsWithCreatorRoles = [];
                foreach ($userProjectRoles as $userProjectRole) {
                    if ($userProjectRole['role'] === $creatorRole) {
                        $projectsWithCreatorRoles[] = $userProjectRole;
                    }
                }
                if (sizeOf($projectsWithCreatorRoles) > 0) {
                    //archive projects created by this user before archiving user
                    $areProjectsArchived = $this->archiveProjectsCreatedByUser($projectsWithCreatorRoles, $userId);
                    $isUserArchived = $this->archiveUser($email, $userId);
                    if ($areProjectsArchived && $isUserArchived) {
                        $this->logoutUser();
                        return $this->sendResponseSuccess($email);
                    } else {
                        return $this->sendResponseError();
                    }
                } else {
                    if ($this->archiveUser($email, $userId)) {
                        $this->logoutUser();
                        return $this->sendResponseSuccess($email);
                    } else {
                        return $this->sendResponseError();
                    }
                }
            }
        }
        //request from the mobile app?
        if ($routeName === 'externalAccountDelete') {
            if (sizeof($userProjectRoles) === 0) {
                //user is not a member of any projects so just remove
                if ($this->removeUser($email, $userId)) {
                    return $this->sendResponseSuccess($email);
                } else {
                    return $this->sendResponseError();
                }
            } else {
                //user has roles in projects, but if no CREATOR role found, remove it
                $projectsWithCreatorRoles = [];
                foreach ($userProjectRoles as $userProjectRole) {
                    if ($userProjectRole['role'] === $creatorRole) {
                        $projectsWithCreatorRoles[] = $userProjectRole;
                    }
                }

                if (sizeOf($projectsWithCreatorRoles) > 0) {
                    //archive projects created by this user before archiving the user
                    $areProjectsArchived = $this->archiveProjectsCreatedByUser($projectsWithCreatorRoles, $userId);
                    $isUserArchived = $this->archiveUser($email, $userId);
                    if ($areProjectsArchived && $isUserArchived) {
                        return $this->sendResponseSuccess($email);
                    } else {
                        return $this->sendResponseError();
                    }
                } else {
                    if ($this->archiveUser($email, $userId)) {
                        return $this->sendResponseSuccess($email);
                    } else {
                        return $this->sendResponseError();
                    }
                }
            }
        }
    }

    protected function archiveProjectsCreatedByUser($projects, $userId)
    {
        try {
            DB::beginTransaction();
            foreach ($projects as $project) {
                $projectId = $project['project_id'];

                //get slug (skip already archived projects)
                $projectSlug = Project::where('id', $projectId)
                    ->where('created_by', $userId)
                    ->where('status', '<>', config('epicollect.strings.project_status.archived'))
                    ->value('slug');

                //if any of the projects is featured, throw error
                //as we need to deal with them manually
                $isFeatured = ProjectFeatured::where('project_id', $projectId)->exists();
                if ($isFeatured) {
                    throw new Exception('Project archive failed because is featured project');
                }

                $projectStat = ProjectStats::where('project_id', $projectId)->first();
                if ($projectStat->total_entries === 0) {
                    //if the project has no entries, it can be removed
                    if (!$this->removeProject($projectId, $projectSlug)) {
                        throw new Exception('Project created by user removal failed');
                    }
                } else {
                    //otherwise, just archive without deletion
                    if (!$this->archiveProject($projectId, $projectSlug)) {
                        throw new Exception('Project created by user archive failed');
                    }
                }
            }
            DB::commit();
            return true;
        } catch (Throwable $e) {
            Log::error('Error archiveProjectsCreatedByUser()', ['exception' => $e->getMessage()]);
            try {
                DB::rollBack();
                return false;
            } catch (Throwable $e) {
                Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            }
        }
    }

    private function sendResponseSuccess($email)
    {
        //send confirmation email to user
        try {
            Mail::to($email)->send(new UserAccountDeletionConfirmation($email));
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            return Response::apiErrorCode(400, [
                'account-deletion' => ['ec5_103']
            ]);
        }
        //return a JSON response (account deleted, will trigger a page refresh)
        $data = ['id' => 'account-deletion-performed', 'deleted' => true];
        return Response::apiData($data);

    }

    private function sendResponseError()
    {
        return Response::apiErrorCode(400, [
            'account-deletion' => ['ec5_104']
        ]);
    }

    /**
     * @throws Throwable
     */
    private function removeUser($email, $userId)
    {
        try {
            DB::beginTransaction();
            $user = User::where('id', $userId)->where('email', $email);
            if ($user->delete()) {
                DB::commit();
                return true;
            } else {
                DB::rollBack();
                return false;
            }

        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }

    private function logoutUser()
    {
        Auth::logout();
        request()->session()->flush();
        request()->session()->regenerate();
    }
}

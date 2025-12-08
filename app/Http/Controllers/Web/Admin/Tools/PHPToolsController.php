<?php

namespace ec5\Http\Controllers\Web\Admin\Tools;

use Auth;
use Carbon\Carbon;
use ec5\DTO\ProjectDTO;
use ec5\Libraries\Utilities\Generators;
use ec5\Libraries\Utilities\GPointConverter;
use ec5\Mail\DebugEmailSending;
use ec5\Models\Project\Project;
use ec5\Services\Project\ProjectAvatarService;
use ec5\Traits\Eloquent\System\ProjectsStats;
use Illuminate\Support\Facades\Mail;
use Random\RandomException;
use Storage;
use Throwable;

class PHPToolsController
{
    use ProjectsStats;

    protected ProjectDTO $project;

    public function __construct(ProjectDTO $project)
    {
        $this->project = $project;
    }

    //bust opcache on command
    public function resetOpcache()
    {
        if (opcache_reset()) {
            return 'Opcache cleared';
        }
        return 'Could not clear Opcache';
    }

    public function showProjectsStats()
    {

        //$result = $this->getProjectTotalByThreshold(1000, 10000);

        //return response()->json($result);

        $this->textLLtoUTM();
    }

    public function textLLtoUTM()
    {
        $converter = new GPointConverter();
        $converter->setLongLat(-0.019106, 51.583362);
        $converter->convertLLtoTM(null);
        $converter->printUTM();
        $converter->printLatLong();
        //return response($converter->printUTM());

    }

    /**
     * @throws Throwable
     */
    public function createProjectAvatar($ref = 'b8a4ac0a586b46dd8ad41ecf9eff39a7')
    {
        //get project name
        $name = Project::where('ref', $ref)->pluck('name')->first();
        $id = Project::where('ref', $ref)->pluck('id')->first();

        //check there is not a logo already
        $files = Storage::disk('project')->allFiles($ref);

        if (sizeof($files) === 0) {

            //set the newly generated project ID in the model in memory
            $this->project->setId($id);

            //generate project logo avatar(s)
            $avatarCreator = new ProjectAvatarService();
            $wasCreated = $avatarCreator->generate($ref, $name);

            // dd($wasCreated, $ref, $name);

            if ($wasCreated) {
                //update logo_url as we are creating an avatar placeholder
                $input['logo_url'] = $ref;

                if ($this->doUpdate($input)) {
                    return 'Logo created for ' . $name;
                } else {
                    // Return db update errors
                    return 'Error creating logo for ' . $name;
                }
            }
        }
        return 'Project "' . $name . '" already has a logo';
    }

    /**
     * @throws Throwable
     */
    private function doUpdate($params)
    {
        // Update the Definition and Extra data
        $this->project->updateProjectDetails($params);

        // Update in the database
        return Project::updateAllTables($this->project, $params, false);
    }

    public function sendSuperAdminEmail()
    {
        //send test email to verify it is all working
        try {
            Mail::to(config('epicollect.setup.super_admin_user.email'))->send(new DebugEmailSending());
            return 'Mail sent.';
        } catch (Throwable $e) {
            return 'Failed -> ' . $e->getMessage();
        }
    }

    public function sendSystemEmail()
    {
        //send test email to verify it is all working
        try {
            Mail::to(config('epicollect.setup.system.email'))->send(new DebugEmailSending());
            return 'Mail sent.';
        } catch (Throwable $e) {
            return 'Failed -> ' . $e->getMessage();
        }
    }

    /**
     * @throws RandomException
     */
    public function previewEmail()
    {
        $userName = Auth::user()->name;
        $code = random_int(100000, 999999);
        $token = 'xxxxxxxxxxxxxxxxxxxxxxxx';
        $url = route('login-reset', $token);

        $expireAt = Carbon::now()
            ->subSeconds(config('auth.jwt-forgot.expire'))
            ->diffForHumans(Carbon::now(), true);

        //        return view('emails.user_registration', [
        //            'name' => $userName,
        //            'code' => $code,
        //            'url' => $url
        //        ]);

        return view('emails.user_passwordless_web', [
            'name' => $userName,
            'code' => $code,
            'url' => $url,
            'expireAt' => $expireAt
        ]);
    }

    #[NoReturn] public function hash()
    {
        dd(bcrypt('my-password', ['rounds' => 12]));
        // dd('test', bcrypt('test', ['rounds' => 12]), bcrypt('test', ['rounds' => 12]), bcrypt('test', ['rounds' => 12]));
    }

    public function codes($howMany = 1)
    {
        $codes = [];
        for ($i = 0; $i < $howMany; $i++) {
            $codes[] = Generators::randomNumber(6, 1);
        }
        return $codes;
    }

    public function carbon()
    {
        return 'Carbon::now()->startOfMonth() = ' . Carbon::now()->startOfMonth();
    }
}

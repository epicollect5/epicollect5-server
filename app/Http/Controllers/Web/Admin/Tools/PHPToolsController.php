<?php

namespace ec5\Http\Controllers\Web\Admin\Tools;

use ec5\Traits\Eloquent\ProjectsStats;
use ec5\Libraries\Utilities\GpointConverter;
use Config;
use ec5\Models\Eloquent\Project;
use ec5\Models\Projects\Project as LegacyProject;
use Storage;
use ec5\Models\Images\CreateProjectLogoAvatar;
use ec5\Http\Controllers\ProjectControllerBase;
use Illuminate\Http\Request;
use ec5\Repositories\QueryBuilder\Project\UpdateRepository as UpdateRep;
use Illuminate\Support\Facades\Mail;
use Auth;
use Carbon\Carbon;
use ec5\Libraries\Utilities\Generators;
use ec5\Mail\DebugEmailSending;

use Exception;

class PHPToolsController extends ProjectControllerBase
{
    use ProjectsStats;

    protected $project;
    protected $updateRep;

    public function __construct(Request $request, LegacyProject $project, UpdateRep $updateRep)
    {
        parent::__construct($request);

        $this->project = $project;
        $this->updateRep = $updateRep;
    }

    //bust opcache on command
    public function resetOpcache()
    {
        if (opcache_reset()) {
            return 'Opcache cleared';
        }
        return 'Could not clear Opcache';
    }

    public function showPHPInfo()
    {
        phpInfo();
    }

    public function showProjectsStats()
    {

        //$result = $this->getProjectTotalByThreshold(1000, 10000);

        //return response()->json($result);

        $this->textLLtoUTM();
    }

    public function textLLtoUTM()
    {
        $converter = new GpointConverter();
        $converter->setLongLat(-0.019106, 51.583362);
        $converter->convertLLtoTM(null);
        $converter->printUTM();
        $converter->printLatLong();
        //return response($converter->printUTM());

    }

    public function avatarPalette()
    {
        return view('admin.avatar_palette', ['colors' => Config::get('laravolt.avatar.backgrounds')]);
    }

    public function createProjectAvatar($ref = 'b8a4ac0a586b46dd8ad41ecf9eff39a7')
    {
        //get project name
        $name = Project::where('ref', $ref)->pluck('name')->first();
        $id = Project::where('ref', $ref)->pluck('id')->first();

        //check there is not a logo already
        $files = Storage::disk('project_thumb')->allFiles($ref);

        if (sizeof($files) === 0) {

            //set the newly generated project ID in the model in memory
            $this->project->setId($id);

            //generate project logo avatar(s)
            $avatarCreator = new CreateProjectLogoAvatar();
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

    private function doUpdate($input)
    {
        // Update the Definition and Extra data
        $this->project->updateProjectDetails($input);

        // Update in the database
        return $this->updateRep->updateProject($this->project, $input, false);
    }

    public function sendEmail()
    {
        //send test email to verify it is all working
        try {
            Mail::to(Config::get('ec5Setup.super_admin_user.email'))->send(new DebugEmailSending());
            return 'Mail sent.';
        } catch (Exception $e) {
            return 'Failed -> ' . $e->getMessage();
        }
    }

    public function previewEmail()
    {
        $userName = Auth::user()->name;
        $code = random_int(100000, 999999);
        $token = 'xxxxxxxxxxxxxxxxxxxxxxxx';
        $url = route('login-reset', $token);

        $expireAt = Carbon::now()
            ->subSeconds(Config::get('auth.jwt-forgot.expire'))
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

    public function hash()
    {
        dd(bcrypt('my-password', ['rounds' => 12]));
        dd('test', bcrypt('test', ['rounds' => 12]), bcrypt('test', ['rounds' => 12]), bcrypt('test', ['rounds' => 12]));
    }

    public function codes($howMany = 1)
    {
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

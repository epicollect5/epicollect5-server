<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use Illuminate\Http\Request;
use ec5\Repositories\QueryBuilder\Project\DeleteRepository;
use ec5\Repositories\QueryBuilder\Stats\Entry\StatsRepository;
use Illuminate\Support\Facades\DB;

class ProjectDeleteEntriesController extends ProjectControllerBase
{

    protected $errors = [];

    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function show()
    {

        if (!$this->requestedProjectRole->canDeleteEntries()) {
            $errors = ['ec5_91'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        $vars = $this->defaultProjectDetailsParams('', '', true);

        return view('project.project_delete_entries', $vars);
    }

    /**
     * @param Request $request
     * @param DeleteRepository $deleteRepository
     * @param StatsRepository $statsRepository
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function delete(
        Request $request,
        DeleteRepository $deleteRepository,
        StatsRepository $statsRepository
    ) {

        $projectName = $request->input('project-name');

        //no project name passed?
        if (!isset($projectName)) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        //if we are sending the wrong project name bail out
        if (trim($this->requestedProject->name) !== $projectName) {
            return redirect('myprojects/' . $this->requestedProject->slug . '/manage-entries')
                ->withErrors(['errors' => ['ec5_91']]);
        }

        //do we have the right permissions?
        if (!$this->requestedProjectRole->canDeleteEntries()) {
            return redirect('myprojects/' . $this->requestedProject->slug . '/manage-entries')
                ->withErrors(['errors' => ['ec5_91']]);
        }

        /* DELETING */

        // Attempt to delete the data
        $deleteRepository->deleteEntries($this->requestedProject->getId());

        // If the delete fails, error out
        if ($deleteRepository->hasErrors()) {
            return redirect('myprojects/' . $this->requestedProject->slug . '/manage-entries')->withErrors($deleteRepository->errors());
        }

        // Attempt to delete all project media
        $deleteRepository->deleteEntriesMedia($this->requestedProject->ref);

        // If the delete media fails, inform user
        //Project has already been deleted by this point
        if ($deleteRepository->hasErrors()) {
            return redirect('myprojects/' . $this->requestedProject->slug . '/manage-entries')->withErrors($deleteRepository->errors());
        }

        // Update the entry stats
        if (!$statsRepository->updateProjectEntryStats($this->requestedProject)) {
            $this->errors['entries_deletion'] = ['ec5_94'];
            return redirect('myprojects/' . $this->requestedProject->slug . '/manage-entries')->withErrors($this->errors);
        }

        // Succeeded
        return redirect('myprojects/' . $this->requestedProject->slug . '/manage-entries')->with('message', 'ec5_122');
    }

    public function softDelete(
        Request $request
    ) {

        $projectName = $request->input('project-name');

        //no project name passed?
        if (!isset($projectName)) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        //if we are sending the wrong project name bail out
        if (trim($this->requestedProject->name) !== $projectName) {
            return redirect('myprojects/' . $this->requestedProject->slug . '/manage-entries')
                ->withErrors(['errors' => ['ec5_91']]);
        }

        //do we have the right permissions?
        if (!$this->requestedProjectRole->canDeleteEntries()) {
            return redirect('myprojects/' . $this->requestedProject->slug . '/manage-entries')
                ->withErrors(['errors' => ['ec5_91']]);
        }
        DB::beginTransaction();
        try {
            if (!$this->archiveEntries($this->requestedProject->getId())) {
                DB::rollBack();
                return redirect('myprojects/' . $this->requestedProject->slug . '/manage-entries')->withErrors(['ec5_104']);
            } else {
                //update stats
                $statsRepository = new StatsRepository();
                //update entry stats
                $statsRepository->updateEntryStats($this->requestedProject);
                //update branch entry stats
                $statsRepository->updateBranchEntryStats($this->requestedProject);
            }
            // Success!
            DB::commit();
            return redirect('myprojects/' . $this->requestedProject->slug . '/manage-entries')->with('message', 'ec5_122');
        } catch (\Exception $e) {
            \Log::error('Error softDelete() entries', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return redirect('myprojects/' . $this->requestedProject->slug . '/manage-entries')->withErrors(['ec5_104']);
        }
    }
}

<?php

namespace ec5\Models\ProjectRoles;

use ec5\Models\Eloquent\User;

class ProjectRole
{

    /**
     * @var User
     */
    protected $user;

    /**
     * @var
     */
    protected $role;

    /**
     * @var
     */
    protected $projectId;

    /**
     * @param $user
     * @param $projectId
     * @param $role
     */
    public function setRole(User $user = null, $projectId, $role)
    {
        $this->user = $user;
        $this->projectId = $projectId;
        $this->role = $role;
    }

    /**
     * Check if a role exists
     *
     * @return bool
     */
    public function hasRole()
    {
        return !empty($this->user) && !empty($this->projectId) && !empty($this->role);
    }

    /**
     * Get the role
     *
     * @return mixed
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * @return bool
     */
    public function isCreator()
    {
        if ($this->role == 'creator') {
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isManager()
    {
        if ($this->role == 'manager' || $this->isCreator()) {
            return true;
        }

        return false;

    }

    /**
     * @return bool
     */
    public function isCurator()
    {

        if ($this->role == 'curator') {
            return true;
        }

        return false;

    }

    /**
     * @return bool
     */
    public function isCollector()
    {

        if ($this->role == 'collector') {
            return true;
        }

        return false;

    }

    public function isViewer()
    {
        return ($this->role === 'viewer');
    }

    /**
     * @return bool
     */
    public function canEditData()
    {
        if ($this->isManager() || $this->isCurator()) {
            return true;
        }

        return false;
    }

    /**
     * Only creator and manager can delete all the entries at once
     * @return bool
     */
    public function canDeleteEntries()
    {
        return $this->isManager() || $this->isCreator();

    }

    /**
     * @param $entry
     * @return bool
     */
    public function canDeleteEntry($entry)
    {
        if ($this->isManager() || $this->isCurator() ||
            ($this->user && $this->user->id == $entry->user_id)) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function canUpload()
    {
        if ($this->isManager() || $this->isCurator() || $this->isCollector()) {
            return true;
        }

        return false;
    }

    public function canBulkUpload()
    {
        if ($this->isCreator() || $this->isManager() || $this->isCurator() || $this->isCollector()) {
            return true;
        }

        return false;
    }


    /**
     * @return bool
     */
    public function canEditProject()
    {
        return ($this->isManager());
    }

    /**
     * @return bool
     */
    public function canDeleteProject(): bool
    {
        return $this->isCreator();
    }

    /**
     * @return bool
     */
    public function canViewProject()
    {
        if ($this->isManager() || $this->isCurator() || $this->isCollector() || $this->isViewer()) {
            return true;
        }

        return false;
    }

    //creator cannot leave a project
    public function canLeaveProject()
    {
        return (!$this->isCreator());
    }

    /**
     * @return bool
     */
    public function canAddUsers()
    {
        return $this->isManager();
    }

    /**
     * @return bool
     */
    public function canRemoveUsers()
    {
        return $this->isManager();
    }

    /**
     * @return bool
     */
    public function canSwitchUserRole()
    {
        return $this->isManager();
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return int
     */
    public function getProjectId()
    {
        return $this->projectId;
    }

}

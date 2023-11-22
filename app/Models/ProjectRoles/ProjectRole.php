<?php

namespace ec5\Models\ProjectRoles;

class ProjectRole
{
    protected $user;
    protected $role;
    protected $projectId;

    public function setRole($user, $projectId, $role)
    {
        $this->user = $user ?? null;
        $this->projectId = $projectId;
        $this->role = $role;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function hasRole(): bool
    {
        return !empty($this->user) && !empty($this->projectId) && !empty($this->role);
    }


    public function isCreator(): bool
    {
        return $this->role === 'creator';
    }

    public function isManager(): bool
    {
        if ($this->role == 'manager' || $this->isCreator()) {
            return true;
        }

        return false;

    }

    public function isCurator(): bool
    {
        return $this->role === 'curator';
    }

    public function isCollector(): bool
    {
        return $this->role === 'collector';
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    public function canEditData(): bool
    {
        return ($this->isManager() || $this->isCurator());
    }

    /**
     * Only creator and manager can delete all the entries at once
     * @uses canDeleteEntries
     */
    public function canDeleteEntries(): bool
    {
        return $this->isManager() || $this->isCreator();
    }

    public function canDeleteEntry($entry): bool
    {
        if ($this->isManager() || $this->isCurator() ||
            ($this->user && $this->user->id == $entry->user_id)) {
            return true;
        }
        return false;
    }

    public function canUpload(): bool
    {
        if ($this->isManager() || $this->isCurator() || $this->isCollector()) {
            return true;
        }
        return false;
    }

    public function canBulkUpload(): bool
    {
        if ($this->isCreator() || $this->isManager() || $this->isCurator() || $this->isCollector()) {
            return true;
        }
        return false;
    }

    public function canEditProject(): bool
    {
        return $this->isManager();
    }

    public function canDeleteProject(): bool
    {
        return $this->isCreator();
    }

    public function canViewProject(): bool
    {
        if ($this->isManager() || $this->isCurator() || $this->isCollector() || $this->isViewer()) {
            return true;
        }
        return false;
    }

    //creator cannot leave a project
    public function canLeaveProject(): bool
    {
        return (!$this->isCreator());
    }

    public function canAddUsers(): bool
    {
        return $this->isManager();
    }

    public function canRemoveUsers(): bool
    {
        return $this->isManager();
    }

    public function canSwitchUserRole(): bool
    {
        return $this->isManager();
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getProjectId(): int
    {
        return $this->projectId;
    }
}

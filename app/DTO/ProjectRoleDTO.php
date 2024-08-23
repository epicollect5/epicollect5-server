<?php

namespace ec5\DTO;

use ec5\Models\User\User;

class ProjectRoleDTO
{
    protected User|null $user;
    protected string|null $role;
    protected int $projectId;

    public function setRole($user, $projectId, $role): void
    {
        $this->user = $user ?? null;
        $this->projectId = $projectId;
        $this->role = $role;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function hasRole(): bool
    {
        return !empty($this->user) && !empty($this->projectId) && !empty($this->role);
    }


    public function isCreator(): bool
    {
        return $this->role === config('epicollect.strings.project_roles.creator');
    }

    public function isManager(): bool
    {
        return $this->role === config('epicollect.strings.project_roles.manager');
    }

    public function isCurator(): bool
    {
        return $this->role === config('epicollect.strings.project_roles.curator');
    }

    public function isCollector(): bool
    {
        return $this->role === config('epicollect.strings.project_roles.collector');
    }

    public function isViewer(): bool
    {
        return $this->role === config('epicollect.strings.project_roles.viewer');
    }

    public function canEditData(): bool
    {
        return $this->isCreator() || $this->isManager() || $this->isCurator();
    }

    /**
     * Only creator and manager can delete all the entries at once
     */
    public function canDeleteEntries(): bool
    {
        return $this->isCreator() || $this->isManager();
    }

    public function canDeleteEntry($entry): bool
    {
        if ($this->isCreator() || $this->isManager() || $this->isCurator() ||
            ($this->user && $this->user->id == $entry->user_id)) {
            return true;
        }
        return false;
    }

    public function canUpload(): bool
    {
        if ($this->isCreator() || $this->isManager() || $this->isCurator() || $this->isCollector()) {
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
        return $this->isCreator() || $this->isManager();
    }

    public function canDeleteProject(): bool
    {
        return $this->isCreator();
    }

    //creator(s) cannot leave a project
    public function canLeaveProject(): bool
    {
        return (!$this->isCreator());
    }

    public function canManageUsers(): bool
    {
        return $this->isCreator() || $this->isManager();
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}

<?php

namespace ec5\Traits\Requests;

use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectRoleDTO;
use ec5\Models\User\User;

trait RequestAttributes
{
    protected function requestedUser(): User
    {
        /**
         * @var User $user
         */
        $user = request()->attributes->get('requestedUser');
        return $user;
    }

    protected function requestedProject(): ProjectDTO
    {
        /**
         * @var ProjectDTO $requestedProject
         */
        $requestedProject = request()->attributes->get('requestedProject');
        return $requestedProject;
    }

    protected function requestedProjectRole(): ProjectRoleDTO
    {
        /**
         * @var ProjectRoleDTO $projectRole
         */
        $projectRole = request()->attributes->get('requestedProjectRole');
        return $projectRole;
    }
}
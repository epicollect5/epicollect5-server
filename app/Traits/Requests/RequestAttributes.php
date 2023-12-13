<?php

namespace ec5\Traits\Requests;

trait RequestAttributes
{
    protected function requestedUser()
    {
        return request()->attributes->get('requestedUser');
    }

    protected function requestedProject()
    {
        return request()->attributes->get('requestedProject');
    }

    protected function requestedProjectRole()
    {
        return request()->attributes->get('requestedProjectRole');
    }
}
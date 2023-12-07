<?php

namespace ec5\Http\Middleware;

class ProjectPermissionsBulkUpload extends ProjectPermissionsBase
{
    //Check if the user can bulk upload to this project
    public function hasPermission(): bool
    {
        $canBulkUpload = $this->requestedProject->canBulkUpload();

        switch ($canBulkUpload) {
            case config('epicollect.strings.can_bulk_upload.nobody'):
                //bulk upload is disabled for all users
                $this->error = 'ec5_360';
                return false;
                break;
            case config('epicollect.strings.can_bulk_upload.members'):
                //check the user role for the current project
                if (!$this->requestedProjectRole->canBulkUpload()) {
                    $this->error = 'ec5_363';
                    return false;
                }
                break;
            case config('epicollect.strings.can_bulk_upload.everybody'):
                //do nothing so every user will go through
                break;
            default:
                //do nothing, it never gets here
        }
        return true;
    }
}


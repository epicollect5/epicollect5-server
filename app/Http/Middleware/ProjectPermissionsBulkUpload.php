<?php

namespace ec5\Http\Middleware;

use Config;

class ProjectPermissionsBulkUpload extends ProjectPermissionsBase
{
    /**
     * @return bool
     *
     * Check if the user can bulk upload to this project
     */
    public function hasPermission()
    {
        $NOBODY = Config::get('ec5Strings.can_bulk_upload.NOBODY');
        $MEMBERS = Config::get('ec5Strings.can_bulk_upload.MEMBERS');
        $EVERYBODY = Config::get('ec5Strings.can_bulk_upload.EVERYBODY');

        $canBulkUpload = $this->requestedProject->canBulkUpload();

        switch ($canBulkUpload) {
            case $NOBODY:
                //bulk upload is disabled for all users
                $this->error = 'ec5_360';
                return false;
                break;
            case $MEMBERS:
                //check user role for the current project
                if(!$this->requestedProjectRole->canBulkUpload()) {
                    $this->error = 'ec5_363';
                    return false;
                }
                break;
            case $EVERYBODY:
                //do nothing so every user will go through
                break;
            default:
                //do nothing, it never gets here
        }

        return true;
    }
}


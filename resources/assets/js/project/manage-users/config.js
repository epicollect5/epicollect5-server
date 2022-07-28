'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function config(module) {

    module.config = {
        messages: {
            error: {
                CSV_FILE_INVALID: 'CSV file is invalid',
                INVALID_EMAILS: 'Invalid emails',
                BROWSER_NOT_SUPPORTED: 'Browser not supported'

            },
            success: {
                USERS_IMPORTED: 'Users added to the project'
            },
            warning: {
                SOME_USERS_NOT_IMPORTED: 'Some users could not be imported'
            }
        },
        consts: {
            CSV_FILE_EXTENSION: 'csv',
            ANIMATION_FAST: 200,
            ANIMATION_NORMAL: 500,
            ROLES: {
                MANAGER: 'manager',
                CURATOR: 'curator',
                COLLECTOR: 'collector',
                VIEWER: 'viewer'
            }
        },
        errorCodes: {
            userDoesntExist: 'ec5_90',
            importerDoesNotHavePermission: 'ec5_91',
            invalidValue: 'ec5_29', //if role or provider are invalid
            invalidEmailAddress: 'ec5_42',
            creatorEmailAddress: 'ec5_217',
            managerEmailAddress: 'ec5_344'
        },
        invalidEmailAddresses: []
    }

}(window.EC5.project_users));

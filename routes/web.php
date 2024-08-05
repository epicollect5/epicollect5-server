<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here we specify 'web' route middleware for all web requests.
|
*/
Route::get('/', 'Web\HomeController@index')->name('home');
//Tell me more routes
Route::get('more-create', 'Web\MorePages\MoreCreateController@index');
Route::get('more-collect', 'Web\MorePages\MoreCollectController@index');
Route::get('more-view', 'Web\MorePages\MoreViewController@index');

// Authentication routes
Route::group(['middleware' => ['guest']], function () {
    Route::get('login', 'Web\Auth\AuthController@show')->name('login');
    Route::post('login', 'Web\Auth\LocalController@authenticate');
    //  Route::post('login/ldap', 'Web\Auth\LdapController@authenticate');
    Route::get('login/admin', 'Web\Auth\AdminController@show')->name('login-admin');
    Route::post('login/admin', 'Web\Auth\AdminController@authenticate');

    Route::get('login/staff', 'Web\Auth\LocalController@show')->name('login-staff');
    Route::post('login/staff', 'Web\Auth\LocalController@authenticate');

    // Google Authentication routes
    //Route::get('handle/connect-google', 'Web\Auth\ProfileController@handle');
    Route::get('redirect/google', 'Web\Auth\GoogleController@redirect');
    Route::get('handle/google', 'Web\Auth\GoogleController@handleCallback');
    //Apple auth
    Route::post('handle/apple', 'Web\Auth\AppleController@handleAppleCallback');

    //routes to enter 6-digit code when account email clashes
    Route::get('login/verification/code', 'Web\Auth\VerificationCodeController@show')->name('verification-code');
    Route::post('login/verification/google', 'Web\Auth\GoogleController@verify')->name('verification-google');
    Route::post('login/verification/apple', 'Web\Auth\AppleController@verify')->name('verification-apple');
});


//Passwordless routes, 5 requests max every 30 minutes
$passwordlessMiddleware = App::isLocal() ? ['guest'] : ['guest', 'throttle:5,30'];
Route::group(['middleware' => $passwordlessMiddleware], function () use ($passwordlessMiddleware) {
    Route::post('login/passwordless/token', 'Web\Auth\PasswordlessController@sendCode')
        ->name('passwordless-token-web');

    Route::post('login/passwordless/verification', 'Web\Auth\PasswordlessController@authenticateWithCode')
        ->name('passwordless-auth-web');
});

// Admin area and functions
Route::group(['middleware' => 'auth.admin'], function () {
    // Administration
    Route::get('admin/projects', 'Web\Admin\AdminController@showProjects')->name('admin-projects');
    Route::get('admin/stats', 'Web\Admin\AdminController@showStats')->name('admin-stats');
    Route::get('admin/users', 'Web\Admin\AdminController@showUsers')->name('admin-users');

    Route::post('admin/update-user-state', 'Web\Admin\AdminUsersController@updateUserState');
    Route::post('admin/add-user', 'Web\Admin\AdminUsersController@addUser');

    Route::post('admin/update-user-project-role', 'Web\Admin\AdminUserRolesController@update');

    Route::get('admin/tools/resize-entry-images', 'Web\Admin\Tools\ImageToolsController@resizeEntryImages');
    Route::get('admin/tools/create-entry-extra-images', 'Web\Admin\Tools\ImageToolsController@createEntryExtraImages');

    Route::get('admin/tools/api/entries', 'Web\Admin\Tools\ApiPrivateEntriesController@getEntries');
    Route::get('admin/tools/api/media', 'Web\Admin\Tools\ApiPrivateEntriesController@getMedia');

    Route::get('admin/tools/phpinfo', 'Web\Admin\Tools\PHPToolsController@showPHPInfo');
    Route::get('admin/tools/opcache', 'Web\Admin\Tools\PHPToolsController@resetOpcache');
    Route::get('admin/tools/projects-stats', 'Web\Admin\Tools\PHPToolsController@showProjectsStats');

    Route::get('admin/tools/storage', 'Web\Admin\Tools\SearchToolsController@findProjectsStorageUsedDefault');
    Route::get('admin/tools/storage-table', 'Web\Admin\Tools\SearchToolsController@findProjectsStorageUsedTableDefault');
    Route::get('admin/tools/storage-table/{year}', 'Web\Admin\Tools\SearchToolsController@findProjectsStorageUsedTable');


    Route::get('admin/tools/storage/{threshold}', 'Web\Admin\Tools\SearchToolsController@findProjectsStorageUsed');


    Route::get('admin/tools/project-structures/1', 'Web\Admin\Tools\SearchToolsController@findQuestionsWithTooManyJumps');

    Route::get('admin/tools/project-structures/2', 'Web\Admin\Tools\SearchToolsController@findProjectsWithJumps');

    Route::get('admin/tools/project-structures/3', 'Web\Admin\Tools\SearchToolsController@findprojectsWithALotOfQuestions');

    Route::get('admin/tools/project-structures/4', 'Web\Admin\Tools\SearchToolsController@findprojectsWithTimeUniqueness');

    Route::get('admin/tools/count-media/{days?}', 'Web\Admin\Tools\SearchToolsController@countMedia');

    Route::get('admin/tools/avatar/{ref?}', 'Web\Admin\Tools\PHPToolsController@createProjectAvatar');

    Route::get('admin/tools/db/get-entries', 'Web\Admin\Tools\DBToolsController@getEntries');

    Route::get('admin/tools/db/copy-entries', 'Web\Admin\Tools\DBToolsController@copyEntries');

    Route::get('admin/tools/db/db-size', 'Web\Admin\Tools\DBToolsController@getDBSize');

    Route::get('admin/tools/db/users-today', 'Web\Admin\Tools\DBToolsController@getUsersToday');

    Route::get('admin/tools/csv', 'Web\Admin\Tools\PHPToolsController@testCSV');

    Route::get('admin/tools/hash-password', 'Web\Admin\Tools\AuthToolsController@show');
    Route::post('admin/tools/hash-password', 'Web\Admin\Tools\AuthToolsController@hash');

    Route::get('admin/tools/send-superadmin-email', 'Web\Admin\Tools\PHPToolsController@sendSuperAdminEmail');

    Route::get('admin/tools/send-system-email', 'Web\Admin\Tools\PHPToolsController@sendSystemEmail');


    Route::get('admin/tools/email-preview', 'Web\Admin\Tools\PHPToolsController@previewEmail');

    Route::get('admin/tools/codes/{howMany?}', 'Web\Admin\Tools\PHPToolsController@codes');

    Route::get('admin/tools/count-media/{days?}', 'Web\Admin\Tools\SearchToolsController@countMedia');

    Route::get('admin/tools/apple', 'Web\Admin\Tools\PHPToolsController@decodeAppleToken');

    Route::get('admin/tools/carbon', 'Web\Admin\Tools\PHPToolsController@carbon');
});

// Projects & categories
Route::get('projects/search', 'Web\Projects\SearchProjectsController@show')->name('projects-search');
Route::get('projects/{category?}', 'Web\Projects\ListedProjectsController@show');


// My Project routes
// Set middleware to auth
Route::group(['middleware' => 'auth'], function () {

    Route::get('profile', 'Web\Auth\ProfileController@show')->name('profile');
    // Logout route
    Route::get('logout', 'Web\Auth\AuthController@logout')->name('logout');

    Route::get('profile/connect-google', 'Web\Auth\ProfileController@connectGoogle')->name('profile-connect-google');

    Route::post('profile/disconnect-google', 'Web\Auth\ProfileController@disconnectGoogle')->name('profile-disconnect-google');


    Route::get('profile/connect-google-callback', 'Web\Auth\ProfileController@handleGoogleConnectCallback')
        ->name('profile-connect-google-callback');

    Route::post('profile/connect-apple-callback', 'Web\Auth\ProfileController@handleAppleConnectCallback')->name('profile-connect-apple-callback');

    Route::post('profile/disconnect-apple', 'Web\Auth\ProfileController@disconnectApple')->name('profile-disconnect-apple');

    Route::get('login/staff/reset', 'Web\Auth\ResetPasswordController@show')->name('password-reset');
    Route::post('login/staff/reset', 'Web\Auth\ResetPasswordController@reset')->name('password-reset-post');

    Route::get('myprojects', 'Web\Projects\MyProjectsController@show')->name('my-projects');
    Route::get('myprojects/create', 'Web\Project\ProjectCreateController@show')->name('my-projects-create');
    Route::post('myprojects/create', 'Web\Project\ProjectCreateController@create');
    Route::post('myprojects/import', 'Web\Project\ProjectImportController@import');

    // Set middleware to project.permissions.required.role
    Route::group(['middleware' => 'project.permissions.required.role'], function () {

        Route::get('myprojects/{project_slug}', 'Web\Project\ProjectController@details');

        Route::get('myprojects/{project_slug}/formbuilder', 'Web\Project\ProjectController@formbuilder')->name('formbuilder');

        Route::post('myprojects/{project_slug}/settings/{action?}', 'Web\Project\ProjectEditController@settings');

        Route::post('myprojects/{project_slug}/details', 'Web\Project\ProjectEditController@details');

        Route::get('myprojects/{project_slug}/download-project-definition', 'Web\Project\ProjectController@downloadProjectDefinition');

        // Cloning
        Route::get('myprojects/{project_slug}/clone', 'Web\Project\ProjectCloneController@show');
        Route::post('myprojects/{project_slug}/clone', 'Web\Project\ProjectCloneController@store');

        // Manage Entries
        Route::get('myprojects/{project_slug}/manage-entries', 'Web\Project\ProjectEntriesController@show');
        Route::post('myprojects/{project_slug}/manage-entries', 'Web\Project\ProjectEntriesController@store');

        // Mappings
        Route::get('myprojects/{project_slug}/mapping-data', 'Web\Project\ProjectMappingController@show');
        Route::post('myprojects/{project_slug}/mapping-data', 'Web\Project\ProjectMappingController@store');
        Route::post('myprojects/{project_slug}/mapping-data/update', 'Web\Project\ProjectMappingController@update');
        Route::post('myprojects/{project_slug}/mapping-data/delete', 'Web\Project\ProjectMappingController@delete');

        //Datasets
        //        Route::get('myprojects/{project_slug}/datasets', 'Web\Project\ProjectDatasetsController@show');
        //
        //        Route::post('myprojects/{project_slug}/datasets', 'Web\Project\ProjectDatasetsController@store');
        //
        //        Route::post('myprojects/{project_slug}/datasets/delete', 'Web\Project\ProjectDatasetsController@destroy');
        //
        //        Route::post('myprojects/{project_slug}/datasets/rename', 'Web\Project\ProjectDatasetsController@update');
        //
        //        Route::post('myprojects/{project_slug}/datasets/replace', 'Web\Project\ProjectDatasetsController@replace');
        //
        //        Route::post('myprojects/{project_slug}/datasets/download', 'Web\Project\ProjectDatasetsController@download');

        // Project user management
        Route::get('myprojects/{project_slug}/manage-users', 'Web\Project\ProjectManageUsersController@index')->name('manage-users');
        Route::post('myprojects/{project_slug}/add-{role}', 'Web\Project\ProjectManageUsersController@addUserRole');
        Route::post('myprojects/{project_slug}/remove-role', 'Web\Project\ProjectManageUsersController@removeUserRole');

        // Project deletion
        Route::get('myprojects/{project_slug}/delete', 'Web\Project\ProjectDeleteController@show');
        Route::post('myprojects/{project_slug}/delete', 'Web\Project\ProjectDeleteController@delete');

        //Leaving a project
        Route::get('myprojects/{project_slug}/leave', 'Web\Project\ProjectLeaveController@show');
        Route::post('myprojects/{project_slug}/leave', 'Web\Project\ProjectLeaveController@leave');

        // Project entries deletion
        Route::get('myprojects/{project_slug}/delete-entries', 'Web\Project\ProjectDeleteEntriesController@show');

        //Project Transfer Ownership
        Route::get('myprojects/{project_slug}/transfer-ownership', 'Web\Project\ProjectTransferOwnershipController@show')->name('transfer-ownership');
        Route::post('myprojects/{project_slug}/transfer-ownership', 'Web\Project\ProjectTransferOwnershipController@transfer');

        // Api
        Route::get('myprojects/{project_slug}/api', 'Web\Project\ProjectApiController@show');
        // Apps
        Route::get('myprojects/{project_slug}/apps', 'Web\Project\ProjectAppsController@show');
        Route::post('myprojects/{project_slug}/apps', 'Web\Project\ProjectAppsController@store');
        Route::post('myprojects/{project_slug}/app-delete', 'Web\Project\ProjectAppsController@delete');
        Route::post('myprojects/{project_slug}/app-revoke', 'Web\Project\ProjectAppsController@revoke');
    });

    // Users must authenticate in order to add/edit entries, even for public projects
    //Also the viewer role cannot add/edit entries
    Route::group(['middleware' => ['project.permissions', 'project.permissions.viewer.role']], function () {
        // Data editor Add/Edit
        Route::get('project/{project_slug}/add-entry', 'Web\Project\ProjectDataController@add')->name('data-editor-add');
        Route::get('project/{project_slug}/edit-entry', 'Web\Project\ProjectDataController@edit')->name('data-editor-edit');
    });
});
// END My Project routes

// Project home (data-viewer)
Route::group(['middleware' => 'project.permissions'], function () {
    Route::get('project/{project_slug?}', 'Web\Project\ProjectController@show')
        ->name('project-home');
    Route::get('project/{project_slug?}/data', 'Web\Project\ProjectController@dataviewer')
        ->name('dataviewer');

});

// Deeplink (open the project in the app if installed, otherwise redirect to the project home page)
//always public, but the app will ask to log in when private.
//Same goes for "Open in Browser"
Route::group(['middleware' => 'project.permissions.open'], function () {
    Route::get('open/project/{project_slug?}', 'Web\Project\ProjectController@open')
        ->name('project-open');
});





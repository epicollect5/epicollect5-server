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

    //routes to enter 6 digit code when account email clashes
    Route::get('login/verification/code', 'Web\Auth\VerificationCodeController@show')->name('verification-code');
    Route::post('login/verification/google', 'Web\Auth\GoogleController@verify')->name('verification-google');
    Route::post('login/verification/apple', 'Web\Auth\AppleController@verify')->name('verification-apple');
});

//Registration for local users (show this page only to guest, not users already logged in)
//Route::group(['middleware' => ['guest', 'throttle:5,1']], function () {
//    Route::get('signup', 'Web\Auth\SignUpController@show')->name('signup');
//    Route::post('signup', 'Web\Auth\SignUpController@store')->name('signup-post');
//
//    Route::get('login/forgot', 'Web\Auth\ForgotPasswordController@show')
//        ->name('forgot-show');
//    Route::post('login/forgot', 'Web\Auth\ForgotPasswordController@sendResetEmail')
//        ->name('forgot-post');
////    // Password reset routes...
//    Route::get('login/reset/{token}', 'Web\Auth\ResetPasswordController@show')
//        ->name('login-reset');
//    Route::post('login/reset', 'Web\Auth\ResetPasswordController@reset')
//        ->name('login-reset-post');
//});

//Passwordless routes, 5 requests max every 30 minutes
$throttle = config('APP_ENV') === 'local' ? 'throttle:5,30' : '';
Route::group(['middleware' => ['guest', $throttle]], function () {
    // Route::post('login/passwordless/token', 'Web\Auth\PasswordlessController@sendLink')
    //     ->name('passwordless-token-web');

    Route::post('login/passwordless/token', 'Web\Auth\PasswordlessController@sendCode')
        ->name('passwordless-token-web');

    Route::get('login/passwordless/verification', 'Web\Auth\PasswordlessController@show')
        ->name('passwordless-verification');


    Route::get('login/passwordless/auth/{token}', 'Web\Auth\PasswordlessController@authenticate')
        ->name('passwordless-authenticate-web');
});


//Show this routes only to unverified users. 5 requests per minute throttling
//Route::group(['middleware' => ['unverified', 'throttle:5,1']], function () {
//    Route::get('signup/verification', 'Web\Auth\VerificationController@show')->name('verify');
//    Route::post('signup/verification', 'Web\Auth\VerificationController@verify')->name('verify-post');
//    Route::post('signup/verification/resend', 'Web\Auth\VerificationController@resend')->name('resend');
//});

// Admin area and functions
Route::group(['middleware' => 'auth.admin'], function () {
    // Administration
    Route::get('admin/projects', 'Web\Admin\AdminController@showProjects');

    Route::get('admin/stats', 'Web\Admin\AdminController@showStats');

    Route::get('admin/{action?}', 'Web\Admin\AdminController@index')->name('admin-home');

    Route::post('admin/update-user-server-role', 'Web\Admin\AdminUsersController@updateUserServerRole');
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

    Route::get('admin/tools/storage', 'Web\Admin\Tools\SearchToolsController@findProjectsStorageUsed');


    Route::get('admin/tools/project-structures/1', 'Web\Admin\Tools\SearchToolsController@findQuestionsWithTooManyJumps');

    Route::get('admin/tools/project-structures/2', 'Web\Admin\Tools\SearchToolsController@findProjectsWithJumps');

    Route::get('admin/tools/project-structures/3', 'Web\Admin\Tools\SearchToolsController@findprojectsWithALotOfQuestions');

    Route::get('admin/tools/project-structures/4', 'Web\Admin\Tools\SearchToolsController@findprojectsWithTimeUniqueness');

    Route::get('admin/tools/count-media/{days?}', 'Web\Admin\Tools\SearchToolsController@countMedia');

    Route::get('admin/tools/avatar/{ref?}', 'Web\Admin\Tools\PHPToolsController@createProjectAvatar');

    Route::get('admin/tools/db/get-entries', 'Web\Admin\Tools\DBToolsController@getEntries');

    Route::get('admin/tools/db/copy-entries', 'Web\Admin\Tools\DBToolsController@copyEntries');

    Route::get('admin/tools/db/db-size', 'Web\Admin\Tools\DBToolsController@getDBSize');

    Route::get('admin/tools/csv', 'Web\Admin\Tools\PHPToolsController@testCSV');

    Route::get('admin/tools/hash-password', 'Web\Admin\Tools\AuthToolsController@show');
    Route::post('admin/tools/hash-password', 'Web\Admin\Tools\AuthToolsController@hash');

    Route::get('admin/tools/email', 'Web\Admin\Tools\PHPToolsController@sendEmail');

    Route::get('admin/tools/email-preview', 'Web\Admin\Tools\PHPToolsController@previewEmail');

    Route::get('admin/tools/codes/{howMany?}', 'Web\Admin\Tools\PHPToolsController@codes');

    Route::get('admin/tools/count-media/{days?}', 'Web\Admin\Tools\SearchToolsController@countMedia');

    Route::get('admin/tools/apple', 'Web\Admin\Tools\PHPToolsController@decodeAppleToken');

    Route::get('admin/tools/carbon', 'Web\Admin\Tools\PHPToolsController@carbon');
});

// Auth middleware
Route::group(['middleware' => 'auth'], function () {
    // User search
    Route::get('users/search-by-email', 'Web\Admin\ManageUsersController@searchByEmail');
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
    // Route::get('profile/reset', 'Web\Auth\ProfileController@reset')->name('profile-reset-password');
    Route::get('profile/connect-google', 'Web\Auth\ProfileController@connectGoogle')->name('profile-connect-google');

    Route::post('profile/disconnect-google', 'Web\Auth\ProfileController@disconnectGoogle')->name('profile-disconnect-google');


    Route::get('profile/connect-google-callback', 'Web\Auth\ProfileController@handleGoogleConnectCallback')
        ->name('profile-connect-google-callback');

    Route::post('profile/connect-apple-callback', 'Web\Auth\ProfileController@handleAppleConnectCallback')->name('profile-connect-apple-callback');

    Route::post('profile/disconnect-apple', 'Web\Auth\ProfileController@disconnectApple')->name('profile-disconnect-apple');

    Route::get('login/staff/reset', 'Web\Auth\ResetPasswordController@show')->name('password-reset');
    Route::post('login/staff/reset', 'Web\Auth\ResetPasswordController@reset')->name('password-reset-post');

    Route::get('myprojects', 'Web\Projects\MyProjectsController@show')->name('my-projects');
    Route::get('myprojects/create', 'Web\Project\ProjectCreateController@show');
    Route::post('myprojects/create', 'Web\Project\ProjectCreateController@create');
    Route::post('myprojects/import', 'Web\Project\ProjectCreateController@import');

    // Set middleware to project.permissions.required.role
    Route::group(['middleware' => 'project.permissions.required.role'], function () {

        Route::get('myprojects/{project_slug}', 'Web\Project\ProjectController@details');

        Route::get('myprojects/{project_slug}/formbuilder', 'Web\Project\ProjectController@edit');

        Route::post('myprojects/{project_slug}/settings/{action?}', 'Web\Project\ProjectEditController@settings');

        Route::post('myprojects/{project_slug}/details', 'Web\Project\ProjectEditController@details');

        Route::get('myprojects/{project_slug}/download-structure', 'Web\Project\ProjectController@downloadStructure');

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
        Route::get('myprojects/{project_slug}/manage-users', 'Web\Project\ManageUsersController@index')->name('manage-users');
        Route::post('myprojects/{project_slug}/add-{role}', 'Web\Project\ManageUsersController@addUserRole');
        Route::post('myprojects/{project_slug}/remove-role', 'Web\Project\ManageUsersController@removeUserRole');

        // Project deletion
        Route::get('myprojects/{project_slug}/delete', 'Web\Project\ProjectDeleteController@show');
        Route::post('myprojects/{project_slug}/delete', 'Web\Project\ProjectDeleteController@delete');

        // Project entries deletion
        Route::get('myprojects/{project_slug}/delete-entries', 'Web\Project\ProjectDeleteEntriesController@show');
        Route::post('myprojects/{project_slug}/delete-entries', 'Web\Project\ProjectDeleteEntriesController@delete');

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
    //Also viewer role cannot add/edit entries
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
    Route::get('project/{project_slug?}/data', 'Web\Project\ProjectController@data')
        ->name('dataviewer');
});

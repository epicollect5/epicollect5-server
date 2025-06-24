<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here we specify 'internal api' route middleware for all internal requests.
|
*/


// Internal api routes
Route::group(['middleware' => ['project.permissions']], function () {

    //used by the dataviewer
    Route::get('api/internal/project/{project_slug}', 'Api\Project\ProjectController@show');

    //Datasets
    // Route::get('api/internal/datasets/{project_slug}', 'Api\Project\DatasetController@show');

    //    Route::get('api/internal/datasets/{project_slug}/{uuid}', 'Api\Project\DatasetController@getDatasetContent');


    //    Route::post('api/internal/dataset/{project_slug}/delete', 'Api\Project\DatasetController@destroy');

    // Media, used by the dataviewer
    Route::get('api/internal/media/{project_slug}', 'Api\Project\MediaController@getMedia');

    // Temp Media (used by PWA to get a temp file while adding an entry)
    Route::get('api/internal/temp-media/{project_slug}', 'Api\Project\MediaController@getTempMedia');

    // Entry downloads (whole project)
    Route::get('api/internal/download-entries/{project_slug}', 'Api\Entries\DownloadController@index');

    Route::get('api/internal/download-entries-subset/{project_slug}', 'Api\Entries\DownloadSubsetController@subset');

    // Entry upload template (returns a csv file)
    Route::get('api/internal/upload-template/{project_slug}', 'Api\Entries\DownloadTemplateController@sendTemplateFileCSV');

    // Entry upload header (returns a JSON response)
    Route::get('api/internal/upload-headers/{project_slug}', 'Api\Entries\DownloadTemplateController@sendTemplateResponseJSON');

    // Entries for table (dataviewer)
    Route::get('api/internal/entries/{project_slug}', 'Api\Entries\View\ViewEntriesDataController@show');

    // Entries for map (dataviewer)
    Route::get('api/internal/entries-locations/{project_slug}', 'Api\Entries\View\ViewEntriesLocationsController@show');

    // Web entry uploads, for data editor and bulk upload from dataviewer
    Route::post('api/internal/web-upload/{project_slug}', 'Api\Entries\Upload\UploadWebController@store');

    //wrapping this endpoint to check for bulk upload permissions
    Route::group(['middleware' => ['project.permissions.bulk-upload']], function () {
        Route::post('api/internal/bulk-upload/{project_slug}', 'Api\Entries\Upload\UploadWebController@storeBulk');
    });

    Route::get('api/internal/can-bulk-upload/{project_slug}', 'Api\Entries\View\ViewEntriesLocationsController@show');

    // Web file uploads
    Route::post('api/internal/web-upload-file/{project_slug}', 'Api\Entries\Upload\UploadTempFileController@store');

    // Web answer uniqueness checks
    Route::post('api/internal/unique-answer/{project_slug}', 'Api\Entries\Upload\UploadUniquenessController@index');


    Route::group(['middleware' => ['project.permissions.viewer.role']], function () {
        // Entry deletion (delete a single entry)
        Route::post('api/internal/deletion/entry/{project_slug}', 'Api\Entries\DeleteController@deleteEntry');

        //Delete all entries for a project
        Route::post('api/internal/deletion/entries/{project_slug}', 'Api\Entries\DeleteController@deleteEntries');

        Route::get('api/internal/counters/entries/{project_slug}', 'Api\Project\ProjectController@countersEntries');

    });


    //User management
    //used to export the users to csv
    //todo missing type and id in the response
    Route::get('api/internal/project-users/{project_slug}', 'Api\Project\UsersController@all');

    Route::post('api/internal/project-users/{project_slug}/remove-by-role', 'Api\Project\UsersController@removeByRole');

    Route::post('api/internal/project-users/{project_slug}/switch-role', 'Api\Project\UsersController@switchRole');

    Route::post('api/internal/project-users/{project_slug}/add-users-bulk', 'Api\Project\UsersController@addUsersBulk');
});

// Required roles
Route::group(['middleware' => ['project.permissions.required.role']], function () {

    // Formbuilder expects all users to be authenticated with a project role of at least MANAGER
    Route::get('api/internal/formbuilder/{project_slug}', ['uses' => 'Api\Project\ProjectController@show']);

    Route::post('api/internal/formbuilder/{project_slug}', ['uses' => 'Api\Project\FormBuilderController@store'])->name('formbuilder-store');

    //update can_bulk_upload settings
    Route::post('api/internal/can-bulk-upload/{project_slug}', ['uses' => 'Api\Project\ProjectController@updateCanBulkUpload']);
});


//Following routes required authentication (to be logged in)
Route::group(['middleware' => 'auth'], function () {
    // Check a project name exists
    Route::get('api/internal/exists/{project_slug}', 'Api\Project\ProjectController@exists');

    // Proxies.
    Route::get('api/proxies/opencage/{search}', 'Api\Proxies\OpenCageController@fetchAPI');
});

//This route has a rate limiter to prevent abuse
Route::group(['middleware' => ['auth', 'throttle:account-deletion']], function () {
    //request user account deletion
    Route::post('/api/internal/profile/account-deletion-request', 'Api\Auth\AccountController@handleDeletionRequest')->name('internalAccountDelete');
});

Route::group(['middleware' => 'auth.admin'], function () {
    Route::get('api/internal/admin/entries-stats', 'Web\Admin\AdminDataController@getEntriesStats');
    Route::get('api/internal/admin/projects-stats', 'Web\Admin\AdminDataController@getProjectsStats');
    Route::get('api/internal/admin/users-stats', 'Web\Admin\AdminDataController@getUsersStats');
    Route::post('api/internal/admin/settings', 'Web\Admin\AdminController@updateSettings')->name('admin-settings-update');
});

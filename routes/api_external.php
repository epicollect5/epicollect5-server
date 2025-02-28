<?php

/*
|--------------------------------------------------------------------------
| External Application Routes
|--------------------------------------------------------------------------
|
| Here we specify 'external api' route middleware for all external api requests.
|
*/

//request a code to be sent by email (mobile app)
Route::group(['middleware' => ['throttle:passwordless']], function () {
    Route::post('api/login/passwordless/code', 'Api\Auth\PasswordlessController@sendCode');
});

//validate a login code (mobile app)
//important: this route uses a hack in the login() method of JwtGuard
//to have a shorter jwt expiry time. (instead of building a custom guard for 1 endpoint)
//************************************************
//do not change its name otherwise it will break!
Route::post('api/login/passwordless', 'Api\Auth\PasswordlessController@login')
    ->name('passwordless-auth');
Route::post('api/login/verify-google', 'Api\Auth\GoogleController@verifyUserEmail')
    ->name('verify-google');
Route::post('api/login/verify-apple', 'Api\Auth\AppleController@verifyUserEmail')
    ->name('verify-apple');


Route::group(['middleware' => ['throttle:600,1']], function () {
    // Authentication routes
    Route::get('api/login', 'Api\Auth\AuthController@getLogin');
    Route::post('api/login/local', 'Api\Auth\LocalController@authenticate');
    //  Route::post('api/login/ldap', 'Api\Auth\AuthController@postLdapLogin');
    Route::post('api/handle/google', 'Api\Auth\GoogleController@authGoogleUser');

    Route::post('api/handle/apple', 'Api\Auth\AppleController@authUser');

    /* LEGACY END POINTS */
    Route::get('api/json/login', 'Api\Auth\AuthController@getLogin');
    //  Route::post('api/json/login/ldap', 'Api\Auth\AuthController@postLdapLogin');
    Route::post('api/json/handle/google', 'Api\Auth\AuthController@authGoogleUser');
    /* LEGACY END POINTS */


    // Set project permissions middleware
    Route::group(['middleware' => ['project.permissions', 'project.permissions.viewer.role']], function () {

        // Project (this is used by the mobile apps to download a project)
        Route::get('api/project/{project_slug}', 'Api\Project\ProjectController@show');

        //Datasets (used by mobile app to download datasets zip archive)
        //   Route::get('api/datasets/{project_slug}', 'Api\Project\DatasetController@download');

        // Entry uploads
        Route::post('api/upload/{project_slug}', 'Api\Entries\Upload\UploadAppController@postUpload');

        //route for debugging, works only on localhost
        Route::post('api/bulk-upload/{project_slug}', 'Api\Entries\Upload\UploadAppController@postUploadBulk');

        //Media Controller for access to media files (even via the browser, this is why we are not using the internal endpoint)
        Route::get('api/media/{project_slug}/', 'Api\Project\MediaController@getMedia');

        // Temp Media (used by PWA debug to get a temp file)
        Route::get('api/temp-media/{project_slug}/', 'Api\Project\MediaController@getTempMedia');

        /* LEGACY END POINTS */
        // Project
        Route::get('api/json/project/{project_slug}', 'Api\Project\ProjectController@show');

        // Entry uploads
        Route::post(
            'api/json/upload/{project_slug}',
            'Api\Entries\Upload\UploadAppController@postUpload'
        );

        // Media
        Route::get('api/json/media/{project_slug}', 'Api\Project\MediaController@getMedia');
        /* LEGACY END POINTS */


        // Entries for mobile app download
        Route::get('api/entries/{project_slug}', 'Api\Entries\View\ViewEntriesDataController@show');

        // Entry viewing for map
        Route::get(
            'api/entries-locations/{project_slug}',
            'Api\Entries\View\ViewEntriesLocationsController@show'
        );

        // Web file uploads
        Route::post(
            'api/web-upload-file/{project_slug}',
            'Api\Entries\Upload\UploadTempFileController@store'
        );

        // Web answer uniqueness checks
        Route::post(
            'api/unique-answer/{project_slug}',
            'Api\Entries\Upload\UploadUniquenessController@index'
        );
    });

    // Projects searching (by name, will use StartsWith so more results are possible)
    // Use ?exact=true query string for exact match
    Route::get('api/projects/{name?}', 'Api\Project\ProjectController@search');


    // Project version
    Route::get('api/project-version/{project_slug}', 'Api\Project\ProjectController@version');

    /* LEGACY END POINTS */
    // Project searching
    Route::get('api/json/projects/{name?}', 'Api\Project\ProjectController@search');

    // Project version
    Route::get('api/json/project-version/{project_slug}', 'Api\Project\ProjectController@version');
    /* LEGACY END POINTS */
});

// Throttle documented entries READ endpoints - 60 requests per minute
$apiEntriesRateLimit = config('epicollect.setup.api.rate_limit_per_minute.entries');
Route::group(['middleware' => ['throttle:'.$apiEntriesRateLimit]], function () {

    /* Routes used specifically for OAuth 2 client requests */
    // Issue client access_token
    Route::post('api/oauth/token', 'Api\OAuth\OAuthController@issueToken');

    /* Export endpoints */
    // Set project permissions api middleware
    Route::group(['middleware' => ['project.permissions.api']], function () {
        // Export Entries
        Route::get('api/export/entries/{project_slug}', 'Api\Entries\View\ViewEntriesDataController@export');
    });
});

// Throttle documented project READ endpoints - 60 requests per minute
$apiProjectRateLimit = config('epicollect.setup.api.rate_limit_per_minute.project');
Route::group(['middleware' => ['throttle:'.$apiProjectRateLimit]], function () {
    // Set project permissions api middleware
    Route::group(['middleware' => ['project.permissions.api']], function () {
        // Export Project
        Route::get('api/export/project/{project_slug}', 'Api\Project\ProjectController@export');
    });
});


//Trying a lower limit for export entry media as it was causing cpu spikes.
$apiMediaRateLimit = config('epicollect.setup.api.rate_limit_per_minute.media');
Route::group(['middleware' => ['throttle:'.$apiMediaRateLimit]], function () {
    Route::group(['middleware' => ['project.permissions.api']], function () {
        // Export Entry Media
        Route::get('api/export/media/{project_slug}', 'Api\Project\MediaController@getMedia');
    });
});

// Throttle WRITE endpoints - 240 requests per minute
// For CGPS use only, this is not documented
Route::group(['middleware' => ['throttle:240,1']], function () {
    /* Import endpoints */
    // Set project permissions api middleware
    Route::group(['middleware' => ['project.permissions.api']], function () {
        //COG-UK uploads (private imports)
        Route::post('api/import/entries/{project_slug}', 'Api\Entries\Upload\UploadAppController@import')->name('private-import');
    });
});

//Following routes required authentication (to be logged in)
//throttle this route in production, so we do not get a lot of deletion requests by the same user
$accountDeletionMiddleware = App::isLocal() ? ['auth'] : ['auth', 'throttle:1,60'];
Route::group(['middleware' => $accountDeletionMiddleware], function () {
    //request user account deletion
    Route::post('/api/profile/account-deletion-request', 'Api\Auth\AccountController@handleDeletionRequest')->name('externalAccountDelete');
});

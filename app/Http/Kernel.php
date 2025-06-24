<?php

namespace ec5\Http;

use ec5\Http\Middleware\AdminAuthenticate;
use ec5\Http\Middleware\Authenticate;
use ec5\Http\Middleware\BasicAuthenticate;
use ec5\Http\Middleware\EncryptCookies;
use ec5\Http\Middleware\IpMiddleware;
use ec5\Http\Middleware\OverrideDiskForTesting;
use ec5\Http\Middleware\PreventRequestsDuringMaintenance;
use ec5\Http\Middleware\ProjectPermissions;
use ec5\Http\Middleware\ProjectPermissionsApi;
use ec5\Http\Middleware\ProjectPermissionsBulkUpload;
use ec5\Http\Middleware\ProjectPermissionsOpen;
use ec5\Http\Middleware\ProjectPermissionsRequiredRole;
use ec5\Http\Middleware\ProjectPermissionsViewerRole;
use ec5\Http\Middleware\RedirectIfAuthenticated;
use ec5\Http\Middleware\SuperAdminAuthenticate;
use ec5\Http\Middleware\Unverified;
use ec5\Http\Middleware\UserVerification;
use ec5\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * This middleware is run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        //imp: without the class below, built in maintenance mode will not work
        PreventRequestsDuringMaintenance::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class
        ],
        'api_internal' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class
        ],
        'api_external' => []
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $middlewareAliases = [
        'auth' => Authenticate::class,
        'unverified' => Unverified::class,
        'guest' => RedirectIfAuthenticated::class,
        'throttle' => ThrottleRequests::class,
        'auth.verification' => UserVerification::class,
        'auth.basic' => BasicAuthenticate::class,
        'auth.admin' => AdminAuthenticate::class,
        'auth.superadmin' => SuperAdminAuthenticate::class,
        'project.permissions' => ProjectPermissions::class,
        'project.permissions.open' => ProjectPermissionsOpen::class,
        'project.permissions.required.role' => ProjectPermissionsRequiredRole::class,
        'project.permissions.viewer.role' => ProjectPermissionsViewerRole::class,
        'project.permissions.api' => ProjectPermissionsApi::class,
        'project.permissions.bulk-upload' => ProjectPermissionsBulkUpload::class,
        'ip.filtering' => IpMiddleware::class,
        'override.disks' => OverrideDiskForTesting::class
    ];
}

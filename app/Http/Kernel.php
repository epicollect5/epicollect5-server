<?php

namespace ec5\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

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
        \ec5\Http\Middleware\PreventRequestsDuringMaintenance::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \ec5\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \ec5\Http\Middleware\VerifyCsrfToken::class
        ],
        'api_internal' => [
            \ec5\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \ec5\Http\Middleware\VerifyCsrfToken::class
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
        'auth' => \ec5\Http\Middleware\Authenticate::class,
        'unverified' => \ec5\Http\Middleware\Unverified::class,
        'guest' => \ec5\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'auth.verification' => \ec5\Http\Middleware\UserVerification::class,
        'auth.basic' => \ec5\Http\Middleware\BasicAuthenticate::class,
        'auth.admin' => \ec5\Http\Middleware\AdminAuthenticate::class,
        'auth.superadmin' => \ec5\Http\Middleware\SuperAdminAuthenticate::class,
        'project.permissions' => \ec5\Http\Middleware\ProjectPermissions::class,
        'project.permissions.open' => \ec5\Http\Middleware\ProjectPermissionsOpen::class,
        'project.permissions.required.role' => \ec5\Http\Middleware\ProjectPermissionsRequiredRole::class,
        'project.permissions.viewer.role' => \ec5\Http\Middleware\ProjectPermissionsViewerRole::class,
        'project.permissions.api' => \ec5\Http\Middleware\ProjectPermissionsApi::class,
        'project.permissions.bulk-upload' => \ec5\Http\Middleware\ProjectPermissionsBulkUpload::class,
        'ip.filtering' => \ec5\Http\Middleware\IpMiddleware::class
    ];
}

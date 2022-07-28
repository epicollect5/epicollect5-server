<?php namespace ec5\Libraries\Ldap;

use Illuminate\Support\ServiceProvider;
use ec5\Libraries\Ldap\Exceptions\MissingConfigurationException;


class LdapServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        //

    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('ldap', function()
        {
            // Create new LDAP connection based on configuration files
            $ldap = new LdapConnection($this->getLdapConfig());

            return new LdapUserProvider($ldap);
        });

    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['ldap'];
    }

    /**
     * @return array
     */
    private function getLdapConfig()
    {
        if( is_array($this->app['config']['ec5Ldap']) ){
            return $this->app['config']['ec5Ldap'];
        }

        return [];
    }

}
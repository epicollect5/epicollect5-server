<?php

namespace ec5\Providers;

use Illuminate\Support\ServiceProvider;
use Monolog;
use Config;

class LogServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Error logging

        // If we have a slack api token and log channel
        if (Config::get('app.slack_log_channel') && Config::get('app.slack_access_token')) {
            // Logger slack integration
            $monolog = \Log::getMonolog();
            $slackHandler = new \Monolog\Handler\SlackHandler(Config::get('app.slack_access_token'), Config::get('app.slack_log_channel'), 'Epicollect5 Logger', true, null, Monolog\Logger::CRITICAL);
            $monolog->pushHandler($slackHandler);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}

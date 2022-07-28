<?php

namespace ec5\Mail\Transports;

class MailServiceProvider extends \Illuminate\Mail\MailServiceProvider
{
    public function registerSwiftTransport()
    {
        if ($this->app['config']['mail.driver'] == 'mailgun') {
            $this->app->singleton('swift.transport', function ($app) {
                return new CustomTransportManager($app);
            });
        } else {
            parent::registerSwiftTransport();
        }
    }

}

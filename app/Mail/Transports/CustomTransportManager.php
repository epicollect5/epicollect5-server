<?php
/**
 * Created by PhpStorm.
 * User: mirko
 * Date: 24/03/2020
 * Time: 17:59
 */

namespace ec5\Mail\Transports;

use Illuminate\Mail\TransportManager;


class CustomTransportManager extends TransportManager
{
    public function createMailgunDriver() {
        $config = $this->app['config']->get('services.mailgun', []);

        return new ExtendedMailgunTransport(
            $this->guzzle($config),
            $config['secret'], $config['domain'], $config['zone']
        );
    }
}

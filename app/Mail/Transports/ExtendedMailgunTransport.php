<?php

namespace ec5\Mail\Transports;

use GuzzleHttp\ClientInterface;
use Illuminate\Mail\Transport\MailgunTransport;

class ExtendedMailgunTransport extends MailgunTransport
{
    private $zone;

    public function __construct(ClientInterface $client, string $key, string $domain, string $zone)
    {
        $this->setZone($zone);
        parent::__construct($client, $key, $domain);
    }

    private function setZone(string $zone)
    {
        $this->zone = $zone;
    }

    /**
     * Set the domain being used by the transport.
     *
     * @param  string $domain
     * @return string
     */
    public function setDomain($domain)
    {
        $this->url = 'https://api.mailgun.net/v3/' . $domain . '/messages.mime';
        if ($this->zone === 'EU') {
            $this->url = 'https://api.eu.mailgun.net/v3/' . $domain . '/messages.mime';
        }

        return $this->domain = $domain;
    }
}

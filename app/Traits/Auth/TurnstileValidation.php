<?php

namespace ec5\Traits\Auth;

use ec5\Http\Validation\Auth\RuleTurnstile;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

trait TurnstileValidation
{
    /**
     * @throws GuzzleException
     */
    public function getAnyTurnstileErrors($turnstileResponse): array
    {
        $client = new Client();
        $ruleTurnstile = new RuleTurnstile();
        $verifyEndpoint = config('epicollect.setup.cloudflare_turnstile.verify_endpoint');
        $response = $client->post($verifyEndpoint, [
            'timeout' => 5.0,
            'connect_timeout' => 2.0,
            'form_params' => [
                'secret' => config('epicollect.setup.cloudflare_turnstile.secret_key'),
                'response' => $turnstileResponse,
                'remoteip' => request()->ip()
            ]
        ]);

        $arrayResponse = json_decode($response->getBody()->getContents(), true);
        if (!is_array($arrayResponse)) {
            return ['captcha' => ['ec5_380']];
        }

        $ruleTurnstile->validate($arrayResponse);
        if ($ruleTurnstile->hasErrors()) {
            return $ruleTurnstile->errors();
        }

        $ruleTurnstile->additionalChecks($arrayResponse);
        if ($ruleTurnstile->hasErrors()) {
            return $ruleTurnstile->errors();
        }

        return [];
    }
}
